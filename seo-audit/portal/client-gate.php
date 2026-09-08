<?php
/**
 * One link for every client. Each signs in with their own email and reaches
 * only their own dashboard.
 *
 * Deployed to html/reports/index.php.
 *
 * WHY THIS EXISTS
 * The per-client dashboards were served as plain files:
 *
 *     GET /reports/everythingit/index.html      -> 200, no authentication
 *     GET /reports/everythingit/api/live.php    -> 200, no authentication
 *
 * The slugs are just client names, so anyone who guessed one read that
 * client's traffic, keywords and AI-crawler data. The agency view already
 * solved this correctly - /reports/_owner/p/*.php returns 404 to a direct
 * request and is only ever reached through a gate - so this applies the same
 * shape to clients.
 *
 * TWO HOST CONSTRAINTS THIS IS BUILT AROUND
 *   1. .htaccess authentication is ignored here. A correct AuthType block
 *      still served 200 to an unauthenticated request, verified against
 *      origin. Authentication must therefore live in PHP, as it does below.
 *   2. Anything under the webroot is reachable, so protected pages carry a
 *      guard constant and refuse to render unless this gate included them.
 *
 * THE SECURITY PROPERTY, STATED PLAINLY
 * The slug served is read from the signed session cookie and never from the
 * URL, a form field or a header. There is no request a signed-in client can
 * make that returns another client's page. If the roster does not map an
 * address to a slug, that address cannot reach anything at all - it fails
 * closed, which is why an empty roster locks everyone out rather than letting
 * everyone in.
 */

declare(strict_types=1);

const GATE_COOKIE   = 'azwc_portal';
const GATE_TTL      = 60 * 60 * 24 * 30;   // stay signed in for a month
const LINK_TTL      = 60 * 15;             // a magic link is valid 15 minutes
const RATE_MAX      = 5;                   // send attempts per address per hour
const RATE_WINDOW   = 3600;

$BASE  = __DIR__;
$PAGES = $BASE . '/p';   // protected builds, 404 on direct hit

/* State and roster live in the account home, never under the webroot.
 *
 * A roster.json sitting in /html/reports/ would be served like any other file
 * here - the client dashboards prove that - and it holds every client contact
 * address. The signing secret is worse still: reading it lets anyone mint a
 * session for any client. .htaccess deny rules are not trustworthy on this
 * host, so neither file is placed anywhere a request can reach. */
$HOME   = getenv('HOME') ?: dirname($BASE, 3);
$STATE  = $HOME . '/.portal-client-state';
$ROSTER = $STATE . '/roster.json';

/* ---------------------------------------------------------------- helpers */

function state_dir(): string {
    global $STATE;
    if (!is_dir($STATE)) {
        @mkdir($STATE, 0700, true);
    }
    return $STATE;
}

/** Server-side secret, generated once. Never rendered, never sent. */
function gate_secret(): string {
    $f = state_dir() . '/secret';
    if (is_readable($f)) {
        $s = trim((string) file_get_contents($f));
        if ($s !== '') {
            return $s;
        }
    }
    $s = bin2hex(random_bytes(32));
    file_put_contents($f, $s);
    @chmod($f, 0600);
    return $s;
}

function roster(): array {
    global $ROSTER;
    if (!is_readable($ROSTER)) {
        return [];
    }
    $j = json_decode((string) file_get_contents($ROSTER), true);
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
                // Last mapping wins; an address should belong to one client.
                $out[$addr] = ['slug' => $slug, 'name' => (string) ($entry['name'] ?? $slug)];
            }
        }
    }
    return $out;
}

function b64u(string $raw): string {
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function b64u_decode(string $s): string {
    return (string) base64_decode(strtr($s, '-_', '+/'));
}

function sign(string $payload): string {
    return b64u($payload) . '.' . b64u(hash_hmac('sha256', $payload, gate_secret(), true));
}

/** Verify a signed value and return its payload, or null. */
function unsign(string $token): ?string {
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) {
        return null;
    }
    $payload = b64u_decode($parts[0]);
    $given   = b64u_decode($parts[1]);
    $want    = hash_hmac('sha256', $payload, gate_secret(), true);
    return hash_equals($want, $given) ? $payload : null;
}

/** Magic links are single use: a spent nonce is recorded and refused after. */
function nonce_spent(string $nonce): bool {
    $f = state_dir() . '/spent-' . preg_replace('/[^a-f0-9]/', '', $nonce);
    if (file_exists($f)) {
        return true;
    }
    file_put_contents($f, (string) time());
    return false;
}

function rate_ok(string $email): bool {
    $f = state_dir() . '/rate-' . md5($email);
    $hits = is_readable($f) ? (array) json_decode((string) file_get_contents($f), true) : [];
    $now = time();
    $hits = array_values(array_filter($hits, fn($t) => $now - (int) $t < RATE_WINDOW));
    if (count($hits) >= RATE_MAX) {
        return false;
    }
    $hits[] = $now;
    file_put_contents($f, json_encode($hits));
    return true;
}

function base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'azwebcorp.com';
    $path = strtok($_SERVER['REQUEST_URI'] ?? '/reports/', '?');
    if (substr($path, -1) !== '/') {
        $path = rtrim(dirname($path), '/') . '/';
    }
    return ($https ? 'https://' : 'http://') . $host . $path;
}

/* ------------------------------------------------------------------ views */

function shell(string $title, string $inner): void {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Robots-Tag: noindex, nofollow');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<meta name="robots" content="noindex,nofollow">'
       . '<title>' . htmlspecialchars($title) . ' &middot; AZ Web Corp</title><style>'
       . ':root{--ink:#f2f4f7;--ink2:#9aa4b2;--line:#23262c;--gold:#e6b84d;--bg:#050506}'
       . '*{box-sizing:border-box}body{margin:0;min-height:100vh;display:flex;align-items:center;'
       . 'justify-content:center;background:var(--bg);color:var(--ink);'
       . "font-family:'Inter',system-ui,-apple-system,sans-serif;padding:24px}"
       . '.card{width:100%;max-width:430px;border:1px solid var(--line);border-radius:14px;'
       . 'background:#0a0b0d;padding:34px}'
       . 'h1{font-size:19px;margin:0 0 6px}p{color:var(--ink2);font-size:14px;line-height:1.6;margin:0 0 18px}'
       . 'input{width:100%;padding:13px 15px;border-radius:9px;border:1px solid var(--line);'
       . 'background:#050506;color:var(--ink);font-size:15px}'
       . 'button{width:100%;margin-top:12px;padding:13px;border:0;border-radius:9px;background:var(--gold);'
       . 'color:#000;font-weight:800;font-size:15px;cursor:pointer}'
       . '.note{margin-top:16px;font-size:12.5px;color:#6b7480}'
       . '.err{color:#f87171}.ok{color:#4ade80}'
       . '</style></head><body><div class="card">' . $inner . '</div></body></html>';
}

function login_page(string $msg = '', string $cls = ''): void {
    $m = $msg === '' ? '' : '<p class="' . $cls . '">' . htmlspecialchars($msg) . '</p>';
    shell('Client reporting', <<<HTML
        <h1>Client reporting</h1>
        <p>Enter the email address your reports are sent to. We will email you a
        sign-in link that lasts fifteen minutes.</p>
        {$m}
        <form method="post">
          <input type="email" name="email" placeholder="you@yourcompany.com" required autofocus
                 autocomplete="email" inputmode="email">
          <button type="submit">Email me a sign-in link</button>
        </form>
        <div class="note">Access is limited to addresses we hold for your account.
        If yours is not recognised, contact AZ Web Corp on (480) 818-5761.</div>
HTML);
}

/* ---------------------------------------------------------------- routing */

$action = $_GET['do'] ?? '';

if ($action === 'out') {
    setcookie(GATE_COOKIE, '', ['expires' => time() - 3600, 'path' => '/reports/', 'samesite' => 'Lax']);
    header('Location: ' . base_url());
    exit;
}

/* 1. Consume a magic link. */
if (isset($_GET['t'])) {
    $payload = unsign((string) $_GET['t']);
    $ok = false;
    if ($payload !== null) {
        $d = json_decode($payload, true);
        if (is_array($d) && ($d['exp'] ?? 0) > time() && !nonce_spent((string) ($d['n'] ?? ''))) {
            // Re-check the roster at redemption: access revoked between sending
            // and clicking must actually take effect.
            $who = roster()[strtolower((string) ($d['e'] ?? ''))] ?? null;
            if ($who && $who['slug'] === ($d['s'] ?? null)) {
                $session = json_encode(['s' => $who['slug'], 'e' => $d['e'], 'exp' => time() + GATE_TTL]);
                setcookie(GATE_COOKIE, sign($session), [
                    'expires'  => time() + GATE_TTL,
                    'path'     => '/reports/',
                    'secure'   => true,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
                $ok = true;
            }
        }
    }
    header('Location: ' . base_url() . ($ok ? '' : '?e=1'));
    exit;
}

/* 2. Already signed in? Serve that client's page and nothing else. */
$cookie = $_COOKIE[GATE_COOKIE] ?? '';
if ($cookie !== '') {
    $payload = unsign($cookie);
    if ($payload !== null) {
        $d = json_decode($payload, true);
        if (is_array($d) && ($d['exp'] ?? 0) > time()) {
            $slug = (string) ($d['s'] ?? '');
            // Still on the roster? A removed client loses access immediately.
            $who = roster()[strtolower((string) ($d['e'] ?? ''))] ?? null;
            if ($who && $who['slug'] === $slug) {
                $file = $PAGES . '/' . preg_replace('/[^a-z0-9-]/', '', $slug) . '.php';
                if (is_readable($file)) {
                    define('AZWC_PORTAL_GATE', true);   // the guard those pages check
                    $GLOBALS['azwc_portal_slug'] = $slug;
                    require $file;
                    exit;
                }
                shell('Report not ready', '<h1>Report not ready</h1>'
                    . '<p>Your account is recognised but this month\'s report has not been '
                    . 'published yet. Please try again shortly.</p>'
                    . '<div class="note"><a href="?do=out" style="color:#e6b84d">Sign out</a></div>');
                exit;
            }
        }
    }
    // Anything unverifiable is treated as signed out rather than trusted.
    setcookie(GATE_COOKIE, '', ['expires' => time() - 3600, 'path' => '/reports/', 'samesite' => 'Lax']);
}

/* 3. Request a link. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        login_page('That does not look like an email address.', 'err');
        exit;
    }
    if (!rate_ok($email)) {
        login_page('Too many sign-in requests for that address. Try again in an hour.', 'err');
        exit;
    }

    $who = roster()[$email] ?? null;
    if ($who) {
        $token = sign(json_encode([
            'e'   => $email,
            's'   => $who['slug'],
            'n'   => bin2hex(random_bytes(16)),
            'exp' => time() + LINK_TTL,
        ]));
        $link = base_url() . '?t=' . rawurlencode($token);

        if (file_exists('/html/wp-load.php')) {
            require_once '/html/wp-load.php';
            wp_mail(
                $email,
                'Your AZ Web Corp reporting sign-in link',
                "Hello,\n\nHere is your sign-in link for the {$who['name']} reporting dashboard:\n\n"
                . "{$link}\n\nIt is valid for 15 minutes and can be used once.\n\n"
                . "If you did not request this, you can ignore this email - nobody can sign in without the link.\n\n"
                . "AZ Web Corp\n(480) 818-5761",
                ['Content-Type: text/plain; charset=UTF-8']
            );
        }
    }

    // Identical response whether or not the address is on the roster, so this
    // form cannot be used to discover who our clients are.
    login_page('If that address is on your account, a sign-in link is on its way. It expires in 15 minutes.', 'ok');
    exit;
}

login_page(isset($_GET['e']) ? 'That link has expired or has already been used. Request another.' : '');
