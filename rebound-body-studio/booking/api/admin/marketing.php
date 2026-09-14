<?php
declare(strict_types=1);
namespace Rebound;
foreach (['Clock', 'Db', 'Config', 'Admin', 'Templates', 'Marketing'] as $class) {
    require_once __DIR__ . '/../../src/' . $class . '.php';
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'POST required']);
        exit;
    }
    $db = Db::sqlite(Config::databasePath());
    Admin::requireToken($db, trim($_POST['admin_token'] ?? ''));
    $marketing = new Marketing($db);
    $action = $_POST['action'] ?? 'list';
    if ($action === 'preference') {
        if (!in_array($_POST['enabled'] ?? '', ['0', '1'], true)) {
            throw new \RuntimeException('Choose Yes or No for marketing emails.');
        }
        $enabled = $_POST['enabled'] === '1';
        if ($enabled && ($_POST['customer_requested'] ?? '') !== '1') {
            throw new \RuntimeException('Confirm that this customer asked to receive marketing emails.');
        }
        $marketing->setPreference((int) ($_POST['client_id'] ?? 0), $enabled);
        echo json_encode(['message' => 'Marketing preference saved. Appointment emails continue as usual.']);
    } elseif ($action === 'preview' || $action === 'send') {
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');
        echo json_encode($action === 'preview'
            ? $marketing->preview($subject, $body)
            : $marketing->queue(trim($_POST['request_key'] ?? ''), $subject, $body));
    } elseif ($action === 'list') {
        echo json_encode([
            'clients' => $db->all('SELECT id, name, email, marketing_consent, unsubscribed_at FROM clients
                WHERE lower(email) <> lower(?) ORDER BY name, email', [$db->setting('studio_email', '')]),
            'recipients' => count($marketing->audience()),
            'campaigns' => $db->all('SELECT id, subject, created_at, recipient_count FROM campaigns ORDER BY id DESC LIMIT 20'),
        ]);
    } else {
        throw new \RuntimeException('Unknown action.');
    }
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
