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
 *
 * A THIRD HOST CONSTRAINT, FOUND LIVE ON 2026-09-13
 * The bare URL (https://azwebcorp.com/reports/, no query string) gets cached
 * by the platform's CDN regardless of any Cache-Control this script sends,
 * and that cache does not vary by cookie. The first response ever served for
 * that exact URL - anyone's dashboard, or the anonymous login form - becomes
 * "the" response for every subsequent visitor until the next manual flush.
 * That is a real cross-client data leak, not a staleness annoyance, and only
 * a manual cache flush is available on this host - no rule-based exclusion.
 *
 * THE FIX: the bare URL must return the exact same bytes for every visitor,
 * full stop, so caching it is harmless. It never inspects the session cookie
 * to decide what to render. Instead it always shows the anonymous shell,
 * which carries a tiny inline script that checks a separate, non-secret,
 * JS-readable marker cookie (SEEN_COOKIE - never the real session token) and,
 * if present, client-side-redirects to the SAME url plus a fresh random
 * ?s=<token> query string. That token carries no meaning at all; its only
 * job is to make the URL unique per visit so the CDN cache key differs every
 * time, which is what actually defeats a cache with no cookie-awareness.
 * Every response that renders real dashboard content - which still requires
 * the real, HttpOnly, signed session cookie exactly as before - is now only
 * ever served under one of these effectively-unguessable ?s= URLs, so even
 * if the platform caches one, it is cached under a key nobody else will ever
 * request. See README-level notes in [[azwebcorp-portal-ticket-design]] for
 * the incident this fixes.
 */

declare(strict_types=1);

const GATE_COOKIE   = 'azwc_portal';
const SEEN_COOKIE   = 'azwc_seen';         // non-secret marker only - see doc comment above
const GATE_TTL      = 60 * 60 * 24 * 30;   // stay signed in for a month
const LINK_TTL      = 60 * 15;             // a magic link is valid 15 minutes
const RATE_MAX      = 5;                   // send attempts per address per hour
const RATE_WINDOW   = 3600;

$BASE  = __DIR__;
$PAGES = $BASE . '/p';   // protected builds, 404 on direct hit

require_once __DIR__ . '/state_lib.php';

/* Roster and signing secret live in state_lib.php's in-webroot storage - see
 * that file's doc comment for why $HOME-based storage (this file's previous
 * approach) silently fails on this host despite looking correct. */

/* ---------------------------------------------------------------- helpers */

/** Server-side secret, generated once. Never rendered, never sent. */
function gate_secret(): string {
    $s = state_read('secret', null);
    if (is_string($s) && $s !== '') {
        return $s;
    }
    $s = bin2hex(random_bytes(32));
    state_write('secret', $s);
    return $s;
}

function roster(): array {
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

/** Magic links are single use: a spent nonce is recorded and refused after.
 *  Kept as one consolidated map (nonce => spent-at) rather than one file per
 *  nonce, pruned to entries still within LINK_TTL so it cannot grow forever. */
function nonce_spent(string $nonce): bool {
    $now = time();
    $spent = state_read('nonces', []);
    $spent = array_filter((array) $spent, fn($t) => $now - (int) $t < LINK_TTL);
    if (isset($spent[$nonce])) {
        return true;
    }
    $spent[$nonce] = $now;
    state_write('nonces', $spent);
    return false;
}

function rate_ok(string $email): bool {
    $now = time();
    $all = state_read('rate', []);
    $key = md5($email);
    $hits = array_values(array_filter((array) ($all[$key] ?? []), fn($t) => $now - (int) $t < RATE_WINDOW));
    if (count($hits) >= RATE_MAX) {
        return false;
    }
    $hits[] = $now;
    $all[$key] = $hits;
    state_write('rate', $all);
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

function shell(string $title, string $inner, bool $autoRedirect = false): void {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Robots-Tag: noindex, nofollow');
    // Only the bare, no-?s= response passes true here. It runs before the
    // page paints, so a returning signed-in visitor sees this shell for a
    // moment at most before bouncing to their own fresh ?s= url - never
    // before checking a REAL secret, only the non-sensitive marker cookie.
    $redirectScript = $autoRedirect
        ? '<script>(function(){if(/(?:^|; )azwc_seen=1(?:;|$)/.test(document.cookie)){'
        . 'var t=Math.random().toString(36).slice(2)+Date.now().toString(36);'
        . 'location.replace(location.pathname+"?s="+t);}})();</script>'
        : '';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
       . $redirectScript
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

function login_page(string $msg = '', string $cls = '', bool $autoRedirect = false): void {
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
HTML, $autoRedirect);
}

/* ---------------------------------------------------------------- routing */

/* A FOURTH HOST CONSTRAINT, FOUND LIVE THE SAME DAY AS THE THIRD
 * Whenever a response gets the forced `Cache-Control: public` treatment
 * described above, the platform also strips any Set-Cookie header from it
 * entirely - confirmed live: a GET to ?do=out and a GET to ?t=<token> both
 * called setcookie() correctly, and neither cookie ever reached the client.
 * This means, concretely, that NO GET request under /reports/ has ever been
 * able to actually set or clear a cookie in production, however correct the
 * PHP logic is - not just today's chroot fix, this predates it.
 *
 * A POST response was observed to keep its own Cache-Control (no-store) and
 * was not touched, so every place that needs to set or clear a cookie now
 * does so from a POST handler, never a GET one. The magic link in the email
 * is still a plain clickable URL (an email client cannot POST), so GET ?t=
 * now only renders a tiny auto-submitting form - no nonce is consumed and no
 * cookie is set until that form's POST actually lands. This has a second
 * benefit: some email providers pre-fetch links in the background to scan
 * them, which would silently burn a one-time nonce before a real click ever
 * happened; a pre-fetched GET no longer touches the nonce at all now. */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    setcookie(GATE_COOKIE, '', ['expires' => time() - 3600, 'path' => '/reports/', 'samesite' => 'Lax']);
    setcookie(SEEN_COOKIE, '', ['expires' => time() - 3600, 'path' => '/reports/', 'samesite' => 'Lax']);
    header('Location: ' . base_url());
    exit;
}

/* 1a. GET ?t=<token>: show the auto-submitting interstitial only. Cached or
 *     pre-fetched harmlessly - the token is single-use and unique per email,
 *     so a cached copy of this exact URL is never requested by anyone else,
 *     and nothing here has side effects yet. */
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['t'])) {
    $t = htmlspecialchars((string) $_GET['t'], ENT_QUOTES);
    shell('Signing in', <<<HTML
        <h1>Signing you in&hellip;</h1>
        <p>One moment.</p>
        <form method="post" id="redeem"><input type="hidden" name="t" value="{$t}">
          <noscript><button type="submit">Continue</button></noscript>
        </form>
        <script>document.getElementById('redeem').submit();</script>
HTML);
    exit;
}

/* 1b. POST of that form: the real redemption - nonce consumed and cookies
 *     set here, and only here, so this is the first point in the whole flow
 *     where a Set-Cookie header has any chance of surviving the platform. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['t'])) {
    $payload = unsign((string) $_POST['t']);
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
                // Readable by JS on purpose - carries no secret, just tells the
                // anonymous shell "this browser has a real session, try to find
                // it" without exposing anything an attacker could use. See the
                // doc comment at the top of this file for why this exists.
                setcookie(SEEN_COOKIE, '1', [
                    'expires'  => time() + GATE_TTL,
                    'path'     => '/reports/',
                    'secure'   => true,
                    'httponly' => false,
                    'samesite' => 'Lax',
                ]);
                $ok = true;
            }
        }
    }
    // A successful sign-in lands on a fresh, effectively-unguessable ?s= url
    // rather than the bare one - see the top-of-file doc comment for why the
    // bare url can never be the thing that renders real content. This is a
    // POST-redirect-GET on purpose, so refreshing the landing page never
    // re-submits the (now spent) token.
    $dest = $ok ? ('?s=' . bin2hex(random_bytes(12))) : '?e=1';
    header('Location: ' . base_url() . $dest);
    exit;
}

/* 2. The bare url (no ?s=) NEVER inspects the session cookie to decide what
 *    to render - it always shows the same anonymous shell, which is what
 *    makes it safe for the platform to cache. A POST (the login form
 *    submitting) is exempt: the form has no action attribute, so it posts
 *    back to whatever url rendered it, bare included, and that has to reach
 *    the handling in step 4 rather than bounce here. */
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['s'])) {
    login_page('', '', true);
    exit;
}

/* 3. Already signed in? Serve that client's page and nothing else. Only
 *    reachable via a ?s=-qualified url (or a POST) per the gate above, so a
 *    cached response here is cached under a url nobody else will request. */
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
                    // A plain <a href="?do=out"> would GET, and a GET can no
                    // longer clear the cookie (see the routing comment above) -
                    // this has to POST.
                    . '<div class="note"><form method="post" style="display:inline">'
                    . '<input type="hidden" name="action" value="logout">'
                    . '<button type="submit" style="all:unset;color:#e6b84d;cursor:pointer;'
                    . 'text-decoration:underline">Sign out</button></form></div>');
                exit;
            }
        }
    }
    // Anything unverifiable is treated as signed out rather than trusted.
    setcookie(GATE_COOKIE, '', ['expires' => time() - 3600, 'path' => '/reports/', 'samesite' => 'Lax']);
    setcookie(SEEN_COOKIE, '', ['expires' => time() - 3600, 'path' => '/reports/', 'samesite' => 'Lax']);
}

/* 4. Request a link. */
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
