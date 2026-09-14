<?php
/**
 * Client-facing ticket JSON API.
 *
 * Deploy as a sibling of client-gate.php's target (html/reports/tickets_api.php)
 * so it sits under the same cookie path ('/reports/') that GATE_COOKIE uses.
 * Every action here requires a valid client-gate.php session - there is no
 * separate login, by design, since the reporting dashboard and the ticket
 * system are the same client relationship.
 *
 * Actions (?action=... on GET, {"action":...} in the JSON body on POST):
 *   GET  categories        -> the Tier 2 taxonomy, for building the intake form
 *   GET  list               -> this client's tickets, newest first
 *   GET  ticket&id=N        -> one ticket's answers + message thread
 *   POST create             -> {category, answers: {question_key: value}}
 *   POST reply              -> {ticket_id, body}
 *   POST tier1              -> {action_key, payload} - passthrough to tier1_actions.php
 *
 * A Tier 1 action (see tier1_actions.php) never appears in this file's ticket
 * tables at all - it is not "a ticket that auto-resolves", it is logged
 * separately and executes immediately. Only a Tier 2 category submission
 * creates a row in `tickets`.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session_lib.php';
require_once __DIR__ . '/categories.php';
require_once __DIR__ . '/ticket_lib.php';

$session = require_client_session();
$slug    = $session['slug'];
$email   = $session['email'];

$action = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (string) (json_body()['action'] ?? '')
    : (string) ($_GET['action'] ?? '');

/** Every contact in this system belongs to exactly one client (client_slug),
 *  matched by the email the magic-link session carries - the same identity
 *  client-gate.php already verified, never trusted from a request field. */
function find_or_create_contact(string $slug, string $email): int {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM contacts WHERE client_slug = ? AND email = ? COLLATE NOCASE');
    $stmt->execute([$slug, $email]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }
    $ins = $pdo->prepare('INSERT INTO contacts (client_slug, email) VALUES (?, ?)');
    $ins->execute([$slug, $email]);
    return (int) $pdo->lastInsertId();
}

/** Confirms a ticket id belongs to the signed-in client before any read or
 *  write touches it - the same "never trust the id alone" rule the gate
 *  itself follows for slugs. */
function load_owned_ticket(string $slug, int $id): array {
    $stmt = db()->prepare('SELECT * FROM tickets WHERE id = ? AND client_slug = ?');
    $stmt->execute([$id, $slug]);
    $t = $stmt->fetch();
    if (!$t) {
        json_response(['error' => 'ticket not found'], 404);
    }
    return $t;
}

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

    case 'categories':
        $out = [];
        foreach (ticket_categories() as $key => $cat) {
            $out[$key] = [
                'label' => $cat['label'],
                'description' => $cat['description'],
                'questions' => array_merge($cat['questions'], closing_questions()),
            ];
        }
        json_response(['categories' => $out]);
        break;

    case 'list':
        $contactId = find_or_create_contact($slug, $email);
        $stmt = db()->prepare(
            'SELECT id, category, status, summary, deadline, created_at, updated_at
             FROM tickets WHERE client_slug = ? AND contact_id = ? ORDER BY updated_at DESC'
        );
        $stmt->execute([$slug, $contactId]);
        json_response(['tickets' => $stmt->fetchAll()]);
        break;

    case 'ticket':
        $id = (int) ($_GET['id'] ?? 0);
        $t = load_owned_ticket($slug, $id);
        json_response([
            'ticket' => $t,
            'answers' => ticket_answers_for($id),
            'messages' => ticket_messages_for($id),
        ]);
        break;

    case 'create':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'POST required'], 405);
        }
        $body = json_body();
        $categoryKey = (string) ($body['category'] ?? '');
        $answers = is_array($body['answers'] ?? null) ? $body['answers'] : [];

        $category = ticket_category($categoryKey);
        if ($category === null) {
            json_response(['error' => 'unknown category'], 400);
        }

        $missing = missing_ticket_answers($categoryKey, $answers);
        if ($missing) {
            json_response(['error' => 'missing required answers', 'fields' => $missing], 422);
        }

        $contactId = find_or_create_contact($slug, $email);
        $ticketId = create_ticket($slug, $contactId, $categoryKey, $answers, 'portal');

        json_response(['ticket_id' => $ticketId], 201);
        break;

    case 'reply':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'POST required'], 405);
        }
        $body = json_body();
        $id = (int) ($body['ticket_id'] ?? 0);
        $text = trim((string) ($body['body'] ?? ''));
        if ($text === '') {
            json_response(['error' => 'empty reply'], 422);
        }
        $t = load_owned_ticket($slug, $id);

        $pdo = db();
        $pdo->prepare(
            'INSERT INTO ticket_messages (ticket_id, sender_type, sender_label, channel, body) VALUES (?, ?, ?, ?, ?)'
        )->execute([$id, 'client', $session['name'] ?? $email, 'portal', $text]);

        $newStatus = $t['status'] === 'waiting_on_client' ? 'in_progress' : $t['status'];
        $pdo->prepare('UPDATE tickets SET status = ?, updated_at = ? WHERE id = ?')
            ->execute([$newStatus, now_iso(), $id]);

        json_response(['ok' => true]);
        break;

    case 'todos':
        $contactId = find_or_create_contact($slug, $email);
        $stmt = db()->prepare(
            "SELECT * FROM todos WHERE client_slug = ? AND (contact_id IS NULL OR contact_id = ?)
             ORDER BY (status = 'done'), (due_date IS NULL), due_date ASC, created_at DESC"
        );
        $stmt->execute([$slug, $contactId]);
        json_response(['todos' => $stmt->fetchAll()]);
        break;

    case 'todos_due_today':
        $contactId = find_or_create_contact($slug, $email);
        $stmt = db()->prepare(
            "SELECT * FROM todos WHERE client_slug = ? AND (contact_id IS NULL OR contact_id = ?)
             AND status = 'open' AND due_date = ? ORDER BY created_at ASC"
        );
        $stmt->execute([$slug, $contactId, gmdate('Y-m-d')]);
        json_response(['todos' => $stmt->fetchAll()]);
        break;

    case 'todo_complete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'POST required'], 405);
        }
        $id = (int) (json_body()['id'] ?? 0);
        // A client can only complete their own client's todos - never trust
        // the id alone, same rule load_owned_ticket follows for tickets.
        $stmt = db()->prepare("UPDATE todos SET status = 'done', completed_at = ? WHERE id = ? AND client_slug = ?");
        $stmt->execute([now_iso(), $id, $slug]);
        if ($stmt->rowCount() === 0) {
            json_response(['error' => 'todo not found'], 404);
        }
        json_response(['ok' => true]);
        break;

    case 'tier1':
        $body = json_body();
        $contactId = find_or_create_contact($slug, $email);
        require_once __DIR__ . '/tier1_actions.php';
        json_response(dispatch_tier1_action($slug, $contactId, (string) ($body['action_key'] ?? ''), $body['payload'] ?? []));
        break;

    default:
        json_response(['error' => 'unknown action'], 400);
}
