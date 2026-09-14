<?php
declare(strict_types=1);

namespace Rebound;

// This worker is for SSH/CLI recovery only. The public scheduler must use the
// token-protected drain-http.php endpoint.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Cron worker: drain the mail queue.
 *
 * Run on the 5-minute mark via cron (see DEPLOYMENT.md for the exact line):
 *   php /path/to/cron/drain-mail.php >> /var/log/booking-mail.log 2>&1
 *
 * Pulls up to 50 messages from the queue, sends them (or skips if consent
 * is missing), and records the result. Retries up to 5 times on failure.
 */

require_once __DIR__ . '/../src/Clock.php';
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Mailer.php';
require_once __DIR__ . '/../src/Templates.php';

$dbPath = Config::databasePath();
if (!is_file($dbPath)) {
    die("Database not found: {$dbPath}\n");
}

$db = Db::sqlite($dbPath);
$mailer = new Mailer($db);

$start = time();
echo '[' . date('Y-m-d H:i:s') . '] Draining mail queue...' . "\n";

$result = $mailer->drain(50);

echo '  Sent: ' . $result['sent'] . "\n";
echo '  Failed: ' . $result['failed'] . "\n";
echo '  Skipped (no consent): ' . $result['skipped'] . "\n";
echo '  Took ' . (time() - $start) . 's' . "\n";
