<?php
/**
 * Ticket-creation logic shared by every intake surface. Originally lived
 * inline in ticket_api.php's 'create' case; pulled out 2026-09-14 so the SMS
 * intake wizard (sms_webhook.php) creates tickets the exact same way the
 * portal's intake form does, rather than a second copy of this logic
 * silently drifting out of sync with it.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/categories.php';

/** Required-question keys with no answer yet, for a category. Empty = ready
 *  to create the ticket. */
function missing_ticket_answers(string $categoryKey, array $answers): array {
    $missing = [];
    foreach (ticket_category_questions($categoryKey) as $q) {
        if (!empty($q['required']) && trim((string) ($answers[$q['key']] ?? '')) === '') {
            $missing[] = $q['key'];
        }
    }
    return $missing;
}

/** A short, human-scannable title built from the category label and the
 *  first substantial answer, so a ticket list is readable without opening
 *  each row - e.g. "Content Update: update the homepage hero text". */
function build_ticket_summary(array $category, array $answers): string {
    $first = '';
    foreach ($category['questions'] as $q) {
        $v = trim((string) ($answers[$q['key']] ?? ''));
        if ($v !== '') {
            $first = $v;
            break;
        }
    }
    $first = preg_replace('/\s+/', ' ', $first) ?? '';
    if (strlen($first) > 70) {
        $first = substr($first, 0, 67) . '...';
    }
    return $first === '' ? $category['label'] : $category['label'] . ': ' . $first;
}

/** Creates a ticket + its answers + the opening system message, in one
 *  transaction, then fires the new-ticket agent notification. Caller must
 *  have already confirmed missing_ticket_answers() is empty - this function
 *  does not re-validate, so a caller that skips that check can create a
 *  ticket with blank required answers. $channel is just a label ('portal' |
 *  'sms') recorded on the opening system message. */
function create_ticket(string $slug, int $contactId, string $categoryKey, array $answers, string $channel = 'portal'): int {
    $category = ticket_category($categoryKey);
    if ($category === null) {
        throw new InvalidArgumentException('unknown category: ' . $categoryKey);
    }
    $questions = ticket_category_questions($categoryKey);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $publishPref = strtolower((string) ($answers['publish_preference'] ?? 'Review first')) === 'publish automatically'
            ? 'auto' : 'review';

        $ins = $pdo->prepare(
            'INSERT INTO tickets (client_slug, contact_id, category, deadline, publish_preference, summary)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $slug,
            $contactId,
            $categoryKey,
            trim((string) ($answers['deadline'] ?? '')) ?: null,
            $publishPref,
            build_ticket_summary($category, $answers),
        ]);
        $ticketId = (int) $pdo->lastInsertId();

        $ansIns = $pdo->prepare(
            'INSERT INTO ticket_answers (ticket_id, question_key, question_label, answer) VALUES (?, ?, ?, ?)'
        );
        foreach ($questions as $q) {
            $ansIns->execute([$ticketId, $q['key'], $q['label'], (string) ($answers[$q['key']] ?? '')]);
        }

        $openingBody = $channel === 'sms' ? 'Ticket created via SMS intake.' : 'Ticket created via intake form.';
        $pdo->prepare(
            'INSERT INTO ticket_messages (ticket_id, sender_type, sender_label, channel, body) VALUES (?, ?, ?, ?, ?)'
        )->execute([$ticketId, 'system', $channel === 'sms' ? 'SMS' : 'Portal', $channel, $openingBody]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    // Fire-and-forget: the agent should never have to be watching the
    // dashboard to notice a new ticket. Failure here must not fail ticket
    // creation, so it is wrapped rather than left to bubble up.
    try {
        require_once __DIR__ . '/sms_notify.php';
        notify_agent_new_ticket($ticketId);
    } catch (Throwable $e) {
        // Logged by notify_agent_new_ticket itself where possible.
    }

    return $ticketId;
}
