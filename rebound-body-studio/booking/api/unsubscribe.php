<?php
declare(strict_types=1);

namespace Rebound;

require_once __DIR__ . '/../src/Clock.php';
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Mailer.php';
require_once __DIR__ . '/../src/Templates.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
        http_response_code(405);
        exit('GET or POST required');
    }
    $token = trim($_GET['t'] ?? $_POST['t'] ?? '');
    if ($token === '') {
        throw new \RuntimeException('Unsubscribe token is required');
    }

    $db = Db::sqlite(Config::databasePath());
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($db->one('SELECT id FROM clients WHERE unsub_token = ?', [$token]) === null) {
            http_response_code(404);
            exit('Unsubscribe link not found.');
        }
        header('Content-Type: text/html; charset=utf-8');
        $safeToken = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Marketing email preferences — Rebound Body Studio</title><body style="font:18px/1.6 Georgia,serif;background:#f2f6f7;color:#0e1c28;padding:24px"><main style="max-width:620px;margin:auto;background:white;padding:28px;border-radius:14px"><h1>Stop marketing emails</h1><p>You will still receive appointment confirmations, reminders, cancellations, and other appointment messages.</p><form method="post"><input type="hidden" name="t" value="' . $safeToken . '"><button style="font:inherit;padding:12px 20px;background:#0a5474;color:white;border:0;border-radius:8px" type="submit">Unsubscribe from marketing</button></form></main></body></html>';
        exit;
    }
    $mailer = new Mailer($db);

    $ok = $mailer->unsubscribe($token);
    if ($ok) {
        echo "You have been unsubscribed from marketing emails. You will still receive appointment confirmations, reminders, cancellations, and other appointment messages.\n";
    } else {
        http_response_code(404);
        echo "Unsubscribe token not found.\n";
    }
} catch (\Throwable $e) {
    http_response_code(400);
    echo 'Error: ' . $e->getMessage() . "\n";
}
