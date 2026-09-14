<?php
declare(strict_types=1);

namespace Rebound;

require_once __DIR__ . '/../src/Clock.php';
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Availability.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    $serviceId = (int) ($_GET['serviceId'] ?? 0);
    $daysAhead = (int) ($_GET['daysAhead'] ?? 21);
    $staffId = isset($_GET['staffId']) ? (int) $_GET['staffId'] : null;

    if ($serviceId === 0) {
        throw new \RuntimeException('serviceId is required');
    }

    $db = Db::sqlite(Config::databasePath());
    $avail = new Availability($db);

    $slots = $avail->slotsForService($serviceId, $daysAhead, $staffId);

    header('Cache-Control: max-age=300, must-revalidate');
    echo json_encode($slots, JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
