<?php
declare(strict_types=1);

namespace Rebound;

require_once __DIR__ . '/../../src/Clock.php';
require_once __DIR__ . '/../../src/Db.php';
require_once __DIR__ . '/../../src/Config.php';
require_once __DIR__ . '/../../src/Admin.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    $adminToken = trim($_POST['admin_token'] ?? '');

    $db = Db::sqlite(Config::databasePath());

    $db = Db::sqlite(Config::databasePath());
    Admin::requireToken($db, $adminToken);

    // Fetch all pending requests, ordered by request time.
    $rows = $db->all(
        'SELECT a.id, a.created_at, a.client_note, a.technique_pref,
                a.starts_at, a.manage_token,
                s.name AS service_name, s.duration_min,
                c.name AS client_name, c.email AS client_email, c.phone AS client_phone
           FROM appointments a
           JOIN services s ON s.id = a.service_id
           JOIN clients  c ON c.id = a.client_id
          WHERE a.status = ?
       ORDER BY a.created_at DESC',
        ['requested']
    );

    $requests = [];
    foreach ($rows as $r) {
        $requests[] = [
            'id' => (int) $r['id'],
            'client_name' => $r['client_name'],
            'client_email' => $r['client_email'],
            'client_phone' => $r['client_phone'],
            'service_name' => $r['service_name'],
            'duration_min' => (int) $r['duration_min'],
            'when' => Clock::human($r['starts_at']),
            'when_sql' => $r['starts_at'],
            'requested_at' => Clock::human($r['created_at']),
            'client_note' => $r['client_note'],
            'technique_pref' => $r['technique_pref'],
            'manage_token' => $r['manage_token'],
        ];
    }

    echo json_encode(['requests' => $requests]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
