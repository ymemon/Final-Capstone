<?php
declare(strict_types=1);
namespace Rebound;

final class Marketing
{
    public function __construct(private Db $db) {}

    public function audience(): array
    {
        return $this->db->all(
            'SELECT id, name, email FROM clients WHERE marketing_consent = 1
             AND unsubscribed_at IS NULL AND lower(email) <> lower(?) ORDER BY name, email',
            [$this->db->setting('studio_email', '')]
        );
    }

    public function setPreference(int $id, bool $enabled): void
    {
        if ($this->db->one('SELECT id FROM clients WHERE id = ?', [$id]) === null) {
            throw new \RuntimeException('Customer not found.');
        }
        $this->db->run(
            'UPDATE clients SET marketing_consent = ?, consent_at = ?, consent_source = ?,
             unsubscribed_at = ? WHERE id = ?',
            [$enabled ? 1 : 0, $enabled ? Clock::nowSql() : null,
             $enabled ? 'studio_customer_request' : 'studio_opt_out',
             $enabled ? null : Clock::nowSql(), $id]
        );
    }

    public static function validate(string $subject, string $body): void
    {
        if ($subject === '' || strlen($subject) > 180 || preg_match('/[\r\n]/', $subject)) {
            throw new \RuntimeException('Enter a subject of 1–180 characters on one line.');
        }
        if ($body === '' || strlen($body) > 20000) {
            throw new \RuntimeException('Enter a message of up to 20,000 characters.');
        }
    }

    public function preview(string $subject, string $body): array
    {
        self::validate($subject, $body);
        return [
            'subject' => $subject, 'recipients' => count($this->audience()),
            'text' => Templates::render('marketing', ['campaign_body' => $body,
                'unsubscribe_url' => '[Your personal marketing opt-out link]'],
                $this->db, rtrim($this->db->setting('site_url', ''), '/')),
        ];
    }

    public function queue(string $key, string $subject, string $body): array
    {
        self::validate($subject, $body);
        if (!preg_match('/^[a-zA-Z0-9-]{20,64}$/', $key)) {
            throw new \RuntimeException('Please preview the message again before sending.');
        }
        return $this->db->transact(function (Db $db) use ($key, $subject, $body) {
            $existing = $db->one('SELECT * FROM campaigns WHERE request_key = ?', [$key]);
            if ($existing !== null) {
                if ($existing['subject'] !== $subject || $existing['body'] !== $body) {
                    throw new \RuntimeException('This send request has already been used. Preview the new message first.');
                }
                return ['id' => (int) $existing['id'], 'queued' => (int) $existing['recipient_count']];
            }
            $recipients = $this->audience();
            if (!$recipients) {
                throw new \RuntimeException('There are no customers receiving marketing emails yet.');
            }
            $id = $db->insert(
                'INSERT INTO campaigns (request_key, subject, body, created_at, recipient_count) VALUES (?, ?, ?, ?, ?)',
                [$key, $subject, $body, Clock::nowSql(), count($recipients)]
            );
            foreach ($recipients as $client) {
                $db->run(
                    'INSERT INTO mail_queue (to_email, to_name, subject, template, payload_json, send_after, kind, dedupe_key)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$client['email'], $client['name'], $subject, 'marketing',
                     json_encode(['campaign_body' => $body, 'client_email' => $client['email']]),
                     Clock::nowSql(), 'marketing', 'campaign:' . $id . ':' . $client['id']]
                );
            }
            return ['id' => $id, 'queued' => count($recipients)];
        });
    }
}
