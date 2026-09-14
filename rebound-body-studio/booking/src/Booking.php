<?php
declare(strict_types=1);

namespace Rebound;

/**
 * The appointment lifecycle.
 *
 * Every booking arrives as a REQUEST and waits for her to approve it. That is
 * a deliberate product decision, not a limitation: she asked to approve every
 * booking, and for a solo practitioner who may need to say "not that day" it
 * is the right default. Nothing in here ever creates a confirmed appointment
 * from the public side.
 *
 * Two invariants this class is responsible for:
 *   1. No two blocking appointments may overlap. Enforced by re-checking
 *      availability inside the same transaction as the insert, not by trusting
 *      whatever the browser posted back.
 *   2. A package credit is spent at most once, and always comes back if the
 *      appointment does not happen. Enforced through the ledger, so the
 *      counter and the history can never disagree.
 */
final class Booking
{
    private Db $db;
    private Availability $avail;
    private Mailer $mail;

    public function __construct(Db $db, Availability $avail, Mailer $mail)
    {
        $this->db    = $db;
        $this->avail = $avail;
        $this->mail  = $mail;
    }

    public const HELD    = ['requested', 'confirmed'];
    public const REFUNDS = ['declined', 'cancelled'];

    /**
     * Public entry point: a client asks for a slot.
     *
     * @throws SlotTakenException  if the slot went while they were filling the form
     * @throws \RuntimeException   on bad input or an unusable credit
     * @return array{id:int, manage_token:string}
     */
    public function request(
        string $email,
        string $name,
        string $phone,
        int $serviceId,
        string $startSql,
        string $clientNote = '',
        string $techniquePref = '',
        bool $marketingOptIn = true,
        ?int $packagePurchaseId = null,
        string $packageToken = ''
    ): array {
        $email = trim(strtolower($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \RuntimeException('That email address does not look right.');
        }
        $name = trim($name);
        if ($name === '') {
            throw new \RuntimeException('Please give a name for the appointment.');
        }

        $service = $this->db->one(
            'SELECT * FROM services WHERE id = ? AND active = 1',
            [$serviceId]
        );
        if ($service === null) {
            throw new \RuntimeException('That service is not currently offered.');
        }

        $staffId = $this->avail->defaultStaffId();
        if ($staffId === null) {
            throw new \RuntimeException('Online booking is not available at the moment.');
        }

        // Derive the end from the service, never from the client. Otherwise a
        // crafted post could claim a 30-minute end for a 120-minute session
        // and quietly free up the room for a collision.
        $start = Clock::fromSql($startSql);
        $end   = $start->modify('+' . (int) $service['duration_min'] . ' minutes');
        $endSql = $end->format(Clock::SQL);

        return $this->db->transact(function (Db $db) use (
            $email, $name, $phone, $service, $staffId, $startSql, $endSql,
            $clientNote, $techniquePref, $marketingOptIn, $packagePurchaseId, $packageToken
        ) {
            if (!$this->avail->isStillFree($staffId, $startSql, $endSql)) {
                throw new SlotTakenException(
                    'Sorry - that time was taken while you were filling in the form. '
                    . 'Please pick another.'
                );
            }

            $client = $this->upsertClient($email, $name, $phone, $marketingOptIn);

            if ($packagePurchaseId !== null) {
                require_once __DIR__ . '/Packages.php';
                $authorized = (new Packages($db, $this->mail))->authorizedPurchase($packageToken);
                if ($authorized !== $packagePurchaseId) throw new \RuntimeException('This link does not authorize that package.');
                $this->assertCreditUsable($packagePurchaseId, (int) $client['id'], $service);
            }

            $token = Db::token();
            $apptId = $db->insert(
                'INSERT INTO appointments
                    (client_id, service_id, staff_id, starts_at, ends_at, status,
                     client_note, technique_pref, created_at, package_purchase_id,
                     manage_token)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $client['id'], $service['id'], $staffId, $startSql, $endSql,
                    'requested', $clientNote, $techniquePref, Clock::nowSql(),
                    $packagePurchaseId, $token,
                ]
            );

            if ($packagePurchaseId !== null) {
                $this->spendCredit($packagePurchaseId, $apptId);
            }

            $this->mail->queueAppointment('request_received', $apptId);
            $this->mail->queueStudioAlert('studio_new_request', $apptId);

            return ['id' => $apptId, 'manage_token' => $token];
        });
    }

    /** She approves. The client is told, and a reminder is scheduled. */
    public function approve(int $apptId, string $studioNote = ''): void
    {
        $this->db->transact(function (Db $db) use ($apptId, $studioNote) {
            $appt = $this->lockAppointment($apptId);
            if ($appt['status'] !== 'requested') {
                throw new \RuntimeException(
                    "Only a pending request can be approved (this one is {$appt['status']})."
                );
            }
            $db->run(
                'UPDATE appointments
                    SET status = ?, decided_at = ?, studio_note = ?
                  WHERE id = ?',
                ['confirmed', Clock::nowSql(), $studioNote, $apptId]
            );
            $this->mail->queueAppointment('confirmed', $apptId);
            $this->mail->scheduleReminder($apptId);
        });
    }

    public function decline(int $apptId, string $reason = ''): void
    {
        $this->db->transact(function (Db $db) use ($apptId, $reason) {
            $appt = $this->lockAppointment($apptId);
            if ($appt['status'] !== 'requested') {
                throw new \RuntimeException('Only a pending request can be declined.');
            }
            $db->run(
                'UPDATE appointments
                    SET status = ?, decided_at = ?, cancel_reason = ?
                  WHERE id = ?',
                ['declined', Clock::nowSql(), $reason, $apptId]
            );
            $this->refundCreditIfAny($appt, 'declined');
            $this->mail->queueAppointment('declined', $apptId);
        });
    }

    /**
     * Cancellation, by either side. $by is recorded so the confirmation email
     * can be worded honestly - "you cancelled" and "we had to cancel" are very
     * different messages to receive.
     */
    public function cancel(int $apptId, string $by = 'client', string $reason = ''): void
    {
        $this->db->transact(function (Db $db) use ($apptId, $by, $reason) {
            $appt = $this->lockAppointment($apptId);
            if (!in_array($appt['status'], self::HELD, true)) {
                throw new \RuntimeException(
                    "That appointment is already {$appt['status']}."
                );
            }
            $db->run(
                'UPDATE appointments
                    SET status = ?, cancelled_at = ?, cancel_reason = ?
                  WHERE id = ?',
                ['cancelled', Clock::nowSql(), $reason, $apptId]
            );
            $this->refundCreditIfAny($appt, 'cancelled');
            // The slot is free again, so any pending reminder must not go out.
            $this->mail->dropPending($apptId, 'reminder_24h');
            $this->mail->queueAppointment(
                $by === 'studio' ? 'cancelled_by_studio' : 'cancelled_by_client',
                $apptId
            );
            if ($by === 'client') {
                $this->mail->queueStudioAlert('studio_cancellation', $apptId);
            }
        });
    }

    /** After the fact, for her records and for the client's package maths. */
    public function markCompleted(int $apptId): void
    {
        $this->setTerminal($apptId, 'completed');
    }

    /**
     * A no-show still consumes the credit - the room was held and the hour is
     * gone. She can hand it back from the admin screen if she wants to.
     */
    public function markNoShow(int $apptId): void
    {
        $this->setTerminal($apptId, 'no_show');
    }

    private function setTerminal(int $apptId, string $status): void
    {
        $this->db->transact(function (Db $db) use ($apptId, $status) {
            $appt = $this->lockAppointment($apptId);
            if ($appt['status'] !== 'confirmed') {
                throw new \RuntimeException(
                    'Only a confirmed appointment can be marked ' . $status . '.'
                );
            }
            $db->run('UPDATE appointments SET status = ? WHERE id = ?', [$status, $apptId]);
            $this->mail->dropPending($apptId, 'reminder_24h');
        });
    }

    // ---- clients ---------------------------------------------------------

    /** Default enrollment applies only to new clients; prior preferences survive rebooking. */
    private function upsertClient(string $email, string $name, string $phone, bool $enabled): array
    {
        $existing = $this->db->one('SELECT * FROM clients WHERE email = ?', [$email]);
        if ($existing === null) {
            $id = $this->db->insert(
                'INSERT INTO clients (email, name, phone, created_at, marketing_consent,
                 consent_at, consent_source, unsub_token, unsubscribed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$email, $name, $phone, Clock::nowSql(), $enabled ? 1 : 0,
                 $enabled ? Clock::nowSql() : null, $enabled ? 'booking_default' : 'booking_opt_out',
                 Db::token(), $enabled ? null : Clock::nowSql()]
            );
        } else {
            $id = (int) $existing['id'];
            $this->db->run('UPDATE clients SET name = ?, phone = ? WHERE id = ?',
                [$name, $phone !== '' ? $phone : $existing['phone'], $id]);
            if (!$enabled) {
                $this->db->run('UPDATE clients SET marketing_consent = 0,
                    unsubscribed_at = COALESCE(unsubscribed_at, ?), consent_source = ? WHERE id = ?',
                    [Clock::nowSql(), 'booking_opt_out', $id]);
            }
        }
        return $this->db->one('SELECT * FROM clients WHERE id = ?', [$id]);
    }

    // ---- package credits -------------------------------------------------

    private function assertCreditUsable(int $purchaseId, int $clientId, array $service): void
    {
        $p = $this->db->one(
            'SELECT * FROM package_purchases WHERE id = ?',
            [$purchaseId]
        );
        if ($p === null) {
            throw new \RuntimeException('That package does not exist.');
        }
        if ($p['status'] !== 'active') {
            throw new \RuntimeException('That package is no longer active.');
        }
        if ((int) $p['sessions_used'] >= (int) $p['sessions_total']) {
            throw new \RuntimeException('That package has no sessions left on it.');
        }
        if ($p['expires_at'] !== null && $p['expires_at'] <= Clock::nowSql()) {
            throw new \RuntimeException('That package has expired.');
        }
        $pkg = $this->db->one('SELECT * FROM packages WHERE id = ?', [$p['package_id']]);
        if (!$this->db->value('SELECT 1 FROM package_services WHERE package_id=? AND service_id=?', [$p['package_id'], $service['id']])) {
            throw new \RuntimeException('Choose an eligible massage and the session length included in this package.');
        }
        if ($pkg !== null
            && $pkg['service_id'] !== null
            && (int) $pkg['service_id'] !== (int) $service['id']) {
            throw new \RuntimeException(
                'That package cannot be used for ' . $service['name'] . '.'
            );
        }
    }

    private function spendCredit(int $purchaseId, int $apptId): void
    {
        $changed = $this->db->run(
            "UPDATE package_purchases SET sessions_used = sessions_used + 1 WHERE id = ? AND status='active' AND sessions_used < sessions_total",
            [$purchaseId]
        )->rowCount();
        if ($changed !== 1) throw new \RuntimeException('No package sessions remain. Refresh your balance.');
        $this->db->run(
            'INSERT INTO credit_ledger
                (package_purchase_id, appointment_id, delta, reason, created_at)
             VALUES (?, ?, ?, ?, ?)',
            [$purchaseId, $apptId, -1, 'booked', Clock::nowSql()]
        );
    }

    /**
     * Give the credit back when an appointment will not happen.
     * Idempotent by construction: it checks the ledger for an existing refund
     * before writing one, so a double-cancel cannot mint a free session.
     */
    private function refundCreditIfAny(array $appt, string $reason): void
    {
        $purchaseId = $appt['package_purchase_id'];
        if ($purchaseId === null) {
            return;
        }
        $already = $this->db->value(
            'SELECT 1 FROM credit_ledger
              WHERE appointment_id = ? AND delta > 0 LIMIT 1',
            [$appt['id']]
        );
        if ($already !== null) {
            return;
        }
        $this->db->run(
            'UPDATE package_purchases
                SET sessions_used = CASE WHEN sessions_used > 0
                                         THEN sessions_used - 1 ELSE 0 END
              WHERE id = ?',
            [$purchaseId]
        );
        $this->db->run(
            'INSERT INTO credit_ledger
                (package_purchase_id, appointment_id, delta, reason, created_at)
             VALUES (?, ?, ?, ?, ?)',
            [$purchaseId, $appt['id'], 1, $reason, Clock::nowSql()]
        );
    }

    // ---- helpers ---------------------------------------------------------

    private function lockAppointment(int $apptId): array
    {
        $lock = $this->db->supportsForUpdate() ? ' FOR UPDATE' : '';
        $appt = $this->db->one(
            'SELECT * FROM appointments WHERE id = ?' . $lock,
            [$apptId]
        );
        if ($appt === null) {
            throw new \RuntimeException('No such appointment.');
        }
        return $appt;
    }

    public function byManageToken(string $token): ?array
    {
        return $this->db->one(
            'SELECT a.*, s.name AS service_name, s.duration_min, s.price_cents,
                    c.name AS client_name, c.email AS client_email
               FROM appointments a
               JOIN services s ON s.id = a.service_id
               JOIN clients  c ON c.id = a.client_id
              WHERE a.manage_token = ?',
            [$token]
        );
    }
}

/** Thrown when a slot is taken between being displayed and being requested. */
final class SlotTakenException extends \RuntimeException
{
}
