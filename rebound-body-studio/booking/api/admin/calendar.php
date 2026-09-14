<?php
declare(strict_types=1);
namespace Rebound;
foreach (['Clock', 'Db', 'Config', 'Admin'] as $class) {
    require_once __DIR__ . '/../../src/' . $class . '.php';
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'POST required']);
        exit;
    }
    $db = Db::sqlite(Config::databasePath());
    Admin::requireToken($db, trim($_POST['admin_token'] ?? ''));
    $month = $_POST['month'] ?? Clock::toStudio(Clock::nowUtc())->format('Y-m');
    if (!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/', $month)) {
        throw new \RuntimeException('Choose a valid calendar month.');
    }
    $start = Clock::fromStudioLocal($month . '-01', '00:00');
    $end = $start->modify('+1 month');
    $rows = $db->all(
        'SELECT a.id, a.package_purchase_id, a.starts_at, a.ends_at, a.status, s.name AS service_name,
         s.duration_min, c.name AS client_name, c.email AS client_email, c.phone AS client_phone
         FROM appointments a JOIN services s ON s.id = a.service_id JOIN clients c ON c.id = a.client_id
         WHERE a.starts_at >= ? AND a.starts_at < ? ORDER BY a.starts_at',
        [$start->format(Clock::SQL), $end->format(Clock::SQL)]
    );
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['when'] = Clock::human($row['starts_at']);
        $row['date'] = Clock::studioYmd($row['starts_at']);
        $row['time'] = Clock::humanTime($row['starts_at']);
        $row['end_time'] = Clock::humanTime($row['ends_at']);
    }
    unset($row);
    echo json_encode(['appointments' => $rows, 'month' => $month, 'timezone' => Clock::STUDIO_TZ]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
