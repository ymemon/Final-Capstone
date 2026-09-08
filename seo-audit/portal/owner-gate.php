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
        // Leading underscore is reserved for generated files such as
        // _index.php, which is data for the overview and not a property.
        if (preg_match('/^([a-z0-9][a-z0-9_-]*)\.php$/', $e, $m)) {
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

/** Every property on one screen, which is what "master access" should open on. */
function overview_page(): void {
    $rows = is_readable(PAGES_DIR . '/_index.php') ? require PAGES_DIR . '/_index.php' : [];
    $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);

    $fmt = function ($n) {
        if ($n === null) return '&mdash;';
        return $n >= 10000 ? round($n / 1000, 1) . 'K' : number_format((float) $n);
    };
    $delta = function ($cur, $prev) {
        if (!$prev) return '<span class="d flat">no prior period</span>';
        $p = ($cur - $prev) / $prev * 100;
        $cls = $p > 0.5 ? 'up' : ($p < -0.5 ? 'down' : 'flat');
        $arrow = $p > 0.5 ? '&#9650;' : ($p < -0.5 ? '&#9660;' : '&#9679;');
        return sprintf('<span class="d %s">%s %.1f%% vs prev</span>', $cls, $arrow, abs($p));
    };

    $cards = '';
    foreach ($rows as $r) {
        $ga4 = $r['sessions'] === null
            ? '<div class="m"><b>&mdash;</b><span>Analytics not connected</span></div>'
            : '<div class="m"><b>' . $fmt($r['sessions']) . '</b><span>Sessions &middot; 28d</span></div>';
        $ai = $r['aiHits']
            ? '<div class="m"><b>' . $fmt($r['aiHits']) . '</b><span>AI crawls &middot; ' . (int) $r['aiDays'] . 'd</span></div>'
            : '<div class="m"><b>&mdash;</b><span>No AI crawler log</span></div>';
        $cards .= '<a class="card" href="?c=' . $e($r['slug']) . '">'
            . '<div class="hd"><h2>' . $e($r['name']) . ($r['internal'] ? ' <i>internal</i>' : '') . '</h2>'
            . '<div class="dom">' . $e($r['domain']) . '</div></div>'
            . '<div class="ms">'
            . '<div class="m"><b>' . $fmt($r['clicks']) . '</b><span>Clicks &middot; 28d</span>' . $delta($r['clicks'], $r['clicksPrev']) . '</div>'
            . '<div class="m"><b>' . $fmt($r['impressions']) . '</b><span>Impressions &middot; 28d</span>' . $delta($r['impressions'], $r['impressionsPrev']) . '</div>'
            . '<div class="m"><b>' . $fmt($r['keywords']) . '</b><span>Ranking keywords</span></div>'
            . '<div class="m"><b>' . ($r['position'] ?: '&mdash;') . '</b><span>Avg position</span></div>'
            . $ga4 . $ai
            . '</div><div class="go">Open portal &rarr;</div></a>';
    }
    if (!$cards) {
        $cards = '<p class="none">No properties have been built yet.</p>';
    }

    echo <<<HTML
<!doctype html><html><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>All properties - AZ Web Corp</title>
<style>
 :root{color-scheme:dark}
 body{background:#000;color:#fff;margin:0;padding:34px 26px 60px;
      font:15px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif}
 header{max-width:1180px;margin:0 auto 26px}
 h1{font-size:23px;margin:0 0 5px}
 .sub{color:#85847f;font-size:13px;margin:0}
 .out{float:right;color:#85847f;font-size:12.5px;text-decoration:none}
 .out:hover{color:#fff}
 .grid{max-width:1180px;margin:0 auto;display:grid;gap:15px;
       grid-template-columns:repeat(auto-fill,minmax(340px,1fr))}
 .card{display:block;text-decoration:none;color:inherit;background:#101113;
       border:1px solid rgba(255,255,255,.10);border-radius:15px;padding:19px 20px 15px;
       transition:border-color .16s,transform .16s}
 .card:hover{border-color:rgba(230,184,77,.45);transform:translateY(-2px)}
 .hd h2{font-size:16px;margin:0}
 .hd h2 i{font-style:normal;font-size:10px;letter-spacing:.06em;text-transform:uppercase;
          color:#85847f;border:1px solid rgba(255,255,255,.16);border-radius:20px;padding:1px 7px;
          vertical-align:middle;margin-left:5px}
 .dom{color:#85847f;font-size:12.5px;margin-top:2px}
 .ms{display:grid;grid-template-columns:1fr 1fr;gap:11px;margin:16px 0 13px}
 .m b{display:block;font-size:20px;font-variant-numeric:tabular-nums;line-height:1.15}
 .m span{display:block;color:#85847f;font-size:11px}
 .d{display:block;font-size:11px;margin-top:2px}
 .d.up{color:#0ca30c}.d.down{color:#e66767}.d.flat{color:#85847f}
 .go{color:#e6b84d;font-size:12.5px;border-top:1px solid rgba(255,255,255,.08);padding-top:11px}
 .none{max-width:1180px;margin:0 auto;color:#85847f}
</style></head><body>
<header>
 <a class="out" href="?logout=1">Sign out</a>
 <h1>All properties</h1>
 <p class="sub">Agency view. Every property you have connected, busiest first. Client links show only their own.</p>
</header>
<div class="grid">{$cards}</div>
</body></html>
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
// Past this line the request is authenticated, so the guard the page files and
// _index.php check for can be defined. It has to happen before the overview
// runs, not just before a client page is included - the overview requires
// _index.php, which carries the same guard.
define('AZWC_OWNER_GATE', true);

$clients = roster();
if (!$clients) {
    echo 'No agency copies are deployed yet.';
    exit;
}

// No property chosen (or an unknown one) means show the overview - every
// property at once - rather than silently landing on whichever happens to be
// first. Allowlist rather than sanitise: only a name already found on disk is
// served, so no crafted value can escape the directory.
$want = $_GET['c'] ?? null;
if ($want === null || !in_array($want, $clients, true)) {
    overview_page();
}

$file = PAGES_DIR . '/' . $want . '.php';
if (!is_readable($file)) {
    http_response_code(404);
    echo 'That report has not been built yet.';
    exit;
}

header('Content-Type: text/html; charset=utf-8');
require $file;
