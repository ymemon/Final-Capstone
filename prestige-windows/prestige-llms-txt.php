<?php
/**
 * Plugin Name: Prestige llms.txt
 * Description: Serves /llms.txt directly. On this host the static file is unreachable without a query string because WordPress's canonical redirect fires first.
 * Version: 1.0.0
 *
 * THE PROBLEM THIS SOLVES
 * A static /html/llms.txt was uploaded and is readable on disk, but:
 *
 *     /llms.txt        -> 301 to /llms.txt/
 *     /llms.txt/       -> 404
 *     /llms.txt?x=1    -> 200, serves the file correctly
 *
 * So the file is fine; the request never reaches it. On this stack requests
 * for root-level files are routed through PHP, and WordPress's
 * redirect_canonical() decides /llms.txt looks like a permalink missing its
 * trailing slash. Adding the slash then matches nothing and 404s. The query
 * string changes the routing enough to skip that, which is why ?x=1 works and
 * masked the problem during the first check.
 *
 * Worth remembering: azwebcorp.com and everythingit.ie both serve an identical
 * static llms.txt with no trouble. Same product, same hosting vendor, three
 * different results - so "it worked on the other site" is not evidence here,
 * and a bare 200 check immediately after upload can pass while the canonical
 * URL is broken.
 *
 * Hooks 'init' at priority 0 so it runs before redirect_canonical. Reads the
 * static file so the content stays editable without touching PHP; falls back
 * to a minimal inline summary if the file is ever missing, since an empty
 * 200 would be worse than a 404.
 *
 * Safe at the mu-plugins root: hook registration only, and the hook does
 * nothing at all unless the request path is exactly /llms.txt.
 */

defined('ABSPATH') || exit;

add_action('init', static function (): void {

    $path = strtolower(untrailingslashit(
        (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH)
    ));

    if ($path !== '/llms.txt') {
        return;
    }

    $file = ABSPATH . 'llms.txt';
    $body = is_readable($file) ? (string) file_get_contents($file) : '';

    if ($body === '') {
        $body = "# Prestige Windows\n\n"
              . "> Licensed private window and door contractor in Scottsdale, Arizona. "
              . "Supplies and installs premium-grade window and door systems statewide "
              . "across Arizona. Phone: (480) 331-3209. "
              . "Website: https://prestigewindowsaz.com/\n";
    }

    // 200, plain text, cacheable for a day. nosniff so it is never treated as
    // anything but text.
    status_header(200);
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=86400');
    header('Content-Length: ' . strlen($body));
    echo $body;
    exit;
}, 0);
