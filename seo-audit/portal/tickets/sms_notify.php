<?php
/**
 * Outbound SMS (Twilio) plus the credential store it reads.
 *
 * Credentials live in state_lib.php's storage as a `twilio_creds` state
 * entry - see that file's doc comment for why this lives inside the webroot
 * rather than $HOME (a 2026-09-13 live test proved $HOME-based storage,
 * this file's original approach, never actually worked from a real web
 * request on this host).
 *
 * This file is deliberately never called with real credentials missing: every
 * function here degrades to logging and returning false rather than throwing,
 * because a notification failure must never be allowed to fail the ticket
 * action that triggered it (ticket_api.php and agent_api.php both call these
 * inside try/catch for exactly this reason, but the functions are written to
 * not need that safety net in the first place).
 *
 * Expected shape of the 'twilio_creds' state entry:
 *   [
 *     'account_sid' => 'ACxxxxxxxx...',
 *     'auth_token'  => 'xxxxxxxx...',
 *     'from_number' => '+14805551234',
 *     'agent_phone' => '+14805559999',   // yasir's own phone, for new-ticket alerts
 *   ]
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/state_lib.php';

function twilio_creds(): ?array {
    $c = state_read('twilio_creds', null);
    if (!is_array($c) || empty($c['account_sid']) || empty($c['auth_token']) || empty($c['from_number'])) {
        error_log('twilio_creds state entry missing or incomplete - SMS is not configured yet');
        return null;
    }
    return $c;
}

/** Very small US-centric normalizer - good enough for the client rosters this
 *  system serves today, not a substitute for a real phone-parsing library if
 *  international numbers ever show up. */
function to_e164(string $raw): ?string {
    $digits = preg_replace('/\D/', '', $raw) ?? '';
    if ($raw !== '' && $raw[0] === '+') {
        return '+' . $digits;
    }
    if (strlen($digits) === 10) {
        return '+1' . $digits;
    }
    if (strlen($digits) === 11 && $digits[0] === '1') {
        return '+' . $digits;
    }
    return null;
}

/** Sends one SMS via Twilio's REST API and logs it either way. Returns the
 *  Twilio message SID on success, or null on failure/no-config. */
function send_sms(string $toE164, string $body, ?int $ticketId = null, ?int $contactId = null): ?string {
    $creds = twilio_creds();
    if ($creds === null) {
        db()->prepare(
            'INSERT INTO sms_log (direction, contact_id, ticket_id, to_number, body, status) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute(['out', $contactId, $ticketId, $toE164, $body, 'unconfigured']);
        return null;
    }

    $url = "https://api.twilio.com/2010-04-01/Accounts/{$creds['account_sid']}/Messages.json";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_USERPWD => $creds['account_sid'] . ':' . $creds['auth_token'],
        CURLOPT_POSTFIELDS => http_build_query([
            'From' => $creds['from_number'],
            'To'   => $toE164,
            'Body' => $body,
        ]),
        CURLOPT_TIMEOUT => 10,
    ]);
    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $resp = is_string($raw) ? json_decode($raw, true) : null;
    $sid = is_array($resp) ? ($resp['sid'] ?? null) : null;
    $status = ($httpCode >= 200 && $httpCode < 300) ? 'sent' : 'failed';

    db()->prepare(
        'INSERT INTO sms_log (direction, contact_id, ticket_id, twilio_sid, from_number, to_number, body, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute(['out', $contactId, $ticketId, $sid, $creds['from_number'], $toE164, $body, $status]);

    if ($status === 'failed') {
        error_log('Twilio send failed (' . $httpCode . '): ' . $raw);
        return null;
    }
    return $sid;
}

/** Called after agent_api.php's 'respond' action. Every ticket response
 *  texts the client, per the all-contacts / two-way policy - not just
 *  contacts who opted into some notification preference. */
function notify_client_ticket_response(int $ticketId, string $responseBody): void {
    $stmt = db()->prepare(
        'SELECT t.id, c.id AS contact_id, c.phone_e164, c.sms_opt_in
         FROM tickets t JOIN contacts c ON c.id = t.contact_id WHERE t.id = ?'
    );
    $stmt->execute([$ticketId]);
    $row = $stmt->fetch();
    if (!$row || !$row['sms_opt_in'] || empty($row['phone_e164'])) {
        return; // no phone on file yet - nothing to text
    }

    $snippet = mb_strlen($responseBody) > 300 ? mb_substr($responseBody, 0, 297) . '...' : $responseBody;
    $text = "AZ Web Corp - update on ticket #{$ticketId}:\n\n{$snippet}\n\n"
          . "Reply to this text and it goes straight to your agent.";
    send_sms($row['phone_e164'], $text, $ticketId, (int) $row['contact_id']);
}

/** Called right after a client creates a Tier 2 ticket, so the agent does not
 *  have to be watching the dashboard to notice new work. Texts agent_phone
 *  if configured, and always tries an email as a second channel, mirroring
 *  the existing weekly-report send path (wp_mail via wp-load.php) rather
 *  than inventing a third mail mechanism. */
function notify_agent_new_ticket(int $ticketId): void {
    $stmt = db()->prepare(
        'SELECT t.*, c.name AS contact_name, c.email AS contact_email
         FROM tickets t JOIN contacts c ON c.id = t.contact_id WHERE t.id = ?'
    );
    $stmt->execute([$ticketId]);
    $t = $stmt->fetch();
    if (!$t) {
        return;
    }

    $line = "New ticket #{$ticketId} from {$t['client_slug']} ({$t['contact_email']}): {$t['summary']}";

    $creds = twilio_creds();
    if ($creds !== null && !empty($creds['agent_phone'])) {
        send_sms($creds['agent_phone'], $line, $ticketId, null);
    }

    if (file_exists('/html/wp-load.php')) {
        require_once '/html/wp-load.php';
        wp_mail('requests@azwebcorp.com', "New portal ticket #{$ticketId}", $line
            . "\n\nCategory: {$t['category']}\nDeadline: " . ($t['deadline'] ?: 'no rush')
            . "\nPublish preference: {$t['publish_preference']}");
    }
}
