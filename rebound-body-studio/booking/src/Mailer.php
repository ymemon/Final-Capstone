<?php
declare(strict_types=1);

namespace Rebound;

/**
 * Queued outbound mail.
 *
 * The app always writes a durable queue row first. On the GoDaddy preview an
 * off-host worker leases rendered messages through the protected handoff API,
 * delivers them through authenticated AZWebCorp SMTP, and acknowledges each
 * result. That buys three things:
 *   - a client never waits on an SMTP handshake to see "request received";
 *   - a transient send failure is retried instead of vanishing;
 *   - reminders are just rows with a future send_after, so scheduling one is
 *     the same operation as sending one.
 *
 * Marketing mail goes out through the same queue but is gated on consent at
 * the moment of sending, not the moment of queueing. Someone who unsubscribes
 * after a campaign is queued but before it drains does not receive it - which
 * is the case that catches most homegrown senders out.
 */
final class Mailer
{
    private Db $db;
    /** @var callable(array):bool */
    private $transport;

    /**
     * @param callable(array):bool|null $transport Injectable so tests can
     *        assert on what would have been sent without sending anything.
     */
    public function __construct(Db $db, ?callable $transport = null)
    {
        $this->db = $db;
        $this->transport = $transport ?? [$this, 'sendViaMail'];
    }

    private function baseUrl(): string
    {
        return rtrim($this->db->setting('site_url', 'https://reboundbodystudio.com'), '/');
    }

    // ---- queueing --------------------------------------------------------

    public function queuePackage(string $email, string $name, string $subject, string $body, string $key): void
    {
        $this->enqueue($email, $name, $subject, 'package_notice', ['package_body' => $body],
            Clock::nowSql(), null, 'transactional', $key);
    }

    /**
     * Queue a client-facing message about one appointment.
     * The dedupe key makes re-queueing the same message a no-op, so a retried
     * webhook or a double-clicked approve button cannot mail twice.
     */
    public function queueAppointment(string $template, int $apptId, ?string $sendAfter = null): void
    {
        $a = $this->appointmentPayload($apptId);
        if ($a === null) {
            return;
        }
        $this->enqueue(
            $a['client_email'],
            $a['client_name'],
            $this->subjectFor($template, $a),
            $template,
            $a,
            $sendAfter ?? Clock::nowSql(),
            $apptId,
            'transactional',
            $template . ':' . $apptId
        );
    }

    /** Queue the studio's own copy - she needs to know a request came in. */
    public function queueStudioAlert(string $template, int $apptId): void
    {
        $a = $this->appointmentPayload($apptId);
        if ($a === null) {
            return;
        }
        $to = $this->db->setting('studio_email', '');
        if ($to === '') {
            return;
        }
        $this->enqueue(
            $to,
            $this->db->setting('studio_name', 'Rebound Body Studio'),
            $this->subjectFor($template, $a),
            $template,
            $a,
            Clock::nowSql(),
            $apptId,
            'transactional',
            $template . ':' . $apptId
        );
    }

    /**
     * Schedule the 24-hour reminder. Skipped when the appointment is already
     * closer than that - a "reminder" arriving after the session would be
     * worse than none.
     */
    public function scheduleReminder(int $apptId): void
    {
        $a = $this->appointmentPayload($apptId);
        if ($a === null) {
            return;
        }
        $lead = (int) ($this->db->setting('reminder_lead_hours', '24'));
        $sendAt = Clock::fromSql($a['starts_at'])->modify("-{$lead} hours");
        if ($sendAt <= Clock::nowUtc()) {
            return;
        }
        $this->queueAppointment('reminder_24h', $apptId, $sendAt->format(Clock::SQL));
    }

    /** Pull an unsent message - used when an appointment is cancelled. */
    public function dropPending(int $apptId, string $template): void
    {
        $this->db->run(
            'DELETE FROM mail_queue
              WHERE appointment_id = ? AND template = ? AND sent_at IS NULL',
            [$apptId, $template]
        );
    }

    private function enqueue(
        string $toEmail,
        string $toName,
        string $subject,
        string $template,
        array $payload,
        string $sendAfter,
        ?int $apptId,
        string $kind,
        ?string $dedupeKey
    ): void {
        if ($dedupeKey !== null) {
            $exists = $this->db->value(
                'SELECT 1 FROM mail_queue WHERE dedupe_key = ?',
                [$dedupeKey]
            );
            if ($exists !== null) {
                return;
            }
        }
        $this->db->run(
            'INSERT INTO mail_queue
                (to_email, to_name, subject, template, payload_json, send_after,
                 appointment_id, kind, dedupe_key)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $toEmail, $toName, $subject, $template,
                json_encode($payload, JSON_UNESCAPED_SLASHES),
                $sendAfter, $apptId, $kind, $dedupeKey,
            ]
        );
    }

    // ---- draining --------------------------------------------------------

    /**
     * Lease due messages for the off-host authenticated SMTP worker.
     *
     * @return array{messages:array,skipped:int}
     */
    public function claimForExternalDelivery(string $claimToken, int $limit = 50): array
    {
        if (!preg_match('/^[a-f0-9]{32,64}$/', $claimToken)) {
            throw new \InvalidArgumentException('Invalid claim token');
        }

        $now = Clock::nowSql();
        $stale = Clock::nowUtc()->modify('-10 minutes')->format(Clock::SQL);
        $due = $this->db->all(
            'SELECT * FROM mail_queue
              WHERE sent_at IS NULL AND send_after <= ? AND attempts < 5
                AND (claim_token IS NULL OR claimed_at IS NULL OR claimed_at <= ?)
           ORDER BY send_after
              LIMIT ' . (int) $limit,
            [$now, $stale]
        );

        $messages = [];
        $skipped = 0;
        foreach ($due as $row) {
            $claimed = $this->db->run(
                'UPDATE mail_queue
                    SET attempts = attempts + 1, claim_token = ?, claimed_at = ?, last_error = ?
                  WHERE id = ? AND attempts = ? AND sent_at IS NULL
                    AND (claim_token IS NULL OR claimed_at IS NULL OR claimed_at <= ?)',
                [$claimToken, $now, '', $row['id'], $row['attempts'], $stale]
            )->rowCount();
            if ($claimed === 0) {
                continue;
            }

            if ($row['template'] === 'admin_login_token' && !$this->loginTokenIsUsable($row)) {
                $this->db->run(
                    'UPDATE mail_queue
                        SET sent_at = ?, last_error = ?, claim_token = NULL, claimed_at = NULL
                      WHERE id = ? AND claim_token = ?',
                    [Clock::nowSql(), 'skipped: login token expired, used, or superseded', $row['id'], $claimToken]
                );
                $skipped++;
                continue;
            }

            if ($row['kind'] === 'marketing' && !$this->mayReceiveMarketing($row['to_email'])) {
                $this->db->run(
                    'UPDATE mail_queue
                        SET sent_at = ?, last_error = ?, claim_token = NULL, claimed_at = NULL
                      WHERE id = ? AND claim_token = ?',
                    [Clock::nowSql(), 'skipped: no consent at send time', $row['id'], $claimToken]
                );
                $skipped++;
                continue;
            }

            try {
                $message = $this->render($row);
                $message['queue_id'] = (int) $row['id'];
                $message['claim_token'] = $claimToken;
                $messages[] = $message;
            } catch (\Throwable $error) {
                $this->finishExternalDelivery(
                    (int) $row['id'],
                    $claimToken,
                    false,
                    'render failed: ' . $error->getMessage()
                );
            }
        }

        return ['messages' => $messages, 'skipped' => $skipped];
    }

    /** Recheck the lease and preference immediately before SMTP delivery. */
    public function externalDeliveryEligible(int $queueId, string $claimToken): bool
    {
        $row = $this->db->one('SELECT * FROM mail_queue WHERE id = ? AND claim_token = ? AND sent_at IS NULL', [$queueId, $claimToken]);
        if ($row === null || $claimToken === '') return false;
        $eligible = ($row['kind'] !== 'marketing' || $this->mayReceiveMarketing($row['to_email']))
            && ($row['template'] !== 'admin_login_token' || $this->loginTokenIsUsable($row));
        if (!$eligible) {
            $this->db->run('UPDATE mail_queue SET sent_at = ?, last_error = ?, claim_token = NULL, claimed_at = NULL WHERE id = ? AND claim_token = ?',
                [Clock::nowSql(), 'skipped: preference or login changed before delivery', $queueId, $claimToken]);
        }
        return $eligible;
    }

    public function finishExternalDelivery(
        int $queueId,
        string $claimToken,
        bool $sent,
        string $error = ''
    ): bool {
        if ($sent) {
            $statement = $this->db->run(
                'UPDATE mail_queue
                    SET sent_at = ?, last_error = ?, claim_token = NULL, claimed_at = NULL
                  WHERE id = ? AND claim_token = ? AND sent_at IS NULL',
                [Clock::nowSql(), '', $queueId, $claimToken]
            );
        } else {
            $statement = $this->db->run(
                'UPDATE mail_queue
                    SET last_error = ?, claim_token = NULL, claimed_at = NULL
                  WHERE id = ? AND claim_token = ? AND sent_at IS NULL',
                [substr('external delivery failed: ' . $error, 0, 500), $queueId, $claimToken]
            );
        }

        return $statement->rowCount() === 1;
    }

    /**
     * Send everything due. Called by cron, and safe to call concurrently:
     * a row is claimed by bumping attempts before the send is attempted, so
     * two overlapping runs cannot both take the same message.
     *
     * @return array{sent:int, failed:int, skipped:int}
     */
    public function drain(int $limit = 50): array
    {
        $due = $this->db->all(
            'SELECT * FROM mail_queue
              WHERE sent_at IS NULL AND send_after <= ? AND attempts < 5
           ORDER BY send_after
              LIMIT ' . (int) $limit,
            [Clock::nowSql()]
        );

        $sent = $failed = $skipped = 0;
        foreach ($due as $row) {
            $claimed = $this->db->run(
                'UPDATE mail_queue SET attempts = attempts + 1
                  WHERE id = ? AND attempts = ? AND sent_at IS NULL',
                [$row['id'], $row['attempts']]
            )->rowCount();
            if ($claimed === 0) {
                continue;   // another worker has it
            }

            // Never deliver a passwordless-login email after its token has
            // expired, been used, or been superseded. A delayed queue drain
            // must not send a link that can only frustrate the studio owner.
            if ($row['template'] === 'admin_login_token' && !$this->loginTokenIsUsable($row)) {
                $this->db->run(
                    'UPDATE mail_queue SET sent_at = ?, last_error = ? WHERE id = ?',
                    [Clock::nowSql(), 'skipped: login token expired, used, or superseded', $row['id']]
                );
                $skipped++;
                continue;
            }

            // Consent is checked here, not at queue time: a campaign queued
            // this morning must not reach someone who unsubscribed at noon.
            if ($row['kind'] === 'marketing' && !$this->mayReceiveMarketing($row['to_email'])) {
                $this->db->run(
                    'UPDATE mail_queue SET sent_at = ?, last_error = ? WHERE id = ?',
                    [Clock::nowSql(), 'skipped: no consent at send time', $row['id']]
                );
                $skipped++;
                continue;
            }

            try {
                $message = $this->render($row);
                $ok = ($this->transport)($message);
                if ($ok) {
                    $this->db->run(
                        'UPDATE mail_queue SET sent_at = ? WHERE id = ?',
                        [Clock::nowSql(), $row['id']]
                    );
                    $sent++;
                } else {
                    $this->db->run(
                        'UPDATE mail_queue SET last_error = ? WHERE id = ?',
                        ['transport returned false', $row['id']]
                    );
                    $failed++;
                }
            } catch (\Throwable $e) {
                $this->db->run(
                    'UPDATE mail_queue SET last_error = ? WHERE id = ?',
                    [substr($e->getMessage(), 0, 500), $row['id']]
                );
                $failed++;
            }
        }
        return ['sent' => $sent, 'failed' => $failed, 'skipped' => $skipped];
    }

    private function loginTokenIsUsable(array $row): bool
    {
        $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
        $loginUrl = is_array($payload) ? (string) ($payload['login_url'] ?? '') : '';
        $query = parse_url($loginUrl, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return false;
        }

        parse_str($query, $params);
        $token = (string) ($params['token'] ?? '');
        if ($token === '') {
            return false;
        }

        return $this->db->one(
            'SELECT id FROM login_tokens
              WHERE token_hash = ? AND used_at IS NULL AND expires_at > ?',
            [hash('sha256', $token), Clock::nowSql()]
        ) !== null;
    }

    private function mayReceiveMarketing(string $email): bool
    {
        $c = $this->db->one(
            'SELECT marketing_consent, unsubscribed_at FROM clients WHERE email = ?',
            [strtolower($email)]
        );
        return $c !== null
            && (int) $c['marketing_consent'] === 1
            && $c['unsubscribed_at'] === null;
    }

    // ---- unsubscribe -----------------------------------------------------

    /**
     * One click, no login, no "are you sure". Randi's specific complaint about
     * MassageBook was having to remove people by hand; this is the whole fix.
     * Idempotent. The HTTP route uses POST so mail scanners cannot opt clients out.
     */
    public function unsubscribe(string $token): bool
    {
        $c = $this->db->one('SELECT * FROM clients WHERE unsub_token = ?', [$token]);
        if ($c === null) {
            return false;
        }
        if ($c['unsubscribed_at'] === null) {
            $this->db->run(
                'UPDATE clients
                    SET marketing_consent = 0, unsubscribed_at = ?
                  WHERE id = ?',
                [Clock::nowSql(), $c['id']]
            );
        }
        return true;
    }

    // ---- rendering -------------------------------------------------------

    private function subjectFor(string $template, array $a): string
    {
        $studio = $this->db->setting('studio_name', 'Rebound Body Studio');
        $when   = Clock::human($a['starts_at']);
        switch ($template) {
            case 'request_received':
                return "We have your request - {$when}";
            case 'confirmed':
                return "Confirmed: {$a['service_name']}, {$when}";
            case 'declined':
                return "About your request for {$when}";
            case 'reminder_24h':
                return "Tomorrow: {$a['service_name']} at " . Clock::humanTime($a['starts_at']);
            case 'cancelled_by_client':
            case 'cancelled_by_studio':
                return "Cancelled: {$when}";
            case 'studio_new_request':
                return "New booking request - {$a['client_name']}, {$when}";
            case 'studio_cancellation':
                return "Cancellation - {$a['client_name']}, {$when}";
            default:
                return $studio;
        }
    }

    /** @return array{to:string,to_name:string,subject:string,text:string,headers:array} */
    public function render(array $row): array
    {
        $p = json_decode($row['payload_json'], true) ?: [];
        $body = Templates::render($row['template'], $p, $this->db, $this->baseUrl());

        $headers = [
            'From'     => sprintf(
                '%s <%s>',
                $this->db->setting('mail_from_name', 'Rebound Body Studio'),
                $this->db->setting('mail_from', 'hello@reboundbodystudio.com')
            ),
            'Reply-To' => $this->db->setting('studio_email', ''),
        ];

        // RFC 8058: gives Gmail and Apple Mail a native unsubscribe button and
        // keeps complaints off the spam-report path.
        if ($row['kind'] === 'marketing') {
            $c = $this->db->one(
                'SELECT unsub_token FROM clients WHERE email = ?',
                [strtolower($row['to_email'])]
            );
            if ($c !== null) {
                $url = $this->baseUrl() . '/booking/api/unsubscribe.php?t=' . urlencode($c['unsub_token']);
                $headers['List-Unsubscribe']      = '<' . $url . '>';
                $headers['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
            }
        }

        return [
            'to'      => $row['to_email'],
            'to_name' => $row['to_name'],
            'subject' => $row['subject'],
            'text'    => $body,
            'headers' => $headers,
        ];
    }

    private function sendViaMail(array $m): bool
    {
        $h = [];
        foreach ($m['headers'] as $k => $v) {
            if ($v !== '') {
                $h[] = $k . ': ' . $v;
            }
        }

        // GoDaddy Managed WordPress accepts PHP mail() locally but does not
        // reliably deliver it, and blocks outbound SMTP sockets. Production
        // delivery is therefore performed by the off-host authenticated worker.
        if (is_readable(dirname(__DIR__, 4) . '/wp-load.php')) {
            throw new \RuntimeException('Server-side delivery is disabled; use the authenticated external worker');
        }

        // Local-development fallback. Tests normally inject their own transport.
        return mail($m['to'], $m['subject'], $m['text'], implode("\r\n", $h));
    }

    // ---- data ------------------------------------------------------------

    private function appointmentPayload(int $apptId): ?array
    {
        return $this->db->one(
            'SELECT a.id, a.starts_at, a.ends_at, a.status, a.manage_token, a.package_purchase_id,
                    a.client_note, a.studio_note, a.technique_pref, a.cancel_reason,
                    s.name AS service_name, s.duration_min, s.price_cents,
                    c.name AS client_name, c.email AS client_email, c.phone AS client_phone
               FROM appointments a
               JOIN services s ON s.id = a.service_id
               JOIN clients  c ON c.id = a.client_id
              WHERE a.id = ?',
            [$apptId]
        );
    }
}
