<?php
/**
 * Twilio inbound-SMS webhook - the "reply goes directly to the assigned
 * agent" half of two-way texting (per yasir, 2026-09-12).
 *
 * Configure this file's deployed URL as the Twilio phone number's
 * "A MESSAGE COMES IN" webhook (POST). Twilio does not send any session
 * cookie, so authentication here is the request signature Twilio itself
 * signs every webhook with (verify_twilio_signature) - never skip that
 * check, or anyone who finds this URL can inject fake ticket messages.
 *
 * ROUTING A REPLY TO THE RIGHT TICKET
 * A single Twilio number serving every client has no built-in concept of
 * "which conversation is this reply part of" the way a 1:1 phone thread
 * does. This resolves it two ways, in order:
 *   1. The client's text contains "#<ticket id>" (notify_client_ticket_
 *      response already puts "ticket #123" in every outbound text, so
 *      quoting/replying near that text carries the number along on most
 *      phones' quote-reply UI).
 *   2. Otherwise, the contact's single most-recently-updated open ticket.
 * If a contact has more than one open ticket and doesn't mention a number,
 * this guesses wrong silently. That is an acceptable scaffold trade-off for
 * a single-agent, low-volume ticket queue; the real fix if that becomes a
 * problem is Twilio's Conversations API (a distinct address/proxy per
 * conversation), which is a bigger integration than this file.
 *
 * STARTING A NEW TICKET OVER SMS (added 2026-09-14)
 * A text that doesn't resolve to any ticket above - no "#<id>", and no open
 * ticket to fall back to - no longer dead-ends with "we don't see an open
 * ticket". Instead it starts the same Tier 2 intake wizard the portal's chat
 * UI drives, one question per text message, tracked per-contact in
 * sms_intake_state (see handle_intake_reply()). A client can also force a
 * fresh request even with an open ticket in flight by texting NEW. The
 * wizard reuses ticket_lib.php's create_ticket() - the exact function the
 * portal intake form calls - so an SMS-created ticket is indistinguishable
 * from a portal-created one once it exists.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/sms_notify.php';
require_once __DIR__ . '/categories.php';
require_once __DIR__ . '/ticket_lib.php';

function verify_twilio_signature(array $creds, string $url, array $params): bool {
    $signature = $_SERVER['HTTP_X_TWILIO_SIGNATURE'] ?? '';
    if ($signature === '') {
        return false;
    }
    // Twilio's documented algorithm: URL followed by every POST param
    // (key immediately followed by value, no separators), keys sorted,
    // HMAC-SHA1 with the auth token, base64-encoded.
    ksort($params);
    $data = $url;
    foreach ($params as $k => $v) {
        $data .= $k . $v;
    }
    $expected = base64_encode(hash_hmac('sha1', $data, $creds['auth_token'], true));
    return hash_equals($expected, $signature);
}

function webhook_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'azwebcorp.com';
    $path = strtok($_SERVER['REQUEST_URI'] ?? '/reports/sms_webhook.php', '?');
    return ($https ? 'https://' : 'http://') . $host . $path;
}

function twiml(string $message = ''): void {
    header('Content-Type: text/xml; charset=utf-8');
    if ($message === '') {
        echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
    } else {
        echo '<?xml version="1.0" encoding="UTF-8"?><Response><Message>'
            . htmlspecialchars($message, ENT_XML1) . '</Message></Response>';
    }
    exit;
}

// --- SMS ticket-intake wizard ------------------------------------------

function intake_category_keys(): array {
    return array_keys(ticket_categories());
}

function intake_category_menu(): string {
    $lines = ['What would you like help with? Reply with a number:'];
    $i = 1;
    foreach (ticket_categories() as $cat) {
        $lines[] = "{$i}. {$cat['label']}";
        $i++;
    }
    $lines[] = 'Reply CANCEL anytime to stop.';
    return implode("\n", $lines);
}

function get_intake_state(int $contactId): ?array {
    $stmt = db()->prepare('SELECT * FROM sms_intake_state WHERE contact_id = ?');
    $stmt->execute([$contactId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $row['answers'] = json_decode($row['answers_json'], true);
    if (!is_array($row['answers'])) {
        $row['answers'] = [];
    }
    return $row;
}

function save_intake_state(int $contactId, ?string $category, int $stepIndex, array $answers, string $triggerBody): void {
    db()->prepare(
        'INSERT INTO sms_intake_state (contact_id, category, step_index, answers_json, trigger_body, updated_at)
         VALUES (?, ?, ?, ?, ?, ?)
         ON CONFLICT(contact_id) DO UPDATE SET
           category = excluded.category, step_index = excluded.step_index,
           answers_json = excluded.answers_json, trigger_body = excluded.trigger_body,
           updated_at = excluded.updated_at'
    )->execute([$contactId, $category, $stepIndex, json_encode($answers), $triggerBody, now_iso()]);
}

function clear_intake_state(int $contactId): void {
    db()->prepare('DELETE FROM sms_intake_state WHERE contact_id = ?')->execute([$contactId]);
}

function start_new_intake(int $contactId, string $triggerBody): void {
    save_intake_state($contactId, null, 0, [], $triggerBody);
    twiml("Sure - let's get that started.\n\n" . intake_category_menu());
}

/** Renders one question for SMS - a select-type question gets a numbered
 *  list of options since a client can't tap a dropdown over text. */
function format_question_for_sms(array $q): string {
    $text = $q['label'];
    if (empty($q['required'])) {
        $text .= ' (optional - reply SKIP to leave blank)';
    }
    if (($q['type'] ?? '') === 'select' && !empty($q['options'])) {
        foreach ($q['options'] as $i => $opt) {
            $text .= "\n" . ($i + 1) . ") {$opt}";
        }
    }
    return $text;
}

/** Interprets a reply against one question. Returns the answer to store, or
 *  null if the reply doesn't satisfy the question (caller re-asks it). */
function resolve_answer_for_question(array $q, string $reply): ?string {
    $reply = trim($reply);
    if (strtoupper($reply) === 'SKIP') {
        return empty($q['required']) ? '' : null;
    }
    if (($q['type'] ?? '') === 'select' && !empty($q['options'])) {
        if (ctype_digit($reply)) {
            $idx = ((int) $reply) - 1;
            return $q['options'][$idx] ?? null;
        }
        foreach ($q['options'] as $opt) {
            if (strcasecmp($opt, $reply) === 0) {
                return $opt;
            }
        }
        return null;
    }
    if ($reply === '') {
        return empty($q['required']) ? '' : null;
    }
    return $reply;
}

/** Advances one step of an in-progress SMS ticket-creation wizard. Always
 *  replies via twiml() before returning, same as every other branch in this
 *  file - a mid-wizard reply is never treated as a ticket-number lookup. */
function handle_intake_reply(int $contactId, string $slug, array $intake, string $body): void {
    $bodyTrim = trim($body);

    if (strtoupper($bodyTrim) === 'CANCEL') {
        clear_intake_state($contactId);
        twiml('Okay, cancelled that request. Text anytime to start a new one, or reply to an existing ticket with its number (e.g. "#123: ...").');
    }

    // Step 0: category not chosen yet.
    if ($intake['category'] === null) {
        $keys = intake_category_keys();
        $chosen = null;
        if (ctype_digit($bodyTrim) && isset($keys[((int) $bodyTrim) - 1])) {
            $chosen = $keys[((int) $bodyTrim) - 1];
        } else {
            foreach (ticket_categories() as $key => $cat) {
                if (strcasecmp($cat['label'], $bodyTrim) === 0) {
                    $chosen = $key;
                    break;
                }
            }
        }
        if ($chosen === null) {
            twiml("Sorry, I didn't catch that.\n\n" . intake_category_menu());
        }
        $questions = ticket_category_questions($chosen);
        save_intake_state($contactId, $chosen, 0, [], $intake['trigger_body']);
        twiml(ticket_category($chosen)['label'] . " - got it.\n\n" . format_question_for_sms($questions[0]));
    }

    // Steps 1..N: answering the current category's questions in order.
    $questions = ticket_category_questions((string) $intake['category']);
    $stepIndex = (int) $intake['step_index'];
    $q = $questions[$stepIndex] ?? null;
    if ($q === null) {
        // Defensive only - step_index should never run past the question
        // list, but never leave a client stuck mid-conversation if it does.
        clear_intake_state($contactId);
        twiml('Something went wrong on our end - please try again, or call AZ Web Corp at (480) 818-5761.');
    }

    $answer = resolve_answer_for_question($q, $bodyTrim);
    if ($answer === null) {
        twiml("That doesn't quite work for this question.\n\n" . format_question_for_sms($q));
    }

    $answers = $intake['answers'];
    $answers[$q['key']] = $answer;
    $stepIndex++;

    if ($stepIndex < count($questions)) {
        save_intake_state($contactId, (string) $intake['category'], $stepIndex, $answers, $intake['trigger_body']);
        twiml(format_question_for_sms($questions[$stepIndex]));
    }

    // All questions answered - create the ticket the same way the portal's
    // intake form does.
    $missing = missing_ticket_answers((string) $intake['category'], $answers);
    if ($missing) {
        // Shouldn't happen - every required question was enforced on its own
        // turn above - but never call create_ticket() with gaps in it.
        clear_intake_state($contactId);
        twiml('Something went wrong collecting your answers - please try again, or call AZ Web Corp at (480) 818-5761.');
    }

    $ticketId = create_ticket($slug, $contactId, (string) $intake['category'], $answers, 'sms');
    clear_intake_state($contactId);

    if ($intake['trigger_body'] !== '') {
        db()->prepare(
            'INSERT INTO ticket_messages (ticket_id, sender_type, sender_label, channel, body) VALUES (?, ?, ?, ?, ?)'
        )->execute([$ticketId, 'client', 'via SMS', 'sms', $intake['trigger_body']]);
    }

    twiml("Got it - opened ticket #{$ticketId}. Your agent will follow up here.");
}

$creds = twilio_creds();
if ($creds === null) {
    http_response_code(503);
    exit('SMS not configured');
}

if (!verify_twilio_signature($creds, webhook_url(), $_POST)) {
    http_response_code(403);
    exit('bad signature');
}

$from = to_e164((string) ($_POST['From'] ?? ''));
$body = trim((string) ($_POST['Body'] ?? ''));
$sid  = (string) ($_POST['MessageSid'] ?? '');

if ($from === null || $body === '') {
    twiml(); // nothing sane to log; ack silently rather than error to Twilio
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, client_slug FROM contacts WHERE phone_e164 = ?');
$stmt->execute([$from]);
$contact = $stmt->fetch();

if (!$contact) {
    // A text from a number no contact record has on file. Log it as
    // unmatched rather than silently dropping it - someone still needs to
    // notice and add the number to the right contact.
    $pdo->prepare(
        'INSERT INTO sms_log (direction, twilio_sid, from_number, body, status) VALUES (?, ?, ?, ?, ?)'
    )->execute(['in', $sid, $from, $body, 'unmatched']);
    twiml("Thanks for the message. We couldn't match this number to an account - please call AZ Web Corp at (480) 818-5761.");
}

$contactId = (int) $contact['id'];
$slug = (string) $contact['client_slug'];
$bodyUpper = strtoupper($body);

// A reply while a client is mid-wizard is always an answer to the current
// question, never a ticket-number lookup - check this before anything else.
$intake = get_intake_state($contactId);
if ($intake !== null) {
    $pdo->prepare(
        'INSERT INTO sms_log (direction, contact_id, twilio_sid, from_number, body, status) VALUES (?, ?, ?, ?, ?, ?)'
    )->execute(['in', $contactId, $sid, $from, $body, 'received']);
    handle_intake_reply($contactId, $slug, $intake, $body);
}

// 1. explicit "#123" reference in the text
$ticketId = null;
if (preg_match('/#(\d+)/', $body, $m)) {
    $chk = $pdo->prepare('SELECT id FROM tickets WHERE id = ? AND contact_id = ?');
    $chk->execute([(int) $m[1], $contactId]);
    $found = $chk->fetchColumn();
    if ($found !== false) {
        $ticketId = (int) $found;
    }
}

// 2. fall back to the contact's most recently updated open ticket - skipped
// when the client explicitly types NEW, so that always starts a fresh
// request even if one is already open.
if ($ticketId === null && $bodyUpper !== 'NEW') {
    $chk = $pdo->prepare(
        "SELECT id FROM tickets WHERE contact_id = ? AND status IN ('open','in_progress','waiting_on_client')
         ORDER BY updated_at DESC LIMIT 1"
    );
    $chk->execute([$contactId]);
    $found = $chk->fetchColumn();
    if ($found !== false) {
        $ticketId = (int) $found;
    }
}

$pdo->prepare(
    'INSERT INTO sms_log (direction, contact_id, ticket_id, twilio_sid, from_number, body, status) VALUES (?, ?, ?, ?, ?, ?, ?)'
)->execute(['in', $contactId, $ticketId, $sid, $from, $body, $ticketId ? 'received' : 'unmatched']);

// 3. no "#<id>" and no open ticket (or NEW was explicit) - start a new
// ticket over SMS instead of dead-ending here.
if ($ticketId === null) {
    start_new_intake($contactId, $bodyUpper === 'NEW' ? '' : $body);
}

$pdo->prepare(
    'INSERT INTO ticket_messages (ticket_id, sender_type, sender_label, channel, body) VALUES (?, ?, ?, ?, ?)'
)->execute([$ticketId, 'client', $from, 'sms', $body]);

$t = $pdo->prepare('SELECT status FROM tickets WHERE id = ?');
$t->execute([$ticketId]);
$status = $t->fetchColumn();
$newStatus = $status === 'waiting_on_client' ? 'in_progress' : $status;
$pdo->prepare('UPDATE tickets SET status = ?, updated_at = ? WHERE id = ?')
    ->execute([$newStatus, now_iso(), $ticketId]);

twiml("Got it - added to ticket #{$ticketId}. Your agent will follow up here.");
