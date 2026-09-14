<?php
declare(strict_types=1);

namespace Rebound;

require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/Config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    $db = Db::sqlite(Config::databasePath());

    $rows = $db->all(
        'SELECT id, slug, name, duration_min, price_cents
           FROM services
          WHERE active = 1
       ORDER BY sort_order, duration_min'
    );

    header('Cache-Control: max-age=3600');
    echo json_encode($rows, JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
