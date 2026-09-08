<?php
/**
 * Deployed to html/reports/_owner/index.php
 *
 * Authentication gate for the agency copy of the portals - the build with the
 * account switcher, where one page can reach every client's data.
 *
 * WHY THIS EXISTS RATHER THAN .htaccess
 * The obvious answer is AuthType Basic in an .htaccess. It does not work on
 * this host: a directory dropped under html/reports/ with a correct AuthType/
 * AuthUserFile/Require block still served 200 to an unauthenticated request
 * (verified 2026-09-08 against origin, with Cloudflare reporting a cache MISS,
 * so it was not an edge artifact). Managed WordPress hosting does not grant
 * AllowOverride AuthConfig here. PHP does run - the live.php feed proves it -
 * so the gate is implemented in PHP instead.
 *
 * HOW THE PAGES ARE KEPT UNREACHABLE
 * A gate is only as good as the guarantee that nothing bypasses it. Plain
 * .html files next to this script would be fetchable directly by anyone who
 * guessed the path, and this host gives no working way to deny that at the
 * server level. Outside the webroot is not an option either - PHP here cannot
 * read the home directory. So each built page is stored as p/<slug>.php with a
 * one-line guard at the top: requested directly it is executed, sees that
 * AZWC_OWNER_GATE is undefined, and 404s. Only this script, after
 * authenticating, defines that constant and includes it.
 */

declare(strict_types=1);

// Both of these sit INSIDE the webroot, which looks wrong and is not: PHP on
// this host cannot read the home directory at all (open_basedir/user split -
// the same reason the GA4 feed keeps its service-account key in
// api/ga4-key.php rather than in ~). Files ending in .php are executed, never
// served as text, so a direct request for either returns nothing useful. The
// page files additionally refuse to render unless this script defined the
// guard constant below.
const PAGES_DIR = __DIR__ . '/p';
const AUTH_FILE = __DIR__ . '/auth.php';
const SESSION_KEY = 'azwc_portal_owner';

session_start();

header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');       // never index the agency copy
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');

/** The clients this gate will serve, discovered from disk, so adding a client
 *  is a deploy rather than an edit here. Names come from directory names only,
 *  which cannot contain a traversal sequence by construction. */
function roster(): array {
    $out = [];
    foreach (scandir(PAGES_DIR) ?: [] as $e) {
        if ($e === '.' || $e === '..') continue;
        if (preg_match('/^([a-z0-9_-]+)\.php$/', $e, $m)) {
            $out[] = $m[1];
        }
    }
    sort($out);
    return $out;
}

function login_page(string $note = ''): void {
    http_response_code($note ? 401 : 200);
    $msg = $note ? '<p class="err">' . htmlspecialchars($note, ENT_QUOTES) . '</p>' : '';
    echo <<<HTML
<!doctype html><html><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>AZ Web Corp - agency reporting</title>
<style>
 body{background:#000;color:#fff;font:15px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif;
      min-height:100vh;display:grid;place-items:center;margin:0}
 form{background:#101113;border:1px solid rgba(255,255,255,.12);border-radius:14px;
      padding:26px 26px 22px;width:min(360px,92vw)}
 h1{font-size:16px;margin:0 0 4px} p{color:#85847f;font-size:12.5px;margin:0 0 18px}
 .err{color:#e66767;margin:0 0 12px}
 input{width:100%;padding:11px 12px;border-radius:9px;border:1px solid rgba(255,255,255,.16);
       background:#000;color:#fff;font-size:14px}
 button{width:100%;margin-top:12px;padding:11px;border:0;border-radius:9px;background:#d95926;
        color:#fff;font-weight:650;font-size:14px;cursor:pointer}
</style></head><body>
<form method="post">
 <h1>Agency reporting</h1>
 <p>All client properties. Client-facing links are elsewhere and unaffected.</p>
 {$msg}
 <input type="password" name="pw" placeholder="Password" autofocus autocomplete="current-password">
 <button type="submit">Sign in</button>
</form></body></html>
HTML;
    exit;
}

// --- authenticate ----------------------------------------------------------
if (isset($_GET['logout'])) {
    unset($_SESSION[SESSION_KEY]);
    session_destroy();
    header('Location: ?');
    exit;
}

if (empty($_SESSION[SESSION_KEY])) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        login_page();
    }
    $creds = is_readable(AUTH_FILE) ? require AUTH_FILE : null;
    if (!is_array($creds) || empty($creds['salt']) || empty($creds['hash'])) {
        login_page('Sign-in is not configured on the server.');
    }
    $salt = (string) $creds['salt'];
    $want = (string) $creds['hash'];
    $got = hash('sha256', $salt . (string) ($_POST['pw'] ?? ''));
    // Constant-time compare, and a deliberate pause so this cannot be hammered.
    if (!hash_equals($want, $got)) {
        sleep(2);
        login_page('That password was not correct.');
    }
    session_regenerate_id(true);
    $_SESSION[SESSION_KEY] = true;
}

// --- serve -----------------------------------------------------------------
$clients = roster();
if (!$clients) {
    echo 'No agency copies are deployed yet.';
    exit;
}

$want = (string) ($_GET['c'] ?? $clients[0]);
// Allowlist rather than sanitise: only a name already found on disk is served,
// so no crafted value can escape the directory.
if (!in_array($want, $clients, true)) {
    $want = $clients[0];
}

$file = PAGES_DIR . '/' . $want . '.php';
if (!is_readable($file)) {
    http_response_code(404);
    echo 'That report has not been built yet.';
    exit;
}

header('Content-Type: text/html; charset=utf-8');
define('AZWC_OWNER_GATE', true);   // the page files check for this and 404 without it
require $file;
