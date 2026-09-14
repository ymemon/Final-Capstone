<?php
declare(strict_types=1);

namespace Rebound;

require_once __DIR__ . '/../../src/Clock.php';
require_once __DIR__ . '/../../src/Db.php';
require_once __DIR__ . '/../../src/Config.php';
require_once __DIR__ . '/../../src/Admin.php';
require_once __DIR__ . '/../../src/Mailer.php';
require_once __DIR__ . '/../../src/Templates.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        throw new \RuntimeException('Email is required');
    }

    // The login address can be overridden for testing independently of studio mail.
    $db = Db::sqlite(Config::databasePath());
    $mailer = new Mailer($db);
    $studioEmail = Admin::loginEmail($db);
    $adminName = $db->setting('admin_login_name', 'Randi');

    if (strtolower($email) !== strtolower($studioEmail)) {
        throw new \RuntimeException('That email is not authorized');
    }

    // Create a passwordless login token valid for 15 minutes.
    $client = $db->one('SELECT id FROM clients WHERE email = ?', [strtolower($studioEmail)]);
    if ($client === null) {
        $clientId = $db->insert(
            'INSERT INTO clients (email, name, created_at, unsub_token)
             VALUES (?, ?, ?, ?)',
            [$studioEmail, $adminName, Clock::nowSql(), Db::token()]
        );
    } else {
        $clientId = (int) $client['id'];
    }

    $token = Db::token();
    $tokenHash = hash('sha256', $token);
    $expiresAt = Clock::nowUtc()->modify('+15 minutes')->format(Clock::SQL);

    // Only the newest requested link should remain useful or be delivered.
    // Mark older queued login emails as handled and invalidate their tokens
    // before creating the replacement.
    $db->run(
        "UPDATE mail_queue
            SET sent_at = ?, last_error = 'skipped: superseded by newer login request'
          WHERE template = 'admin_login_token' AND sent_at IS NULL",
        [Clock::nowSql()]
    );
    $db->run(
        'UPDATE login_tokens SET used_at = ?
          WHERE client_id = ? AND used_at IS NULL',
        [Clock::nowSql(), $clientId]
    );

    $db->run(
        'INSERT INTO login_tokens (client_id, token_hash, expires_at)
         VALUES (?, ?, ?)',
        [$clientId, $tokenHash, $expiresAt]
    );

    $loginUrl = $db->setting('site_url', 'https://reboundbodystudio.com')
        . '/booking/public/admin/index.html?token=' . urlencode($token);

    // Direct insert rather than Mailer::queueAppointment() - this message has
    // no appointment to attach to, and expires in 15 minutes anyway, so the
    // usual dedupe/retry machinery would be dead weight.
    $db->run(
        'INSERT INTO mail_queue
            (to_email, to_name, subject, template, payload_json, send_after, kind)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        [
            $studioEmail, $adminName, 'Your studio login link', 'admin_login_token',
            json_encode(['login_url' => $loginUrl, 'client_name' => $adminName]), Clock::nowSql(), 'transactional',
        ]
    );

    echo json_encode(['message' => 'Check your email for a login link']);
    // Delivery is handled within one minute by the off-host authenticated
    // worker. GoDaddy's local PHP mail transport is not delivery-reliable.
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
