<?php
declare(strict_types=1);

namespace Rebound;

require_once __DIR__ . '/../src/Clock.php';
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Availability.php';
require_once __DIR__ . '/../src/Booking.php';
require_once __DIR__ . '/../src/Mailer.php';
require_once __DIR__ . '/../src/Templates.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $token = trim($_POST['token'] ?? '');
    if ($token === '') {
        throw new \RuntimeException('token is required');
    }

    $db = Db::sqlite(Config::databasePath());
    $avail = new Availability($db);
    $mailer = new Mailer($db);
    $booking = new Booking($db, $avail, $mailer);

    $appt = $booking->byManageToken($token);
    if ($appt === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Appointment not found']);
        exit;
    }

    // Token-protected reads use POST in the browser so GoDaddy's forced edge
    // cache can never store private appointment details.
    if (($_POST['action'] ?? '') === 'view') {
        echo json_encode([
            'id' => $appt['id'],
            'service_name' => $appt['service_name'],
            'starts_at' => $appt['starts_at'],
            'ends_at' => $appt['ends_at'],
            'duration_min' => $appt['duration_min'],
            'price_cents' => $appt['price_cents'],
            'status' => $appt['status'],
            'client_note' => $appt['client_note'],
            'studio_note' => $appt['studio_note'],
            'created_at' => $appt['created_at'],
            'when_human' => Clock::human($appt['starts_at']),
            'price_usd' => $appt['package_purchase_id'] ? '1 prepaid package session'
                : '$' . number_format((int) $appt['price_cents'] / 100, 2),
            'can_cancel' => in_array($appt['status'], ['requested', 'confirmed'], true),
        ]);
        exit;
    }

    // POST: cancel the appointment
    $action = trim($_POST['action'] ?? '');
    if ($action !== 'cancel') {
        throw new \RuntimeException('Unknown action: ' . $action);
    }
    $reason = trim($_POST['reason'] ?? '');
    $booking->cancel((int) $appt['id'], 'client', $reason);
    echo json_encode(['message' => 'Appointment cancelled']);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
