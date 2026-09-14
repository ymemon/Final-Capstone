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
        throw new \RuntimeException('POST required');
    }

    $payload = json_decode(file_get_contents('php://input'), true) ?? [];

    $email = trim($payload['email'] ?? '');
    $name = trim($payload['name'] ?? '');
    $phone = trim($payload['phone'] ?? '');
    $serviceId = (int) ($payload['serviceId'] ?? 0);
    $startSql = trim($payload['startSql'] ?? '');
    $clientNote = trim($payload['clientNote'] ?? '');
    $techniquePref = trim($payload['techniquePref'] ?? '');
    // New forms explicitly send an opt-out flag. Retain old forms' preference semantics.
    $marketingOptIn = array_key_exists('marketingOptOut', $payload)
        ? !filter_var($payload['marketingOptOut'], FILTER_VALIDATE_BOOLEAN)
        : (array_key_exists('marketingOptIn', $payload)
            ? filter_var($payload['marketingOptIn'], FILTER_VALIDATE_BOOLEAN) : true);
    $packagePurchaseId = isset($payload['packagePurchaseId'])
        ? (int) $payload['packagePurchaseId']
        : null;

    if ($serviceId === 0 || $startSql === '') {
        throw new \RuntimeException('serviceId and startSql are required');
    }

    $db = Db::sqlite(Config::databasePath());
    $avail = new Availability($db);
    $mailer = new Mailer($db);
    $booking = new Booking($db, $avail, $mailer);

    $result = $booking->request(
        $email,
        $name,
        $phone,
        $serviceId,
        $startSql,
        $clientNote,
        $techniquePref,
        $marketingOptIn,
        $packagePurchaseId,
        (string) ($payload['packageToken'] ?? '')
    );

    http_response_code(201);
    echo json_encode([
        'id' => $result['id'],
        'manage_token' => $result['manage_token'],
        'message' => 'Request received. Check your email for confirmation.',
    ]);

    // Delivery is handled within one minute by the off-host authenticated
    // worker. GoDaddy's local PHP mail transport is not delivery-reliable.
} catch (SlotTakenException $e) {
    http_response_code(409);
    echo json_encode(['error' => $e->getMessage()]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
