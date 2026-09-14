<?php
/**
 * Identifies "who is calling this endpoint" for the ticket system's two
 * JSON APIs, reusing the two auth mechanisms client-gate.php and
 * owner-gate.php already built and proved out on this host - this file adds
 * no new auth, it only reads sessions those gates already created.
 *
 * client_session() reads the exact same roster/secret state client-gate.php
 * writes, via the shared state_lib.php (state_read/state_write) rather than
 * a second copy of file-access logic - both files now depend on one
 * storage contract instead of keeping two copies in sync by hand. See
 * state_lib.php's doc comment for why that storage lives inside the webroot
 * rather than $HOME (a 2026-09-13 live test proved the $HOME approach this
 * file used to duplicate never actually worked from a real web request).
 *
 * owner_session_ok() has no such dependency: it just reads the native PHP
 * session owner-gate.php already started, using the same session key. For
 * that to work, ticket_api.php's agent-facing sibling (agent_api.php) must
 * be deployed in the same directory as owner-gate.php's index.php
 * (html/reports/_owner/) so the browser's session cookie covers both.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/state_lib.php';

// --- client-facing session (mirrors client-gate.php) ------------------------

const CLIENT_GATE_COOKIE = 'azwc_portal';

function client_gate_secret(): ?string {
    $s = state_read('secret', null);
    return (is_string($s) && $s !== '') ? $s : null; // null: client-gate.php has never run yet
}

function client_roster(): array {
    $j = state_read('roster', []);
    if (!is_array($j)) {
        return [];
    }
    $out = [];
    foreach ($j as $slug => $entry) {
        if ($slug === '_README' || !is_array($entry)) {
            continue;
        }
        foreach (($entry['emails'] ?? []) as $addr) {
            $addr = strtolower(trim((string) $addr));
            if ($addr !== '') {
                $out[$addr] = ['slug' => $slug, 'name' => (string) ($entry['name'] ?? $slug)];
            }
        }
    }
    return $out;
}

function client_b64u_decode(string $s): string {
    return (string) base64_decode(strtr($s, '-_', '+/'));
}

/** Verifies a client-gate.php session cookie. Returns
 *  ['slug' => ..., 'email' => ...] for a valid, current, still-rostered
 *  session, or null for anything else (missing, expired, tampered, or the
 *  client was removed from the roster since signing in). */
function client_session(): ?array {
    $secret = client_gate_secret();
    $cookie = $_COOKIE[CLIENT_GATE_COOKIE] ?? '';
    if ($secret === null || $cookie === '') {
        return null;
    }

    $parts = explode('.', $cookie, 2);
    if (count($parts) !== 2) {
        return null;
    }
    $payload = client_b64u_decode($parts[0]);
    $given   = client_b64u_decode($parts[1]);
    $want    = hash_hmac('sha256', $payload, $secret, true);
    if (!hash_equals($want, $given)) {
        return null;
    }

    $d = json_decode($payload, true);
    if (!is_array($d) || ($d['exp'] ?? 0) <= time()) {
        return null;
    }

    $email = strtolower((string) ($d['e'] ?? ''));
    $slug  = (string) ($d['s'] ?? '');
    $who   = client_roster()[$email] ?? null;
    if (!$who || $who['slug'] !== $slug) {
        return null; // revoked/changed since the cookie was signed
    }

    return ['slug' => $slug, 'email' => $email, 'name' => $who['name']];
}

/** Ends the request with 401 JSON if there is no valid client session; the
 *  API handler calls this first and can trust the returned slug/email after. */
function require_client_session(): array {
    $s = client_session();
    if ($s === null) {
        json_response(['error' => 'not signed in'], 401);
    }
    return $s;
}

// --- agent/owner-facing session (mirrors owner-gate.php) --------------------

const OWNER_SESSION_KEY = 'azwc_portal_owner';

function owner_session_ok(): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    return !empty($_SESSION[OWNER_SESSION_KEY]);
}

function require_owner_session(): void {
    if (!owner_session_ok()) {
        json_response(['error' => 'not signed in'], 401);
    }
}

// --- shared response helper --------------------------------------------------

function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Reads a JSON request body once, as an associative array. */
function json_body(): array {
    $raw = file_get_contents('php://input') ?: '';
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}
