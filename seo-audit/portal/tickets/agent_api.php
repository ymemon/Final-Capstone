<?php
/**
 * Agent-facing ticket JSON API - the "yasir reads the ticket, has Claude do
 * the work, replies once" side described 2026-09-12.
 *
 * Deploy as a sibling of owner-gate.php's index.php (html/reports/_owner/)
 * so it shares that gate's native PHP session (SESSION_KEY
 * 'azwc_portal_owner') - see session_lib.php's doc comment for why that
 * placement matters. There is deliberately no separate agent login here.
 *
 * Actions (?action=... on GET, {"action":...} in the JSON body on POST):
 *   GET  list                 -> all tickets, every client, newest first
 *   GET  ticket&id=N          -> one ticket incl. contact phone/email
 *   POST respond              -> {ticket_id, body} - agent reply, moves the
 *                                 ticket to waiting_on_client, texts the client
 *   POST status               -> {ticket_id, status} - housekeeping only,
 *                                 no message (e.g. closing a stale ticket)
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session_lib.php';

require_owner_session();

const VALID_STATUSES = ['open', 'in_progress', 'waiting_on_client', 'resolved', 'closed'];

$action = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (string) (json_body()['action'] ?? '')
    : (string) ($_GET['action'] ?? '');

function ticket_answers_for(int $ticketId): array {
    $stmt = db()->prepare('SELECT question_key, question_label, answer FROM ticket_answers WHERE ticket_id = ?');
    $stmt->execute([$ticketId]);
    return $stmt->fetchAll();
}

function ticket_messages_for(int $ticketId): array {
    $stmt = db()->prepare('SELECT sender_type, sender_label, channel, body, created_at FROM ticket_messages WHERE ticket_id = ? ORDER BY id ASC');
    $stmt->execute([$ticketId]);
    return $stmt->fetchAll();
}

switch ($action) {

    case 'list':
        $statusFilter = (string) ($_GET['status'] ?? '');
        $sql = 'SELECT t.*, c.name AS contact_name, c.email AS contact_email, c.phone_e164 AS contact_phone
                FROM tickets t JOIN contacts c ON c.id = t.contact_id';
        $params = [];
        if ($statusFilter !== '' && in_array($statusFilter, VALID_STATUSES, true)) {
            $sql .= ' WHERE t.status = ?';
            $params[] = $statusFilter;
        }
        $sql .= ' ORDER BY t.updated_at DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        json_response(['tickets' => $stmt->fetchAll()]);
        break;

    case 'ticket':
        $id = (int) ($_GET['id'] ?? 0);
        $stmt = db()->prepare(
            'SELECT t.*, c.name AS contact_name, c.email AS contact_email, c.phone_e164 AS contact_phone
             FROM tickets t JOIN contacts c ON c.id = t.contact_id WHERE t.id = ?'
        );
        $stmt->execute([$id]);
        $t = $stmt->fetch();
        if (!$t) {
            json_response(['error' => 'ticket not found'], 404);
        }
        json_response([
            'ticket' => $t,
            'answers' => ticket_answers_for($id),
            'messages' => ticket_messages_for($id),
        ]);
        break;

    case 'respond':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'POST required'], 405);
        }
        $body = json_body();
        $id = (int) ($body['ticket_id'] ?? 0);
        $text = trim((string) ($body['body'] ?? ''));
        if ($text === '') {
            json_response(['error' => 'empty response'], 422);
        }

        $pdo = db();
        $stmt = $pdo->prepare('SELECT * FROM tickets WHERE id = ?');
        $stmt->execute([$id]);
        $t = $stmt->fetch();
        if (!$t) {
            json_response(['error' => 'ticket not found'], 404);
        }

        $pdo->prepare(
            'INSERT INTO ticket_messages (ticket_id, sender_type, sender_label, channel, body) VALUES (?, ?, ?, ?, ?)'
        )->execute([$id, 'agent', $t['assigned_agent'], 'portal', $text]);

        // Responding to a client always hands the ball back to them - the
        // agent picks a terminal status (resolved/closed) separately via the
        // 'status' action once the client confirms, rather than this action
        // guessing that a reply means the work is finished.
        $pdo->prepare('UPDATE tickets SET status = ?, updated_at = ? WHERE id = ?')
            ->execute(['waiting_on_client', now_iso(), $id]);

        // Per policy, every ticket response texts the client - not just
        // email/portal - because "everyone is using phones" (2026-09-12).
        // Failure here must not fail the response itself.
        try {
            require_once __DIR__ . '/sms_notify.php';
            notify_client_ticket_response($id, $text);
        } catch (Throwable $e) {
            error_log('ticket SMS notify failed for ticket ' . $id . ': ' . $e->getMessage());
        }

        json_response(['ok' => true]);
        break;

    case 'status':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'POST required'], 405);
        }
        $body = json_body();
        $id = (int) ($body['ticket_id'] ?? 0);
        $status = (string) ($body['status'] ?? '');
        if (!in_array($status, VALID_STATUSES, true)) {
            json_response(['error' => 'invalid status'], 422);
        }
        $stmt = db()->prepare('UPDATE tickets SET status = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$status, now_iso(), $id]);
        if ($stmt->rowCount() === 0) {
            json_response(['error' => 'ticket not found'], 404);
        }
        json_response(['ok' => true]);
        break;

    case 'todos':
        $clientFilter = (string) ($_GET['client'] ?? '');
        $sql = 'SELECT * FROM todos';
        $params = [];
        if ($clientFilter !== '') {
            $sql .= ' WHERE client_slug = ?';
            $params[] = $clientFilter;
        }
        $sql .= " ORDER BY (status = 'done'), (due_date IS NULL), due_date ASC, created_at DESC";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        json_response(['todos' => $stmt->fetchAll()]);
        break;

    case 'todo_create':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'POST required'], 405);
        }
        $body = json_body();
        $title = trim((string) ($body['title'] ?? ''));
        $clientSlug = trim((string) ($body['client_slug'] ?? ''));
        if ($title === '' || $clientSlug === '') {
            json_response(['error' => 'title and client_slug are required'], 422);
        }
        db()->prepare(
            'INSERT INTO todos (client_slug, contact_id, ticket_id, title, detail, due_date, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $clientSlug,
            !empty($body['contact_id']) ? (int) $body['contact_id'] : null,
            !empty($body['ticket_id']) ? (int) $body['ticket_id'] : null,
            $title,
            (string) ($body['detail'] ?? ''),
            !empty($body['due_date']) ? (string) $body['due_date'] : null,
            'agent',
        ]);
        json_response(['id' => (int) db()->lastInsertId()], 201);
        break;

    case 'todo_update':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'POST required'], 405);
        }
        $body = json_body();
        $id = (int) ($body['id'] ?? 0);
        $fields = [];
        $params = [];
        foreach (['title', 'detail', 'due_date', 'status'] as $f) {
            if (array_key_exists($f, $body)) {
                $fields[] = "$f = ?";
                $params[] = $body[$f];
            }
        }
        if (!$fields) {
            json_response(['error' => 'nothing to update'], 422);
        }
        if (($body['status'] ?? null) === 'done') {
            $fields[] = 'completed_at = ?';
            $params[] = now_iso();
        }
        $params[] = $id;
        $stmt = db()->prepare('UPDATE todos SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $stmt->execute($params);
        if ($stmt->rowCount() === 0) {
            json_response(['error' => 'todo not found'], 404);
        }
        json_response(['ok' => true]);
        break;

    case 'todo_delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'POST required'], 405);
        }
        $id = (int) (json_body()['id'] ?? 0);
        $stmt = db()->prepare('DELETE FROM todos WHERE id = ?');
        $stmt->execute([$id]);
        json_response(['ok' => $stmt->rowCount() > 0]);
        break;

    default:
        json_response(['error' => 'unknown action'], 400);
}
