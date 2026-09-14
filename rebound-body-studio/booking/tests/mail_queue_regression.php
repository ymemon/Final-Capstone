<?php
declare(strict_types=1);

namespace Rebound;

require_once __DIR__ . '/../src/Clock.php';
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/Templates.php';
require_once __DIR__ . '/../src/Mailer.php';

$dbFile = tempnam(sys_get_temp_dir(), 'rebound-mail-test-');
if ($dbFile === false) {
    throw new \RuntimeException('Could not create temporary database');
}

try {
    $db = Db::sqlite($dbFile);
    $db->pdo()->exec((string) file_get_contents(__DIR__ . '/../migrations/001_schema.sql'));

    $now = Clock::nowUtc();
    $clientId = $db->insert(
        'INSERT INTO clients (email, name, created_at, unsub_token) VALUES (?, ?, ?, ?)',
        ['owner@example.test', 'Owner', Clock::nowSql(), Db::token()]
    );

    $expiredToken = Db::token();
    $validToken = Db::token();
    foreach ([
        [$expiredToken, $now->modify('-1 minute')->format(Clock::SQL)],
        [$validToken, $now->modify('+15 minutes')->format(Clock::SQL)],
    ] as [$token, $expiresAt]) {
        $db->run(
            'INSERT INTO login_tokens (client_id, token_hash, expires_at) VALUES (?, ?, ?)',
            [$clientId, hash('sha256', $token), $expiresAt]
        );
        $db->run(
            'INSERT INTO mail_queue
                (to_email, to_name, subject, template, payload_json, send_after, kind)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                'owner@example.test', 'Owner', 'Login', 'admin_login_token',
                json_encode(['login_url' => 'https://example.test/admin?token=' . $token]),
                Clock::nowSql(), 'transactional',
            ]
        );
    }

    $delivered = [];
    $mailer = new Mailer($db, static function (array $message) use (&$delivered): bool {
        $delivered[] = $message;
        return true;
    });
    $result = $mailer->drain(10);

    if ($result !== ['sent' => 1, 'failed' => 0, 'skipped' => 1]) {
        throw new \RuntimeException('Unexpected drain result: ' . json_encode($result));
    }
    if (count($delivered) !== 1 || !str_contains($delivered[0]['text'], $validToken)) {
        throw new \RuntimeException('The valid login message was not the only delivered message');
    }

    $stale = $db->one(
        "SELECT sent_at, last_error FROM mail_queue
          WHERE payload_json LIKE ?",
        ['%' . $expiredToken . '%']
    );
    if ($stale === null || $stale['sent_at'] === null || !str_starts_with($stale['last_error'], 'skipped:')) {
        throw new \RuntimeException('The expired login message was not recorded as skipped');
    }

    $externalToken = Db::token();
    $db->run(
        'INSERT INTO login_tokens (client_id, token_hash, expires_at) VALUES (?, ?, ?)',
        [$clientId, hash('sha256', $externalToken), $now->modify('+15 minutes')->format(Clock::SQL)]
    );
    $externalQueueId = $db->insert(
        'INSERT INTO mail_queue
            (to_email, to_name, subject, template, payload_json, send_after, kind)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        [
            'owner@example.test', 'Owner', 'External login', 'admin_login_token',
            json_encode(['login_url' => 'https://example.test/admin?token=' . $externalToken]),
            Clock::nowSql(), 'transactional',
        ]
    );

    $claimToken = str_repeat('a', 32);
    $claimed = $mailer->claimForExternalDelivery($claimToken, 10);
    if (count($claimed['messages']) !== 1 || $claimed['messages'][0]['queue_id'] !== $externalQueueId) {
        throw new \RuntimeException('External worker did not lease exactly the valid queue row');
    }
    if (!$mailer->finishExternalDelivery($externalQueueId, $claimToken, true)) {
        throw new \RuntimeException('External worker acknowledgement was rejected');
    }
    $acknowledged = $db->one('SELECT sent_at, claim_token FROM mail_queue WHERE id = ?', [$externalQueueId]);
    if ($acknowledged === null || $acknowledged['sent_at'] === null || $acknowledged['claim_token'] !== null) {
        throw new \RuntimeException('External delivery acknowledgement did not finalize the queue row');
    }

    echo "mail queue regression test passed\n";
} finally {
    @unlink($dbFile);
}
