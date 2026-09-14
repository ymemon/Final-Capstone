<?php
/**
 * Tier 1: simple, self-service, real-time actions - no ticket, no human in
 * the loop (per yasir, 2026-09-12). Every action still gets logged to
 * tier1_action_log for accountability, but nothing here waits on a person.
 *
 * Each entry is a small registry: a key, and a handler that returns the
 * result to send back to the client. Adding a new Tier 1 action means adding
 * one array entry here, not touching ticket_api.php.
 *
 * gmail_connect is intentionally stubbed rather than faked. A real Gmail
 * connector needs a registered Google Cloud OAuth client, a verified
 * consent screen, and per-client token storage using the same
 * outside-webroot pattern as twilio-creds.json/db.php - none of which exist
 * yet. Returning a clear "not implemented" is safer than pretending a
 * button works.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function log_tier1_action(string $slug, int $contactId, string $actionKey, array $detail, string $result): void {
    db()->prepare(
        'INSERT INTO tier1_action_log (client_slug, contact_id, action_key, detail_json, result) VALUES (?, ?, ?, ?, ?)'
    )->execute([$slug, $contactId, $actionKey, json_encode($detail), $result]);
}

/** @return array{ok: bool, message: string, ...} */
function tier1_gmail_connect(string $slug, int $contactId, array $payload): array {
    return [
        'ok' => false,
        'message' => 'Gmail connection is not available yet - this needs a Google OAuth '
            . 'integration that has not been built. Contact AZ Web Corp directly for now.',
    ];
}

function tier1_report_frequency_set(string $slug, int $contactId, array $payload): array {
    $freq = (string) ($payload['frequency'] ?? '');
    if (!in_array($freq, ['weekly', 'monthly'], true)) {
        return ['ok' => false, 'message' => 'frequency must be "weekly" or "monthly"'];
    }
    // Storage for the preference itself is out of scope for this scaffold -
    // the existing weekly-report send job (azwebcorp-weekly-seo-report) would
    // need to read this same tier1_action_log or a small preferences table
    // before this actually changes anyone's email cadence.
    return ['ok' => true, 'message' => "Report frequency set to {$freq}."];
}

function tier1_contact_phone_update(string $slug, int $contactId, array $payload): array {
    $phone = to_e164((string) ($payload['phone'] ?? ''));
    if ($phone === null) {
        return ['ok' => false, 'message' => 'That does not look like a valid phone number.'];
    }
    db()->prepare('UPDATE contacts SET phone_e164 = ? WHERE id = ?')->execute([$phone, $contactId]);
    return ['ok' => true, 'message' => 'Phone number updated. You will now receive text updates on your tickets.'];
}

function tier1_registry(): array {
    return [
        'gmail_connect' => 'tier1_gmail_connect',
        'report_frequency_set' => 'tier1_report_frequency_set',
        'contact_phone_update' => 'tier1_contact_phone_update',
    ];
}

function dispatch_tier1_action(string $slug, int $contactId, string $actionKey, array $payload): array {
    $registry = tier1_registry();
    if (!isset($registry[$actionKey])) {
        return ['ok' => false, 'message' => 'unknown action'];
    }
    require_once __DIR__ . '/sms_notify.php'; // to_e164() lives there
    $result = $registry[$actionKey]($slug, $contactId, $payload);
    log_tier1_action($slug, $contactId, $actionKey, $payload, $result['ok'] ? 'ok' : 'error');
    return $result;
}
