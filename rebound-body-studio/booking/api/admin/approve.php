<?php
declare(strict_types=1);

namespace Rebound;

require_once __DIR__ . '/../../src/Clock.php';
require_once __DIR__ . '/../../src/Db.php';
require_once __DIR__ . '/../../src/Config.php';
require_once __DIR__ . '/../../src/Admin.php';
require_once __DIR__ . '/../../src/Availability.php';
require_once __DIR__ . '/../../src/Booking.php';
require_once __DIR__ . '/../../src/Mailer.php';
require_once __DIR__ . '/../../src/Templates.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new \RuntimeException('POST required');
    }

    $adminToken = trim($_POST['admin_token'] ?? '');
    $apptId = (int) ($_POST['appointment_id'] ?? 0);
    $action = trim($_POST['action'] ?? ''); // approve, decline
    $studioNote = trim($_POST['studio_note'] ?? '');
    $declineReason = trim($_POST['decline_reason'] ?? '');

    if ($apptId === 0 || $action === '') {
        throw new \RuntimeException('appointment_id and action are required');
    }

    $db = Db::sqlite(Config::databasePath());
    Admin::requireToken($db, $adminToken);

    $avail = new Availability($db);
    $mailer = new Mailer($db);
    $booking = new Booking($db, $avail, $mailer);

    if ($action === 'approve') {
        $booking->approve($apptId, $studioNote);
        echo json_encode(['message' => 'Appointment approved']);
    } elseif ($action === 'decline') {
        $booking->decline($apptId, $declineReason);
        echo json_encode(['message' => 'Appointment declined']);
    } elseif ($action === 'complete') {
        $booking->markCompleted($apptId);
        echo json_encode(['message' => 'Appointment completed']);
    } elseif ($action === 'no_show') {
        $booking->markNoShow($apptId);
        echo json_encode(['message' => 'Appointment marked no-show']);
    } elseif ($action === 'cancel') {
        $booking->cancel($apptId, 'studio', $declineReason);
        echo json_encode(['message' => 'Appointment cancelled; any package session was returned']);
    } else {
        throw new \RuntimeException('Unknown action: ' . $action);
    }
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
