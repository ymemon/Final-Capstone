<?php
declare(strict_types=1);

namespace Rebound;

/**
 * Secure handoff API for the off-host authenticated SMTP worker.
 *
 * This host (GoDaddy Managed WordPress) exposes no shell crontab and no
 * control-panel cron for this plan - confirmed 2026-09-10, `crontab -l`
 * returns "command not found" over SSH. Rather than leave the mail queue
 * depending on someone remembering to SSH in and drain it by hand (which is
 * exactly how Randi's login link sat unsent for hours), this is called
 * every minute by a non-interactive Windows Scheduled Task on the office
 * machine. The worker leases rendered messages, sends them through the
 * authenticated AZWebCorp mailbox, then acknowledges success or failure.
 *
 * Guarded by a token rather than left open, since an unauthenticated GET
 * that drains a mail queue is a standing invitation to be hit by anyone who
 * finds the URL.
 */

require_once __DIR__ . '/../src/Clock.php';
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Mailer.php';
require_once __DIR__ . '/../src/Templates.php';

header('Content-Type: application/json; charset=utf-8');
// This endpoint performs work on every authorized request. GoDaddy's edge
// cache otherwise treats the GET response as a normal page and can replay an
// old result without executing PHP, leaving newly queued mail untouched.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Robots-Tag: noindex, nofollow', true);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$dbPath = Config::databasePath();
if (!is_file($dbPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'database not found']);
    exit;
}

$db = Db::sqlite($dbPath);
$expected = $db->setting('cron_secret', '');

$provided = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
if ($expected === '' || !hash_equals($expected, $provided)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$mailer = new Mailer($db);
$action = (string) ($_POST['action'] ?? '');

try {
    if ($action === 'status') {
        $pending = (int) $db->value(
            'SELECT COUNT(*) FROM mail_queue WHERE sent_at IS NULL AND attempts < 5'
        );
        echo json_encode(['ok' => true, 'at' => Clock::nowSql(), 'pending' => $pending]);
        exit;
    }

    if ($action === 'claim') {
        $claimToken = (string) ($_POST['claim_token'] ?? '');
        $result = $mailer->claimForExternalDelivery($claimToken, 50);
        echo json_encode(['ok' => true, 'at' => Clock::nowSql(), 'result' => $result]);
        exit;
    }

    if ($action === 'verify') {
        $eligible = $mailer->externalDeliveryEligible((int) ($_POST['queue_id'] ?? 0), (string) ($_POST['claim_token'] ?? ''));
        echo json_encode(['ok' => true, 'eligible' => $eligible]);
        exit;
    }

    if ($action === 'ack') {
        $queueId = (int) ($_POST['queue_id'] ?? 0);
        $claimToken = (string) ($_POST['claim_token'] ?? '');
        $sent = (string) ($_POST['result'] ?? '') === 'sent';
        $error = (string) ($_POST['error'] ?? '');
        $updated = $mailer->finishExternalDelivery($queueId, $claimToken, $sent, $error);
        echo json_encode(['ok' => $updated, 'at' => Clock::nowSql()]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'invalid action']);
} catch (\Throwable $error) {
    http_response_code(500);
    echo json_encode(['error' => substr($error->getMessage(), 0, 300)]);
}
