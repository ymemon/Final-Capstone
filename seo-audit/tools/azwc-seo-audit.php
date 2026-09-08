<?php
/**
 * Plugin Name: AZWebCorp Ultimate SEO Audit
 * Description: Comprehensive, visitor‑facing SEO audit with premium UI. Every number is measured from the actual site response.
 * Version: 2.1.0
 * Author: AZWebCorp
 *
 * ---------------------------------------------------------------------------
 * EVERY NUMBER THIS TOOL SHOWS IS MEASURED.
 * No estimates, no traffic guesses, no artificial authority scores.
 * ---------------------------------------------------------------------------
 */

if (!defined('ABSPATH')) {
    exit;
}

/* -------------------------------------------------------------------------
 * CORE CONFIG & CONSTANTS
 * ---------------------------------------------------------------------- */
define('AZWC_AUDIT_VERSION', '2.1.0');
define('AZWC_AUDIT_CACHE_HOURS', 6);
define('AZWC_AUDIT_RATE_PER_HOUR', 12);
define('AZWC_AUDIT_TIMEOUT', 30);
define('AZWC_AUDIT_MAX_BYTES', 3145728);
define('AZWC_PSI_TIMEOUT', 150);
define('AZWC_LAZY_MANY', 10);
define('AZWC_LAZY_MIN_PCT', 25);
// Raised from 10 so the live crawl log has more genuine work to show. The
// budget below is what makes that safe: a probe is HEAD then, if that is
// refused, GET, so 30 links is up to 360s of worst-case waiting against the
// 150s set_time_limit in azwc_audit_stage_site(). Probing stops when the
// budget is spent and the report states how many links were actually reached,
// so a slow site yields a smaller sample rather than a dead audit.
define('AZWC_LINKCHECK_MAX', 30);
define('AZWC_LINKCHECK_TIMEOUT', 6);
define('AZWC_LINKCHECK_BUDGET', 45);
define('AZWC_SITEMAP_STALE_DAYS', 180);

function azwc_audit_psi_key()
{
    if (defined('AZWC_PSI_KEY') && AZWC_PSI_KEY) {
        return AZWC_PSI_KEY;
    }
    return (string) get_option('azwc_audit_psi_key', '');
}

/* -------------------------------------------------------------------------
 * LIVE PROGRESS
 *
 * The audit is one long blocking request, so before this the front end had
 * nothing to show and animated a step list on a timer instead. The steps were
 * honestly named but the timing was invented, and no real finding appeared
 * until the whole thing finished.
 *
 * Every HTTP request the audit makes goes through azwc_audit_fetch() or
 * azwc_audit_probe_link(), so instrumenting those two gives a genuine feed of
 * what is being crawled. Each event is appended to a transient keyed by a job
 * id that the browser generates and sends up front; a second, cheap request
 * polls that key while the long one is still running. Object cache makes the
 * write visible to the polling request immediately.
 *
 * Nothing here invents a URL. If the audit is served from cache it performs no
 * fetches, and the front end says so rather than replaying a crawl that did
 * not happen.
 * ---------------------------------------------------------------------- */

define('AZWC_AUDIT_JOB_TTL', 600);
define('AZWC_AUDIT_JOB_MAX_EVENTS', 150);

/** Job ids come from the browser, so they are never trusted as a key. */
function azwc_audit_job_key($job)
{
    $job = preg_replace('/[^a-zA-Z0-9]/', '', (string) $job);
    return $job ? 'azwc_audit_job_' . substr($job, 0, 40) : '';
}

function azwc_audit_progress_start($job, $url)
{
    $key = azwc_audit_job_key($job);
    if (!$key) {
        return;
    }
    $GLOBALS['azwc_audit_job'] = $key;
    set_transient($key, array(
        'url'     => $url,
        'started' => microtime(true),
        'phase'   => 'Connecting to your server',
        'done'    => false,
        'cached'  => false,
        'events'  => array(),
    ), AZWC_AUDIT_JOB_TTL);
}

function azwc_audit_progress_state()
{
    $key = isset($GLOBALS['azwc_audit_job']) ? $GLOBALS['azwc_audit_job'] : '';
    if (!$key) {
        return array('', null);
    }
    $state = get_transient($key);
    return array($key, is_array($state) ? $state : null);
}

/** Record one genuinely-performed request. */
function azwc_audit_progress_push($url, $status, $ms, $bytes = 0, $type = '')
{
    list($key, $state) = azwc_audit_progress_state();

    // Kept on the request as well as in the job transient, so the finished
    // report can carry its own crawl even when the browser sent no job id.
    // That is what lets a cached report show the crawl it came from instead of
    // an empty panel.
    $origin = isset($GLOBALS['azwc_audit_started']) ? $GLOBALS['azwc_audit_started'] : microtime(true);
    $event = array(
        'url'    => (string) $url,
        'status' => (int) $status,
        'ms'     => (int) $ms,
        'bytes'  => (int) $bytes,
        'type'   => (string) $type,
        'at'     => round(microtime(true) - $origin, 2),
    );

    if (!isset($GLOBALS['azwc_audit_events'])) {
        $GLOBALS['azwc_audit_events'] = array();
    }
    $GLOBALS['azwc_audit_events'][] = $event;
    if (count($GLOBALS['azwc_audit_events']) > AZWC_AUDIT_JOB_MAX_EVENTS) {
        $GLOBALS['azwc_audit_events'] = array_slice($GLOBALS['azwc_audit_events'], -AZWC_AUDIT_JOB_MAX_EVENTS);
    }

    if (!$state) {
        return;
    }
    $state['events'][] = $event;
    if (count($state['events']) > AZWC_AUDIT_JOB_MAX_EVENTS) {
        $state['events'] = array_slice($state['events'], -AZWC_AUDIT_JOB_MAX_EVENTS);
    }
    set_transient($key, $state, AZWC_AUDIT_JOB_TTL);
}

/** Name the stage of work now under way. Free text, shown verbatim. */
function azwc_audit_progress_phase($label)
{
    list($key, $state) = azwc_audit_progress_state();
    if (!$state) {
        return;
    }
    $state['phase'] = (string) $label;
    set_transient($key, $state, AZWC_AUDIT_JOB_TTL);
}

function azwc_audit_progress_finish($cached = false)
{
    list($key, $state) = azwc_audit_progress_state();
    if (!$state) {
        return;
    }
    $state['done'] = true;
    $state['cached'] = (bool) $cached;
    $state['phase'] = $cached ? 'Loaded from a recent scan' : 'Scan complete';
    set_transient($key, $state, AZWC_AUDIT_JOB_TTL);
}

/* -------------------------------------------------------------------------
 * EXECUTION LOCKING & RATE LIMITING
 * ---------------------------------------------------------------------- */
function azwc_audit_acquire_lock($url, $stage)
{
    $lock_key = 'azwc_lock_' . md5($url . '_' . $stage);
    if (get_transient($lock_key)) {
        return false;
    }
    set_transient($lock_key, true, 45);
    return $lock_key;
}

function azwc_audit_release_lock($lock_key)
{
    if ($lock_key) {
        delete_transient($lock_key);
    }
}

function azwc_audit_rate_ok()
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
    $key = 'azwc_audit_rate_' . md5($ip);
    $n = (int) get_transient($key);
    if ($n >= AZWC_AUDIT_RATE_PER_HOUR) {
        return false;
    }
    set_transient($key, $n + 1, HOUR_IN_SECONDS);
    return true;
}

/* -------------------------------------------------------------------------
 * INPUT SAFETY & NETWORK BOUNDARIES
 * ---------------------------------------------------------------------- */
function azwc_audit_normalize($input)
{
    $input = trim((string) $input);
    if ('' === $input || strlen($input) > 255) {
        return new WP_Error('azwc_bad_input', 'Enter a domain, for example example.com');
    }
    if (!preg_match('#^https?://#i', $input)) {
        $input = 'https://' . $input;
    }
    $parts = wp_parse_url($input);
    if (empty($parts['host'])) {
        return new WP_Error('azwc_bad_input', 'That does not look like a domain.');
    }
    $scheme = strtolower($parts['scheme'] ?? 'https');
    if (!in_array($scheme, array('http', 'https'), true)) {
        return new WP_Error('azwc_bad_input', 'Only http and https addresses can be checked.');
    }
    $host = strtolower($parts['host']);
    if (false === strpos($host, '.')) {
        return new WP_Error('azwc_bad_input', 'Enter a full domain, for example example.com');
    }
    $ips = azwc_audit_resolve($host);
    if (is_wp_error($ips)) {
        return $ips;
    }
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return new WP_Error('azwc_private', 'That address is on a private network and cannot be checked.');
        }
    }
    $path = $parts['path'] ?? '/';
    return $scheme . '://' . $host . ('' === $path ? '/' : $path);
}

function azwc_audit_resolve($host)
{
    $records = @dns_get_record($host, DNS_A | DNS_AAAA);
    $ips = array();
    if ($records) {
        foreach ($records as $r) {
            if (!empty($r['ip'])) {
                $ips[] = $r['ip'];
            }
            if (!empty($r['ipv6'])) {
                $ips[] = $r['ipv6'];
            }
        }
    }
    if (!$ips) {
        $resolved = gethostbyname($host);
        if ($resolved && $resolved !== $host) {
            $ips[] = $resolved;
        }
    }
    if (!$ips) {
        return new WP_Error('azwc_dns', 'That domain does not resolve. Check the spelling.');
    }
    return $ips;
}

function azwc_audit_link_host_ok($link_url)
{
    $parts = wp_parse_url($link_url);
    if (empty($parts['host'])) {
        return false;
    }
    $ips = azwc_audit_resolve($parts['host']);
    if (is_wp_error($ips)) {
        return false;
    }
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
    }
    return true;
}

function azwc_audit_probe_link($link_url)
{
    $args = array(
        'timeout' => AZWC_LINKCHECK_TIMEOUT,
        'redirection' => 4,
        'reject_unsafe_urls' => true,
        'user-agent' => 'AZWebCorpSEOAudit/' . AZWC_AUDIT_VERSION . ' (+https://azwebcorp.com/free-seo-audit/)',
        'limit_response_size' => 2048,
    );
    $started = microtime(true);
    $r = wp_remote_head($link_url, $args);
    $code = is_wp_error($r) ? 0 : (int) wp_remote_retrieve_response_code($r);
    if (0 === $code || in_array($code, array(405, 501), true)) {
        $r = wp_remote_get($link_url, $args);
        $code = is_wp_error($r) ? 0 : (int) wp_remote_retrieve_response_code($r);
    }
    azwc_audit_progress_push(
        $link_url,
        $code,
        (microtime(true) - $started) * 1000,
        0,
        is_wp_error($r) ? '' : (string) wp_remote_retrieve_header($r, 'content-type')
    );
    return $code;
}

/* -------------------------------------------------------------------------
 * FETCHING
 * ---------------------------------------------------------------------- */
function azwc_audit_fetch($url, $method = 'GET')
{
    $started = microtime(true);
    $args = array(
        'timeout' => AZWC_AUDIT_TIMEOUT,
        'redirection' => 5,
        'reject_unsafe_urls' => true,
        'method' => $method,
        'user-agent' => 'AZWebCorpSEOAudit/' . AZWC_AUDIT_VERSION . ' (+https://azwebcorp.com/free-seo-audit/)',
        'limit_response_size' => AZWC_AUDIT_MAX_BYTES,
        'headers' => array('Accept' => 'text/html,application/xhtml+xml,*/*'),
    );
    $response = wp_remote_request($url, $args);
    $elapsed = (microtime(true) - $started) * 1000;
    if (is_wp_error($response)) {
        // A failed request is still a real event, and a visitor watching the
        // crawl should see it rather than a silent gap.
        azwc_audit_progress_push($url, 0, $elapsed);
        return $response;
    }
    $body = (string) wp_remote_retrieve_body($response);
    $type = (string) wp_remote_retrieve_header($response, 'content-type');
    azwc_audit_progress_push(
        $url,
        (int) wp_remote_retrieve_response_code($response),
        $elapsed,
        strlen($body),
        $type
    );
    return array(
        'status' => (int) wp_remote_retrieve_response_code($response),
        'headers' => wp_remote_retrieve_headers($response)->getAll(),
        'body' => $body,
        'ms' => (int) round($elapsed),
    );
}

function azwc_audit_chain($url, $max = 5)
{
    $chain = array();
    $current = $url;
    for ($i = 0; $i < $max; $i++) {
        $r = wp_remote_request($current, array(
            'timeout' => AZWC_AUDIT_TIMEOUT,
            'redirection' => 0,
            'reject_unsafe_urls' => true,
            'method' => 'HEAD',
            'user-agent' => 'AZWebCorpSEOAudit/' . AZWC_AUDIT_VERSION,
        ));
        if (is_wp_error($r)) {
            break;
        }
        $code = (int) wp_remote_retrieve_response_code($r);
        $location = wp_remote_retrieve_header($r, 'location');
        $chain[] = array('url' => $current, 'status' => $code);
        if ($code < 300 || $code >= 400 || !$location) {
            break;
        }
        $current = 0 === strpos($location, 'http') ? $location : untrailingslashit($current) . '/' . ltrim($location, '/');
    }
    return $chain;
}

/* -------------------------------------------------------------------------
 * DOM UTILITIES
 * ---------------------------------------------------------------------- */
function azwc_audit_dom_parser($html)
{
    if (!class_exists('DOMDocument')) {
        return null;
    }
    $libxml_state = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($libxml_state);
    if (!$loaded) {
        return null;
    }
    return new DOMXPath($dom);
}

function azwc_audit_plain_english($id)
{
    static $map = array(
        'indexable' => 'Search engines have to be allowed to list a page before it can ever show up in results.',
        'robots_txt' => 'robots.txt is a small file at the root of your site telling search engines which areas they may look at.',
        'sitemap' => 'A sitemap is a machine-readable list of every page you want found.',
        'canonical' => 'A canonical tag helps search engines identify the primary version of a page.',
        'https' => 'HTTPS encrypts connection traffic and protects visitor data integrity.',
        'redirects' => 'Tracks redirect hops between the initial request and final URL.',
        'mixed_content' => 'Checks for unencrypted HTTP asset references on an HTTPS page.',
        'compression' => 'Server-level HTTP compression reduces byte transfer sizes.',
        'html_size' => 'Measures the total HTML byte weight before asset downloads.',
        'title' => 'Evaluates page title length and search snippet readability.',
        'description' => 'Checks for meta description tags and character thresholds.',
        'h1' => 'Verifies proper H1 heading usage across the document.',
        'content_depth' => 'Measures total visible body word volume.',
        'image_alt' => 'Checks images for screen reader and search alt text attributes.',
        'viewport' => 'Ensures mobile viewport declarations exist for responsive layout rendering.',
        'lang' => 'Verifies primary document language attributes.',
        'structured_data' => 'Validates presence of Schema.org JSON-LD blocks.',
        'open_graph' => 'Checks Open Graph title and image tags for social preview rendering.',
        'favicon' => 'Confirms favicon icon tag declarations.',
        'links' => 'Evaluates internal link density and navigation connectivity.',
        'heading_order' => 'Checks sequential heading level ordering across the DOM.',
        'charset' => 'Confirms document character encoding declarations.',
        'twitter_card' => 'Checks Twitter/X social card markup declarations.',
        'img_dimensions' => 'Checks explicit image width and height attributes to prevent layout shifts.',
        'lazy_images' => 'Checks image lazy loading usage and hero image configuration.',
        'blank_noopener' => 'Checks for rel="noopener" on new-tab link targets.',
        'empty_links' => 'Identifies empty or anchor-only link placeholders.',
        'security_headers' => 'Evaluates presence of active HTTP transport security headers.',
        'broken_links' => 'Probes a sample of page links for HTTP errors.',
        'structured_data_valid' => 'Validates syntax within JSON-LD script blocks.',
        'sitemap_freshness' => 'Parses sitemap lastmod dates to evaluate crawl freshness.',
        'hreflang' => 'Evaluates internationalization hreflang tags for duplicate conflicts.',
        'resource_hints' => 'Advanced code that speeds up browser connection times for critical site files.',
        'deprecated_html' => 'Checks for obsolete code that causes modern mobile devices to render slowly.',
        'form_accessibility' => 'Ensures visitors with disabilities or screen readers can use your contact forms.',
        'apple_touch_icon' => 'A custom brand logo for users who save your website to an iPhone/iPad screen.',
        'meta_keywords' => 'Legacy metadata ignored by Google since 2009. Having this indicates an outdated strategy.',
        'og_image_reachable' => 'Verifies that the Open Graph image URL actually loads.',
        'favicon_reachable' => 'Confirms the favicon file is accessible.',
        'viewport_valid' => 'Checks that the viewport meta tag is configured correctly for mobile.',
        'script_attributes' => 'Ensures external scripts use defer or async to avoid render-blocking.',
    );
    return isset($map[$id]) ? $map[$id] : '';
}

function azwc_audit_check($id, $label, $status, $detail, $weight = 1, $group = 'technical', $items = array())
{
    $plain = azwc_audit_plain_english($id);
    return compact('id', 'label', 'status', 'detail', 'plain', 'weight', 'group', 'items');
}

function azwc_audit_match_all($pattern, $subject)
{
    $m = array();
    $ok = @preg_match_all($pattern, $subject, $m);
    if (false === $ok || !is_array($m) || !isset($m[0]) || !is_array($m[0])) {
        return array(array(), array());
    }
    if (!isset($m[1]) || !is_array($m[1])) {
        $m[1] = array();
    }
    return $m;
}

function azwc_audit_abs_url($ref, $base)
{
    $ref = trim(html_entity_decode($ref, ENT_QUOTES));
    if ('' === $ref || 0 === stripos($ref, 'data:')) {
        return '';
    }
    if (preg_match('#^https?://#i', $ref)) {
        return $ref;
    }
    $p = wp_parse_url($base);
    if (empty($p['host'])) {
        return '';
    }
    $scheme = $p['scheme'] ?? 'https';
    if (0 === strpos($ref, '//')) {
        return $scheme . ':' . $ref;
    }
    $origin = $scheme . '://' . $p['host'];
    if (0 === strpos($ref, '/')) {
        return $origin . $ref;
    }
    $dir = rtrim(dirname($p['path'] ?? '/'), '/\\');
    return $origin . $dir . '/' . $ref;
}

function azwc_audit_strip_blocks($html)
{
    foreach (array('script', 'style', 'noscript', 'template', 'svg') as $tag) {
        $offset = 0;
        while (false !== ($start = stripos($html, '<' . $tag, $offset))) {
            $close = stripos($html, '</' . $tag, $start);
            if (false === $close) {
                $html = substr($html, 0, $start);
                break;
            }
            $end = strpos($html, '>', $close);
            $end = false === $end ? strlen($html) : $end + 1;
            $html = substr($html, 0, $start) . ' ' . substr($html, $end);
            $offset = $start + 1;
        }
    }
    return $html;
}

function azwc_audit_text($html)
{
    $stripped = azwc_audit_strip_blocks($html);
    $stripped = str_replace('<', ' <', $stripped);
    $plain = wp_strip_all_tags($stripped);
    $collapsed = preg_replace('/\s+/', ' ', $plain);
    return trim(null === $collapsed ? $plain : $collapsed);
}

/* -------------------------------------------------------------------------
 * CORE AUDIT ENGINE (All Measurable Checks)
 * ---------------------------------------------------------------------- */
function azwc_audit_run_checks($url, $page, $chain)
{
    $html = $page['body'];
    $headers = array_change_key_case($page['headers']);
    $checks = array();
    $parts = wp_parse_url($url);
    $host = $parts['host'];
    $xpath = azwc_audit_dom_parser($html);

    /* --- Indexability --- */
    $robots_meta = '';
    if (preg_match('#<meta[^>]+name=["\']robots["\'][^>]*content=["\']([^"\']+)#i', $html, $m)) {
        $robots_meta = strtolower($m[1]);
    }
    $x_robots = strtolower(is_array($headers['x-robots-tag'] ?? '') ? implode(',', $headers['x-robots-tag']) : ($headers['x-robots-tag'] ?? ''));
    $noindexed = (false !== strpos($robots_meta, 'noindex')) || (false !== strpos($x_robots, 'noindex'));
    $checks[] = azwc_audit_check(
        'indexable',
        'Search engines are allowed to index this page',
        $noindexed ? 'fail' : 'pass',
        $noindexed
            ? 'A noindex directive is present, which removes this page from search results entirely.'
            : 'No noindex directive found in the meta robots tag or X-Robots-Tag header.',
        4,
        'indexability'
    );

    azwc_audit_progress_phase('Reading robots.txt');
    $robots_txt = azwc_audit_fetch($parts['scheme'] . '://' . $host . '/robots.txt');
    $has_robots = !is_wp_error($robots_txt) && 200 === $robots_txt['status'];
    $rt_body = $has_robots ? $robots_txt['body'] : '';
    $blocks_all = (bool) preg_match('/^\s*disallow:\s*\/\s*$/im', $rt_body);
    $checks[] = azwc_audit_check(
        'robots_txt',
        'robots.txt is present and not blocking the site',
        $blocks_all ? 'fail' : ($has_robots ? 'pass' : 'warn'),
        $blocks_all
            ? 'robots.txt contains "Disallow: /", which blocks all crawling.'
            : ($has_robots ? 'robots.txt found and permits crawling.' : 'No robots.txt found at origin.'),
        2,
        'indexability'
    );

    /* Sitemap */
    $sitemap_in_robots = (bool) preg_match('/^\s*sitemap:\s*(\S+)/im', $rt_body, $sm);
    $sitemap_url = $sitemap_in_robots ? trim($sm[1]) : $parts['scheme'] . '://' . $host . '/sitemap.xml';
    azwc_audit_progress_phase('Reading XML sitemap');
    $sitemap = azwc_audit_fetch($sitemap_url);
    $has_sitemap = !is_wp_error($sitemap) && 200 === $sitemap['status'] && false !== stripos($sitemap['body'], '<');
    $sitemap_urls = $has_sitemap ? substr_count($sitemap['body'], '<loc>') : 0;
    $is_index = $has_sitemap && false !== stripos($sitemap['body'], '<sitemapindex');
    $child_page_count = 0;
    if ($is_index) {
        preg_match_all('#<sitemap>\s*<loc>\s*([^<\s]+)#i', $sitemap['body'], $sm_matches);
        $children = array_slice($sm_matches[1] ?? array(), 0, 3);
        foreach ($children as $child_url) {
            $c_res = azwc_audit_fetch(trim($child_url));
            if (!is_wp_error($c_res) && 200 === $c_res['status']) {
                $child_page_count += substr_count($c_res['body'], '<loc>');
            }
        }
    }
    $sitemap_label = !$has_sitemap
        ? ''
        : ($is_index
            ? sprintf('Sitemap index found at %s, referencing %d child sitemaps (%d URLs sampled across children).', $sitemap_url, $sitemap_urls, $child_page_count)
            : sprintf('Sitemap found at %s listing %d URL%s.', $sitemap_url, $sitemap_urls, 1 === $sitemap_urls ? '' : 's'));
    $checks[] = azwc_audit_check(
        'sitemap',
        'An XML sitemap is reachable',
        $has_sitemap ? 'pass' : 'warn',
        $has_sitemap ? $sitemap_label : 'No XML sitemap found at /sitemap.xml or declared in robots.txt.',
        2,
        'indexability'
    );

    /* Sitemap Freshness */
    $sitemap_lastmods = array();
    if ($has_sitemap) {
        preg_match_all('#<lastmod>\s*([^<\s]+)#i', $sitemap['body'], $lm);
        foreach ($lm[1] as $raw) {
            $ts = strtotime(trim($raw));
            if ($ts) {
                $sitemap_lastmods[] = $ts;
            }
        }
    }
    if ($sitemap_lastmods) {
        $newest = max($sitemap_lastmods);
        $days = (int) floor((time() - $newest) / DAY_IN_SECONDS);
        $future = $days < 0;
        $abs = abs($days);
        $checks[] = azwc_audit_check(
            'sitemap_freshness',
            'The sitemap reflects recent activity',
            $future ? 'warn' : ($abs <= 30 ? 'pass' : ($abs <= AZWC_SITEMAP_STALE_DAYS ? 'warn' : 'fail')),
            $future
                ? sprintf('Newest <lastmod> date is %s (in future).', gmdate('Y-m-d', $newest))
                : sprintf('Newest <lastmod> date in sitemap is %s (%d day%s ago).', gmdate('Y-m-d', $newest), $abs, 1 === $abs ? '' : 's'),
            1,
            'indexability'
        );
    } elseif ($has_sitemap) {
        $checks[] = azwc_audit_check(
            'sitemap_freshness',
            'The sitemap reflects recent activity',
            'info',
            'The sitemap does not specify <lastmod> dates.',
            1,
            'indexability'
        );
    }

    $canonical = '';
    if (preg_match('#<link[^>]+rel=["\']canonical["\'][^>]*href=["\']([^"\']+)#i', $html, $m)) {
        $canonical = trim($m[1]);
    }
    $checks[] = azwc_audit_check(
        'canonical',
        'A canonical URL is declared',
        $canonical ? 'pass' : 'warn',
        $canonical ? 'Canonical URL points to ' . $canonical : 'No canonical tag found.',
        2,
        'indexability'
    );

    /* --- Technical Transport --- */
    $is_https = 'https' === $parts['scheme'];
    $checks[] = azwc_audit_check(
        'https',
        'The site is served over HTTPS',
        $is_https ? 'pass' : 'fail',
        $is_https ? 'HTTPS connection active and verified.' : 'Site reached over unencrypted HTTP.',
        3,
        'technical'
    );

    $hops = max(0, count($chain) - 1);
    $checks[] = azwc_audit_check(
        'redirects',
        'Redirects are kept short',
        $hops <= 1 ? 'pass' : ($hops <= 2 ? 'warn' : 'fail'),
        0 === $hops ? 'Resolves directly with zero redirects.' : sprintf('%d redirect hop(s) detected.', $hops),
        2,
        'technical',
        $hops > 0 ? wp_list_pluck($chain, 'url') : array()
    );

    // Mixed content only ever applies to a RESOURCE THE PAGE LOADS ITSELF —
    // an <img>/<script>/<iframe> src, or a <link> (stylesheet/icon) href.
    // A plain <a href="http://..."> is a navigation destination a visitor
    // has to click; browsers never treat that as mixed content, so matching
    // bare href= here (the original regex) mislabels an ordinary broken
    // link — e.g. a mistyped tel: number that WordPress's URL sanitizer
    // turned into http://+1... — as a security finding it is not.
    $mixed = 0;
    $mixed_items = array();
    if ($is_https) {
        $mixed_matches = array();
        if (preg_match_all('#\bsrc\s*=\s*["\'](http://[^"\']+)#i', $html, $mm)) {
            $mixed_matches = array_merge($mixed_matches, $mm[1]);
        }
        if (preg_match_all('#<link\b[^>]+\bhref\s*=\s*["\'](http://[^"\']+)#i', $html, $mm)) {
            $mixed_matches = array_merge($mixed_matches, $mm[1]);
        }
        $mixed_items = array_slice(array_values(array_unique($mixed_matches)), 0, 12);
        $mixed = count($mixed_items);
    }
    $checks[] = azwc_audit_check(
        'mixed_content',
        'No insecure resources on a secure page',
        $mixed > 0 ? 'fail' : 'pass',
        $mixed > 0 ? sprintf('%d insecure HTTP asset resource(s) referenced on HTTPS page.', $mixed) : 'All referenced assets use HTTPS.',
        2,
        'technical',
        $mixed_items
    );

    $compressed = !empty($headers['content-encoding']);
    $checks[] = azwc_audit_check(
        'compression',
        'The HTML is compressed in transit',
        $compressed ? 'pass' : 'warn',
        $compressed ? 'Content-Encoding: ' . (is_array($headers['content-encoding']) ? implode(',', $headers['content-encoding']) : $headers['content-encoding']) : 'No Content-Encoding header found.',
        1,
        'technical'
    );

    $bytes = strlen($html);
    $checks[] = azwc_audit_check(
        'html_size',
        'HTML document size is reasonable',
        $bytes < 150000 ? 'pass' : ($bytes < 400000 ? 'warn' : 'fail'),
        sprintf('Document payload size is %s (%d ms response time).', size_format($bytes), $page['ms']),
        1,
        'technical'
    );

    /* --- On-Page Structure --- */
    $title = '';
    if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
        $title = trim(html_entity_decode(wp_strip_all_tags($m[1])));
    }
    $tlen = mb_strlen($title);
    $checks[] = azwc_audit_check(
        'title',
        'The page has a title of usable length',
        '' === $title ? 'fail' : (($tlen >= 20 && $tlen <= 60) ? 'pass' : 'warn'),
        '' === $title ? 'No title tag present.' : sprintf('%d characters: "%s"', $tlen, $title),
        3,
        'onpage'
    );

    $desc = '';
    if (preg_match('#<meta[^>]+name=["\']description["\'][^>]*content=["\']([^"\']*)#i', $html, $m)) {
        $desc = trim(html_entity_decode($m[1]));
    }
    $dlen = mb_strlen($desc);
    $checks[] = azwc_audit_check(
        'description',
        'A meta description is present',
        '' === $desc ? 'fail' : (($dlen >= 70 && $dlen <= 165) ? 'pass' : 'warn'),
        '' === $desc ? 'No meta description tag present.' : sprintf('%d characters in description.', $dlen),
        2,
        'onpage'
    );

    preg_match_all('#<h1[^>]*>(.*?)</h1>#is', $html, $h1s);
    $h1_count = count($h1s[0]);
    $checks[] = azwc_audit_check(
        'h1',
        'Exactly one H1 heading',
        1 === $h1_count ? 'pass' : (0 === $h1_count ? 'fail' : 'warn'),
        0 === $h1_count ? 'No H1 tag found.' : sprintf('%d H1 tag(s) detected.', $h1_count),
        2,
        'onpage'
    );

    $words = str_word_count(azwc_audit_text($html));
    $checks[] = azwc_audit_check(
        'content_depth',
        'The page has substantive content',
        $words >= 300 ? 'pass' : ($words >= 120 ? 'warn' : 'fail'),
        sprintf('%d words of visible content text detected.', $words),
        2,
        'onpage'
    );

    preg_match_all('#<img\b[^>]*>#i', $html, $imgs);
    $img_total = count($imgs[0]);
    $img_noalt = 0;
    $img_items = array();
    foreach ($imgs[0] as $img) {
        if (!preg_match('#\balt\s*=\s*["\'][^"\']*[^\s"\']#i', $img)) {
            $img_noalt++;
            if (count($img_items) < 12 && preg_match('#\bsrc\s*=\s*["\']([^"\']+)#i', $img, $sm)) {
                $abs = azwc_audit_abs_url($sm[1], $url);
                if ($abs) {
                    $img_items[] = $abs;
                }
            }
        }
    }
    $checks[] = azwc_audit_check(
        'image_alt',
        'Images carry alt text',
        0 === $img_total ? 'info' : (0 === $img_noalt ? 'pass' : ($img_noalt / $img_total < 0.25 ? 'warn' : 'fail')),
        0 === $img_total ? 'No images found.' : sprintf('%d of %d images missing alt attributes.', $img_noalt, $img_total),
        1,
        'onpage',
        $img_items
    );

    $viewport = (bool) preg_match('#<meta[^>]+name=["\']viewport["\']#i', $html);
    $checks[] = azwc_audit_check(
        'viewport',
        'A mobile viewport is declared',
        $viewport ? 'pass' : 'fail',
        $viewport ? 'Viewport meta tag detected.' : 'Missing mobile viewport tag.',
        3,
        'onpage'
    );

    /* --- NEW: Viewport Content Validation --- */
    $viewport_valid = false;
    if ($viewport) {
        preg_match('#<meta[^>]+name=["\']viewport["\'][^>]*content=["\']([^"\']+)#i', $html, $vm);
        if (!empty($vm[1])) {
            $content = strtolower($vm[1]);
            if (strpos($content, 'width=device-width') !== false && strpos($content, 'initial-scale') !== false) {
                $viewport_valid = true;
            }
        }
    }
    $checks[] = azwc_audit_check(
        'viewport_valid',
        'Viewport is configured correctly for mobile',
        $viewport_valid ? 'pass' : ($viewport ? 'warn' : 'fail'),
        $viewport_valid ? 'Viewport has width=device-width and initial-scale set.' : ($viewport ? 'Viewport exists but lacks width=device-width or initial-scale.' : 'No viewport meta tag.'),
        2,
        'onpage'
    );

    $lang = (bool) preg_match('#<html[^>]+lang=["\'][a-z]#i', $html);
    $checks[] = azwc_audit_check(
        'lang',
        'The page declares its language',
        $lang ? 'pass' : 'warn',
        $lang ? 'Language declared on HTML element.' : 'No lang attribute on HTML tag.',
        1,
        'onpage'
    );

    /* --- Structured Data & Metadata --- */
    preg_match_all('#<script[^>]+application/ld\+json[^>]*>(.*?)</script>#is', $html, $ld);
    $types = array();
    $ld_invalid = array();
    foreach ($ld[1] as $block) {
        $trimmed = trim($block);
        if ('' === $trimmed) {
            continue;
        }
        $data = json_decode($trimmed, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            $snippet = mb_substr(preg_replace('/\s+/', ' ', $trimmed), 0, 140);
            $ld_invalid[] = $snippet . (mb_strlen($trimmed) > 140 ? '…' : '');
            continue;
        }
        if (!is_array($data)) {
            continue;
        }
        array_walk_recursive($data, function ($v, $k) use (&$types) {
            if ('@type' === $k && is_string($v)) {
                $types[$v] = true;
            }
        });
    }
    $types = array_keys($types);
    $checks[] = azwc_audit_check(
        'structured_data',
        'Structured data is present',
        $types ? 'pass' : 'warn',
        $types ? 'JSON-LD types detected: ' . implode(', ', array_slice($types, 0, 8)) : 'No JSON-LD structured data found.',
        2,
        'structured'
    );

    $ld_total = count(array_filter($ld[1], function ($b) {
        return '' !== trim($b);
    }));
    if ($ld_total > 0) {
        $checks[] = azwc_audit_check(
            'structured_data_valid',
            'Structured data parses correctly',
            empty($ld_invalid) ? 'pass' : 'fail',
            empty($ld_invalid) ? sprintf('All %d JSON-LD block(s) parsed cleanly.', $ld_total) : sprintf('%d JSON-LD block(s) failed parsing.', count($ld_invalid)),
            2,
            'structured',
            $ld_invalid
        );
    }

    $og = (bool) preg_match('#<meta[^>]+property=["\']og:title["\']#i', $html);
    $og_img = (bool) preg_match('#<meta[^>]+property=["\']og:image["\']#i', $html);
    $azw_og_label = ($og && $og_img) ? 'Social sharing tags are set' : ($og ? 'Social sharing image is missing' : 'Social sharing tags are missing');
    $checks[] = azwc_audit_check(
        'open_graph',
        $azw_og_label,
        ($og && $og_img) ? 'pass' : ($og ? 'warn' : 'fail'),
        ($og && $og_img) ? 'Open Graph title and image tags present.' : 'Open Graph sharing metadata incomplete.',
        1,
        'structured'
    );

    /* --- NEW: OG Image Reachability --- */
    $og_image_url = '';
    if ($og_img) {
        preg_match('#<meta[^>]+property=["\']og:image["\'][^>]*content=["\']([^"\']+)#i', $html, $ogm);
        if (!empty($ogm[1])) {
            $og_image_url = trim($ogm[1]);
        }
    }
    $og_image_ok = false;
    if (!empty($og_image_url)) {
        azwc_audit_progress_phase('Checking social preview image');
        $img_check = azwc_audit_fetch($og_image_url, 'HEAD');
        if (!is_wp_error($img_check) && $img_check['status'] === 200) {
            $og_image_ok = true;
        }
    }
    $checks[] = azwc_audit_check(
        'og_image_reachable',
        'Open Graph image is accessible',
        $og_image_ok ? 'pass' : ($og_image_url ? 'warn' : 'info'),
        $og_image_ok ? 'OG image loads successfully.' : ($og_image_url ? 'OG image URL does not return a 200 status.' : 'No OG image defined.'),
        1,
        'structured'
    );

    $favicon = (bool) preg_match('#<link[^>]+rel=["\'][^"\']*icon#i', $html);
    $checks[] = azwc_audit_check(
        'favicon',
        'A favicon is declared',
        $favicon ? 'pass' : 'warn',
        $favicon ? 'Favicon link tag detected.' : 'No favicon link tag declared.',
        1,
        'structured'
    );

    /* --- NEW: Favicon Reachability --- */
    $favicon_url = '';
    if ($favicon) {
        preg_match('#<link[^>]+rel=["\'][^"\']*icon["\'][^>]*href=["\']([^"\']+)#i', $html, $fm);
        if (!empty($fm[1])) {
            $favicon_url = azwc_audit_abs_url($fm[1], $url);
        }
    }
    $favicon_ok = false;
    if (!empty($favicon_url)) {
        $fav_check = azwc_audit_fetch($favicon_url, 'HEAD');
        if (!is_wp_error($fav_check) && $fav_check['status'] === 200) {
            $favicon_ok = true;
        }
    }
    $checks[] = azwc_audit_check(
        'favicon_reachable',
        'Favicon file loads successfully',
        $favicon_ok ? 'pass' : ($favicon_url ? 'warn' : 'info'),
        $favicon_ok ? 'Favicon is accessible.' : ($favicon_url ? 'Favicon URL does not return a 200 status.' : 'No favicon defined.'),
        1,
        'structured'
    );

    /* --- Link Analysis & Broken Links --- */
    preg_match_all('~<a\b[^>]+href\s*=\s*["\']([^"\']+)["\']~i', $html, $links);
    $internal = 0;
    $external = 0;
    foreach ($links[1] as $href) {
        if (0 === strpos($href, '/') || false !== stripos($href, $host)) {
            $internal++;
        } elseif (preg_match('#^https?://#i', $href)) {
            $external++;
        }
    }
    $checks[] = azwc_audit_check(
        'links',
        'The page links onward into the site',
        $internal >= 5 ? 'pass' : ($internal >= 1 ? 'warn' : 'fail'),
        sprintf('%d internal and %d external links detected.', $internal, $external),
        1,
        'onpage'
    );

    $link_targets = array();
    foreach ($links[1] as $href) {
        $href = trim($href);
        if ('' === $href || 0 === strpos($href, '#') || preg_match('#^(mailto|tel|javascript):#i', $href)) {
            continue;
        }
        $abs = azwc_audit_abs_url($href, $url);
        if ($abs && preg_match('#^https?://#i', $abs)) {
            $link_targets[$abs] = true;
        }
    }
    $link_targets = array_keys($link_targets);

    $link_sample = array();
    foreach ($link_targets as $target) {
        if (count($link_sample) >= AZWC_LINKCHECK_MAX) {
            break;
        }
        if (azwc_audit_link_host_ok($target)) {
            $link_sample[] = $target;
        }
    }

    $broken_links = array();
    $unverified = 0;
    $probed = 0;
    $probe_started = microtime(true);
    azwc_audit_progress_phase('Probing internal links');
    foreach ($link_sample as $target) {
        // Stop on the budget rather than the list length. A handful of links
        // that each sit on the 6s timeout would otherwise run the request past
        // its execution limit and lose the entire audit.
        if (microtime(true) - $probe_started > AZWC_LINKCHECK_BUDGET) {
            break;
        }
        $code = azwc_audit_probe_link($target);
        $probed++;

        // 401/403/429 mean the crawler was refused, not that the link is
        // broken: Instagram and LinkedIn both answer 429 to an automated HEAD
        // while loading perfectly for a visitor. Reporting those as broken
        // links to a prospective client is a false accusation about their own
        // site, so they are counted separately and never listed as failures.
        if (in_array($code, array(401, 403, 429), true)) {
            $unverified++;
            continue;
        }
        if (0 === $code || $code >= 400) {
            $broken_links[] = $target . ' — ' . ($code ?: 'no response');
        }
    }
    if ($probed) {
        // Report against what was actually reached, never against what was
        // queued, or a truncated run would overstate the coverage.
        $checked = $probed - $unverified;
        $detail = sprintf(
            'Sampled %d links: %s.',
            $probed,
            empty($broken_links) ? 'all loaded successfully' : count($broken_links) . ' failed'
        );
        if ($unverified) {
            $detail .= sprintf(
                ' %d could not be verified because the host refused an automated request (usually a social network); those are not counted as broken.',
                $unverified
            );
        }
        $checks[] = azwc_audit_check(
            'broken_links',
            empty($broken_links) ? 'Sampled links all work' : 'Some sampled links are broken',
            empty($broken_links) ? 'pass' : (($checked > 0 && count($broken_links) / $checked < 0.34) ? 'warn' : 'fail'),
            $detail,
            2,
            'onpage',
            $broken_links
        );
    }

    /* --- Additional Diagnostics --- */
    $hm = azwc_audit_match_all('#<h([1-6])\b#i', $html);
    $levels = array_map('intval', $hm[1]);
    $skips = array();
    $prev = 0;
    foreach ($levels as $lv) {
        if ($prev && $lv > $prev + 1) {
            $skips[] = 'h' . $prev . ' followed by h' . $lv;
        }
        $prev = $lv;
    }
    $checks[] = azwc_audit_check(
        'heading_order',
        'Headings run in order without skipping levels',
        $skips ? 'warn' : 'pass',
        $skips ? sprintf('%d skipped heading level(s) detected.', count($skips)) : sprintf('%d headings ordered correctly.', count($levels)),
        1,
        'onpage',
        array_slice($skips, 0, 10)
    );

    $has_charset = (bool) preg_match('#<meta[^>]+charset#i', $html);
    $checks[] = azwc_audit_check(
        'charset',
        'Character encoding is declared',
        $has_charset ? 'pass' : 'warn',
        $has_charset ? 'Meta charset declaration present.' : 'No character set declared.',
        1,
        'technical'
    );

    $tw = (bool) preg_match('#<meta[^>]+name=["\']twitter:card["\']#i', $html);
    $checks[] = azwc_audit_check(
        'twitter_card',
        'Twitter/X card tags are set',
        $tw ? 'pass' : 'warn',
        $tw ? 'twitter:card tag detected.' : 'No twitter:card meta tag found.',
        1,
        'structured'
    );

    /* Image Dimensions */
    $dim_missing = 0;
    $no_dims = array();
    foreach ($imgs[0] as $img) {
        if (!preg_match('#\bwidth\s*=#i', $img) || !preg_match('#\bheight\s*=#i', $img)) {
            $dim_missing++;
            if (count($no_dims) < 12 && preg_match('#\bsrc\s*=\s*["\']([^"\']+)#i', $img, $dm)) {
                $abs = azwc_audit_abs_url($dm[1], $url);
                if ($abs) {
                    $no_dims[] = $abs;
                }
            }
        }
    }
    $checks[] = azwc_audit_check(
        'img_dimensions',
        'Images declare width and height',
        0 === $img_total ? 'info' : (0 === $dim_missing ? 'pass' : ($dim_missing / $img_total < 0.3 ? 'warn' : 'fail')),
        0 === $img_total ? 'No images present.' : sprintf('%d of %d images lack width/height attributes.', $dim_missing, $img_total),
        1,
        'technical',
        $no_dims
    );

    /* Image Lazy Loading */
    $lazy = 0;
    $hero_lazy_warning = false;
    foreach ($imgs[0] as $idx => $img) {
        $is_lazy = (bool) preg_match('#\bloading\s*=\s*["\']lazy["\']#i', $img);
        $is_hero_candidate = (0 === $idx) || (bool) preg_match('#\b(hero|banner|featured|header-img)\b#i', $img);
        if ($is_lazy) {
            $lazy++;
        }
        if ($is_hero_candidate && $is_lazy) {
            $hero_lazy_warning = true;
        }
    }
    $azw_lazy_heavy = $img_total >= AZWC_LAZY_MANY;
    $azw_lazy_pct = $img_total > 0 ? (int) round($lazy / $img_total * 100) : 0;
    if (0 === $img_total) {
        $azw_lazy_status = 'info';
        $azw_lazy_detail = 'No images present on page.';
    } elseif ($hero_lazy_warning) {
        $azw_lazy_status = 'warn';
        $azw_lazy_detail = 'An above-the-fold or hero image contains loading="lazy". Lazy loading initial viewport images delays Largest Contentful Paint (LCP).';
    } elseif (0 === $lazy) {
        $azw_lazy_status = $azw_lazy_heavy ? 'warn' : 'info';
        $azw_lazy_detail = sprintf('%d images present, zero using loading="lazy".', $img_total);
    } elseif ($azw_lazy_heavy && $azw_lazy_pct < AZWC_LAZY_MIN_PCT) {
        $azw_lazy_status = 'warn';
        $azw_lazy_detail = sprintf('%d of %d images use loading="lazy" (%d%% coverage).', $lazy, $img_total, $azw_lazy_pct);
    } else {
        $azw_lazy_status = 'pass';
        $azw_lazy_detail = sprintf('%d of %d images use native lazy loading (%d%% coverage).', $lazy, $img_total, $azw_lazy_pct);
    }
    $checks[] = azwc_audit_check(
        'lazy_images',
        'Image lazy loading structural configuration',
        $azw_lazy_status,
        $azw_lazy_detail,
        1,
        'technical'
    );

    /* Rel Noopener */
    $blanks = azwc_audit_match_all('#<a\b[^>]*target\s*=\s*["\']_blank["\'][^>]*>#i', $html);
    $unsafe = array();
    foreach ($blanks[0] as $a) {
        if (!preg_match('#\brel\s*=\s*["\'][^"\']*noopener#i', $a)) {
            if (count($unsafe) < 10 && preg_match('#\bhref\s*=\s*["\']([^"\']+)#i', $a, $hm2)) {
                $unsafe[] = azwc_audit_abs_url($hm2[1], $url) ?: $hm2[1];
            }
        }
    }
    $checks[] = azwc_audit_check(
        'blank_noopener',
        'New-tab links are opened safely',
        $unsafe ? 'warn' : 'pass',
        $unsafe ? sprintf('%d target="_blank" link(s) missing rel="noopener".', count($unsafe)) : 'All new-tab links specify rel="noopener".',
        1,
        'technical',
        $unsafe
    );

    /* Empty Links */
    $empties = azwc_audit_match_all('#<a\b[^>]*href\s*=\s*["\'](#|)["\'][^>]*>#i', $html);
    $empty_n = count($empties[0]);
    $checks[] = azwc_audit_check(
        'empty_links',
        'No placeholder or empty links',
        $empty_n > 0 ? 'warn' : 'pass',
        $empty_n > 0 ? sprintf('%d empty or placeholder link(s) found.', $empty_n) : 'All links carry valid destination targets.',
        1,
        'onpage'
    );

    /* Security Headers */
    $hdr = function ($name) use ($headers) {
        $v = $headers[$name] ?? '';
        return is_array($v) ? implode(',', $v) : (string) $v;
    };
    $sec_present = array();
    $sec_missing = array();
    $sec_map = array(
        'strict-transport-security' => 'HSTS',
        'x-content-type-options' => 'X-Content-Type-Options',
        'x-frame-options' => 'X-Frame-Options',
        'referrer-policy' => 'Referrer-Policy',
        'content-security-policy' => 'Content-Security-Policy',
        'permissions-policy' => 'Permissions-Policy',
        'cross-origin-embedder-policy' => 'Cross-Origin-Embedder-Policy',
    );
    foreach ($sec_map as $key => $label) {
        if ('' !== $hdr($key)) {
            $sec_present[] = $label;
        } else {
            $sec_missing[] = $label;
        }
    }
    $checks[] = azwc_audit_check(
        'security_headers',
        'Common security headers are sent',
        empty($sec_missing) ? 'pass' : (count($sec_missing) <= 3 ? 'warn' : 'fail'),
        empty($sec_missing)
            ? 'All seven verified security headers are active: ' . implode(', ', $sec_present) . '.'
            : sprintf('Present: %s. Missing: %s.', $sec_present ? implode(', ', $sec_present) : 'none', implode(', ', $sec_missing)),
        1,
        'technical'
    );

    /* --- Version 2 Enhanced Checks --- */
    // Deprecated HTML
    $bad_tags = array('center', 'font', 'marquee', 'blink');
    $found_tags = array();
    foreach ($bad_tags as $tag) {
        if (stripos($html, "<$tag") !== false) {
            $found_tags[] = "<$tag>";
        }
    }
    $checks[] = azwc_audit_check(
        'deprecated_html',
        'Legacy markup',
        empty($found_tags) ? 'pass' : 'warn',
        empty($found_tags) ? 'Using modern HTML5 markup.' : 'Obsolete tags detected: ' . implode(', ', $found_tags),
        1,
        'technical'
    );

    // Resource Hints
    $has_preconnect = stripos($html, 'rel="preconnect"') !== false;
    $has_preload = stripos($html, 'rel="preload"') !== false;
    $has_resource_hints = $has_preconnect || $has_preload;
    $checks[] = azwc_audit_check(
        'resource_hints',
        'Resource hints for performance',
        $has_resource_hints ? 'pass' : 'info',
        $has_resource_hints ? 'Resource hinting active.' : 'No resource hints found (preconnect/preload).',
        1,
        'technical'
    );

    // Form Accessibility
    $has_input = stripos($html, '<input') !== false;
    $has_label = stripos($html, '<label') !== false;
    if ($has_input) {
        $checks[] = azwc_audit_check(
            'form_accessibility',
            'Form input accessibility',
            $has_label ? 'pass' : 'fail',
            $has_label ? 'Forms have associated labels.' : 'Forms found with no label elements.',
            1,
            'accessibility'
        );
    }

    // Apple Touch Icon
    $has_apple = (bool) preg_match('#<link[^>]+rel=["\']apple-touch-icon#i', $html);
    $checks[] = azwc_audit_check(
        'apple_touch_icon',
        'Apple Touch Icon',
        $has_apple ? 'pass' : 'info',
        $has_apple ? 'Apple touch icon declared.' : 'No Apple touch icon found.',
        1,
        'branding'
    );

    // Meta Keywords
    $has_meta_keywords = (bool) preg_match('#<meta[^>]+name=["\']keywords["\']#i', $html);
    if ($has_meta_keywords) {
        $checks[] = azwc_audit_check(
            'meta_keywords',
            'Meta keywords tag',
            'warn',
            'Meta keywords tag detected. Google has ignored this since 2009.',
            1,
            'technical'
        );
    }

    // Hreflang
    $has_hreflang = (bool) preg_match('#<link[^>]+rel=["\']alternate["\'][^>]*hreflang#i', $html);
    $checks[] = azwc_audit_check(
        'hreflang',
        'International hreflang tags',
        $has_hreflang ? 'pass' : 'info',
        $has_hreflang ? 'Hreflang tags detected for internationalization.' : 'No hreflang tags found.',
        1,
        'internationalization'
    );

    /* --- NEW: Script defer/async detection --- */
    preg_match_all('#<script\b[^>]*src\s*=\s*["\']([^"\']+)["\'][^>]*>#i', $html, $scripts);
    $script_count = count($scripts[0]);
    $script_with_async = 0;
    $script_with_defer = 0;
    foreach ($scripts[0] as $tag) {
        if (preg_match('#\basync\b#i', $tag)) {
            $script_with_async++;
        }
        if (preg_match('#\bdefer\b#i', $tag)) {
            $script_with_defer++;
        }
    }
    $script_optimized = ($script_count > 0) && (($script_with_async + $script_with_defer) > 0);
    $checks[] = azwc_audit_check(
        'script_attributes',
        'External scripts use async or defer',
        $script_optimized ? 'pass' : ($script_count > 0 ? 'warn' : 'info'),
        $script_count > 0
            ? sprintf('%d script tags found. %d use async, %d use defer.', $script_count, $script_with_async, $script_with_defer)
            : 'No external script tags found.',
        1,
        'technical'
    );

    return $checks;
}

/* -------------------------------------------------------------------------
 * PAGE SPEED INSIGHTS
 * ---------------------------------------------------------------------- */
function azwc_audit_psi($url, $strategy = 'mobile')
{
    $endpoint = add_query_arg(
        array_filter(array(
            'url' => $url,
            'strategy' => $strategy,
            'category' => 'performance',
            'key' => azwc_audit_psi_key(),
        )),
        'https://www.googleapis.com/pagespeedonline/v5/runPagespeed'
    );
    $r = wp_remote_get($endpoint, array('timeout' => AZWC_PSI_TIMEOUT));
    if (is_wp_error($r) || 200 !== (int) wp_remote_retrieve_response_code($r)) {
        return null;
    }
    $data = json_decode(wp_remote_retrieve_body($r), true);
    if (!is_array($data) || empty($data['lighthouseResult'])) {
        return null;
    }
    $audits = $data['lighthouseResult']['audits'] ?? array();
    $score = $data['lighthouseResult']['categories']['performance']['score'] ?? null;
    $metric = function ($id) use ($audits) {
        if (empty($audits[$id])) {
            return null;
        }
        return array(
            'display' => $audits[$id]['displayValue'] ?? null,
            'value' => $audits[$id]['numericValue'] ?? null,
            'score' => $audits[$id]['score'] ?? null,
        );
    };
    $field = array();
    if (!empty($data['loadingExperience']['metrics'])) {
        foreach ($data['loadingExperience']['metrics'] as $key => $m) {
            $field[$key] = array(
                'percentile' => $m['percentile'] ?? null,
                'category' => $m['category'] ?? null,
            );
        }
    }
    return array(
        'strategy' => $strategy,
        'score' => null === $score ? null : (int) round($score * 100),
        'lab' => array(
            'lcp' => $metric('largest-contentful-paint'),
            'cls' => $metric('cumulative-layout-shift'),
            'tbt' => $metric('total-blocking-time'),
            'fcp' => $metric('first-contentful-paint'),
            'si' => $metric('speed-index'),
        ),
        'field' => $field,
    );
}

function azwc_audit_authority($url)
{
    return array(
        'available' => false,
        'reason' => 'Backlink and authority data require direct crawler API connections. No statistical heuristics are substituted.',
    );
}

/* -------------------------------------------------------------------------
 * SCORING ENGINE
 * ---------------------------------------------------------------------- */
function azwc_audit_score($checks)
{
    $groups = array();
    $earned = 0;
    $total = 0;
    foreach ($checks as $c) {
        if ('info' === $c['status']) {
            continue;
        }
        $points = 'pass' === $c['status'] ? 1.0 : ('warn' === $c['status'] ? 0.5 : 0.0);
        $earned += $points * $c['weight'];
        $total += $c['weight'];
        $g = $c['group'];
        if (!isset($groups[$g])) {
            $groups[$g] = array('earned' => 0, 'total' => 0);
        }
        $groups[$g]['earned'] += $points * $c['weight'];
        $groups[$g]['total'] += $c['weight'];
    }
    $out = array();
    foreach ($groups as $g => $v) {
        $out[$g] = $v['total'] > 0 ? (int) round($v['earned'] / $v['total'] * 100) : null;
    }
    return array(
        'overall' => $total > 0 ? (int) round($earned / $total * 100) : null,
        'groups' => $out,
    );
}

/* -------------------------------------------------------------------------
 * REST ROUTE
 * ---------------------------------------------------------------------- */
add_action('rest_api_init', function () {
    register_rest_route('azwc/v1', '/audit', array(
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'args' => array(
            'domain' => array('required' => true, 'type' => 'string'),
            'stage' => array('required' => false, 'type' => 'string', 'default' => 'site'),
            'strategy' => array('required' => false, 'type' => 'string', 'default' => 'mobile'),
            'peek' => array('required' => false, 'type' => 'boolean', 'default' => false),
            'job' => array('required' => false, 'type' => 'string', 'default' => ''),
            'fresh' => array('required' => false, 'type' => 'boolean', 'default' => false),
        ),
        'callback' => 'azwc_audit_rest',
    ));

    // Polled while the audit request above is still running. Deliberately
    // cheap: it reads one transient and returns, so it can be called every
    // few hundred milliseconds without competing with the crawl for
    // resources.
    register_rest_route('azwc/v1', '/audit/progress', array(
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'args' => array(
            'job' => array('required' => true, 'type' => 'string'),
            'after' => array('required' => false, 'type' => 'integer', 'default' => 0),
        ),
        'callback' => 'azwc_audit_progress_rest',
    ));
});

function azwc_audit_progress_rest(WP_REST_Request $request)
{
    $key = azwc_audit_job_key($request->get_param('job'));
    $state = $key ? get_transient($key) : false;

    if (!is_array($state)) {
        // The job may simply not have been written yet on the first poll.
        return new WP_REST_Response(array('pending' => true, 'events' => array(), 'total' => 0), 200);
    }

    // Send only what the client has not seen. The crawl of a large sitemap can
    // run to a hundred events and there is no reason to resend them each poll.
    $after = max(0, (int) $request->get_param('after'));
    $all = isset($state['events']) && is_array($state['events']) ? $state['events'] : array();

    return new WP_REST_Response(array(
        'pending' => false,
        'phase'   => isset($state['phase']) ? $state['phase'] : '',
        'done'    => !empty($state['done']),
        'cached'  => !empty($state['cached']),
        'elapsed' => round(microtime(true) - $state['started'], 1),
        'total'   => count($all),
        'events'  => array_slice($all, $after),
    ), 200);
}

function azwc_audit_rest(WP_REST_Request $request)
{
    $url = azwc_audit_normalize($request->get_param('domain'));
    if (is_wp_error($url)) {
        return new WP_REST_Response(array('error' => $url->get_error_message()), 400);
    }
    $stage = $request->get_param('stage');
    $strategy = 'desktop' === $request->get_param('strategy') ? 'desktop' : 'mobile';
    if ('psi' === $stage) {
        return azwc_audit_stage_psi($url, $strategy, (bool) $request->get_param('peek'));
    }
    return azwc_audit_stage_site(
        $url,
        (string) $request->get_param('job'),
        (bool) $request->get_param('fresh')
    );
}

function azwc_audit_history_key($host)
{
    return 'azwc_audit_history_' . md5($host);
}

function azwc_audit_stage_site($url, $job = '', $fresh = false)
{
    if (function_exists('set_time_limit')) {
        @set_time_limit(150);
    }
    $GLOBALS['azwc_audit_started'] = microtime(true);
    $GLOBALS['azwc_audit_events'] = array();
    azwc_audit_progress_start($job, $url);
    $cache_key = 'azwc_audit_site_' . md5($url);

    // A forced re-scan skips the stored report but not the rate limiter or the
    // lock below, so it cannot be used to hammer a target.
    $cached = $fresh ? false : get_transient($cache_key);
    if ($cached) {
        $cached['cached'] = true;
        // No crawl happens on a cache hit. The stored report carries the crawl
        // it was built from, so the panel can show that — labelled with when it
        // ran — instead of replaying it as though it were happening now.
        azwc_audit_progress_finish(true);
        return new WP_REST_Response($cached, 200);
    }
    if (!azwc_audit_rate_ok()) {
        return new WP_REST_Response(array('error' => 'Rate limit exceeded. Please try again in an hour.'), 429);
    }
    $lock_key = azwc_audit_acquire_lock($url, 'site');
    if (!$lock_key) {
        return new WP_REST_Response(array('error' => 'An audit is currently processing for this domain. Please refresh shortly.'), 429);
    }
    try {
        azwc_audit_progress_phase('Fetching homepage HTML');
        $page = azwc_audit_fetch($url);
        if (is_wp_error($page) || $page['status'] >= 400) {
            azwc_audit_release_lock($lock_key);
            azwc_audit_progress_finish();
            return new WP_REST_Response(array('error' => 'Unable to complete site fetch.'), 422);
        }
        azwc_audit_progress_phase('Following redirects');
        $chain = azwc_audit_chain($url);
        $checks = azwc_audit_run_checks($url, $page, $chain);
        azwc_audit_progress_phase('Scoring findings');
        $score = azwc_audit_score($checks);
        $host = wp_parse_url($url, PHP_URL_HOST);
        $history_key = azwc_audit_history_key($host);
        $previous = $host ? get_transient($history_key) : false;
        $result = array(
            'url' => $url,
            'fetched' => gmdate('c'),
            'status' => $page['status'],
            'ms' => $page['ms'],
            'bytes' => strlen($page['body']),
            'score' => $score,
            'checks' => $checks,
            'authority' => azwc_audit_authority($url),
            'history' => ($previous && null !== $score['overall'] && null !== $previous['score'])
                ? array(
                    'previous_score' => $previous['score'],
                    'previous_fetched' => $previous['fetched'],
                    'delta' => $score['overall'] - $previous['score'],
                )
                : null,
            'cached' => false,
            // Stored with the report so a later cache hit can show the crawl
            // this report was actually built from.
            'crawl' => isset($GLOBALS['azwc_audit_events']) ? $GLOBALS['azwc_audit_events'] : array(),
        );
        if ($host && null !== $score['overall']) {
            set_transient($history_key, array('score' => $score['overall'], 'fetched' => $result['fetched']), 30 * DAY_IN_SECONDS);
        }
        set_transient($cache_key, $result, AZWC_AUDIT_CACHE_HOURS * HOUR_IN_SECONDS);
    } finally {
        azwc_audit_release_lock($lock_key);
        azwc_audit_progress_finish();
    }
    return new WP_REST_Response($result, 200);
}

function azwc_audit_stage_psi($url, $strategy, $peek = false)
{
    if (function_exists('ignore_user_abort')) {
        @ignore_user_abort(true);
    }
    if (function_exists('set_time_limit')) {
        @set_time_limit(AZWC_PSI_TIMEOUT + 30);
    }
    $cache_key = 'azwc_audit_psi_' . $strategy . '_' . md5($url);
    $cached = get_transient($cache_key);
    if (false !== $cached) {
        return new WP_REST_Response(array('psi' => $cached, 'strategy' => $strategy, 'cached' => true), 200);
    }
    if ($peek) {
        return new WP_REST_Response(array('psi' => null, 'strategy' => $strategy, 'pending' => true, 'cached' => false), 200);
    }
    $lock_key = azwc_audit_acquire_lock($url, 'psi_' . $strategy);
    if (!$lock_key) {
        return new WP_REST_Response(array('psi' => null, 'strategy' => $strategy, 'pending' => true, 'cached' => false), 200);
    }
    try {
        $psi = azwc_audit_psi($url, $strategy);
        set_transient($cache_key, $psi, $psi ? AZWC_AUDIT_CACHE_HOURS * HOUR_IN_SECONDS : 20);
    } finally {
        azwc_audit_release_lock($lock_key);
    }
    return new WP_REST_Response(array('psi' => $psi, 'strategy' => $strategy, 'cached' => false), 200);
}

/* -------------------------------------------------------------------------
 * SHORTCODE & UI (Premium Visuals with Group Scores)
 * ---------------------------------------------------------------------- */
add_shortcode('azwc_seo_audit', 'azwc_audit_shortcode');

/**
 * Where the dedicated results tab lives. Falls back to the audit page
 * itself, so the tool still works if that page is ever removed.
 */
function azwc_audit_results_url()
{
    $page = get_page_by_path('seo-audit-results');
    if ($page && 'publish' === $page->post_status) {
        return get_permalink($page);
    }
    return '';
}

function azwc_audit_shortcode()
{
    ob_start();
    ?>
    <div id="az-premium-audit" data-endpoint="<?php echo esc_url(rest_url('azwc/v1/audit')); ?>" data-progress="<?php echo esc_url(rest_url('azwc/v1/audit/progress')); ?>" data-results="<?php echo esc_url(azwc_audit_results_url()); ?>">
        <div class="az-audit-card search-card">
            <h2>⚡ Instant SEO Health Audit</h2>
            <p>Our engine directly probes your server – every metric is measured, never estimated.</p>
            <form id="az-audit-form">
                <input type="text" id="azwc-audit-domain" name="domain" placeholder="example.com" required autocomplete="off">
                <button type="submit">ANALYZE NOW</button>
            </form>
        </div>

        <div id="az-audit-progress" hidden>
            <p class="az-progress-target">Auditing <strong id="az-progress-domain"></strong></p>
            <div class="az-bar-bg"><div class="az-bar-fill"></div></div>

            <div class="az-crawl">
                <div class="az-crawl-stats">
                    <div class="az-cstat"><span class="az-cstat-label">Requests made</span><span class="az-cstat-val" id="az-cs-count">0</span></div>
                    <div class="az-cstat"><span class="az-cstat-label">Elapsed</span><span class="az-cstat-val" id="az-cs-elapsed">0:00</span></div>
                    <div class="az-cstat"><span class="az-cstat-label">Avg response</span><span class="az-cstat-val" id="az-cs-avg">—</span></div>
                    <div class="az-cstat"><span class="az-cstat-label">Data read</span><span class="az-cstat-val" id="az-cs-bytes">0 KB</span></div>
                </div>

                <div class="az-crawl-phase"><span class="az-crawl-dot"></span><span id="az-cs-phase">Starting…</span></div>

                <div class="az-statusbar" id="az-cs-statusbar" hidden>
                    <div class="az-statusbar-track">
                        <span class="az-sb az-sb-2xx" style="width:0%"></span>
                        <span class="az-sb az-sb-3xx" style="width:0%"></span>
                        <span class="az-sb az-sb-4xx" style="width:0%"></span>
                        <span class="az-sb az-sb-err" style="width:0%"></span>
                    </div>
                    <div class="az-statusbar-key">
                        <span><i class="az-k az-k-2xx"></i>2xx <b id="az-cs-n2">0</b></span>
                        <span><i class="az-k az-k-3xx"></i>3xx <b id="az-cs-n3">0</b></span>
                        <span><i class="az-k az-k-4xx"></i>4xx/5xx <b id="az-cs-n4">0</b></span>
                        <span><i class="az-k az-k-err"></i>Failed <b id="az-cs-n0">0</b></span>
                    </div>
                </div>

                <div class="az-crawl-log" id="az-cs-log" hidden>
                    <div class="az-crawl-log-head">
                        <span class="az-cl-t">Time</span>
                        <span class="az-cl-s">Status</span>
                        <span class="az-cl-ms">Load</span>
                        <span class="az-cl-b">Size</span>
                        <span class="az-cl-u">URL</span>
                    </div>
                    <div class="az-crawl-log-rows" id="az-cs-rows"></div>
                </div>

                <p class="az-crawl-note" id="az-cs-note" hidden></p>
            </div>

            <ul id="az-progress-steps" class="az-progress-steps" hidden></ul>
        </div>

        <div id="az-audit-results" hidden></div>

        <div id="az-sticky-cta" class="az-sticky-cta" hidden role="button" tabindex="0">
            <span class="az-sticky-cta-icon">🔓</span>
            <span class="az-sticky-cta-text">Enter your email to unlock the full report</span>
            <span class="az-sticky-cta-arrow">→</span>
        </div>
    </div>

    <style data-noptimize="1">
        :root {
            --az-gold: #e6b84d;
            --az-dark: #050608;
            --az-card: rgba(255,255,255,0.04);
            --az-border: rgba(230,184,77,0.2);
            --az-pass: #0f9d58;
            --az-warn: #e8a33d;
            --az-fail: #d64545;
        }
        #az-premium-audit { max-width: 1200px; margin: 40px auto; color: #fff; font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        .az-audit-card { background: var(--az-card); border: 1px solid var(--az-border); border-radius: 16px; padding: 32px; backdrop-filter: blur(10px); transition: all 0.3s ease; }
        .search-card { text-align: center; background: linear-gradient(135deg, rgba(230,184,77,0.05), rgba(255,255,255,0.02)); }
        .search-card h2 { color: var(--az-gold); margin-bottom: 8px; font-weight: 800; letter-spacing: -1px; font-size: 28px; }
        .search-card p { opacity: 0.7; font-size: 14px; margin-bottom: 24px; }
        #az-audit-form { display: flex; gap: 10px; max-width: 600px; margin: 0 auto; }
        #az-audit-form input { flex: 1; padding: 14px 20px; border-radius: 8px; border: 1px solid var(--az-border); background: rgba(0,0,0,0.4); color: #fff; font-size: 16px; transition: all 0.3s ease; }
        #az-audit-form input:focus { outline: none; border-color: var(--az-gold); box-shadow: 0 0 20px rgba(230,184,77,0.1); }
        #az-audit-form button { background: var(--az-gold); color: #000; border: 0; padding: 0 30px; border-radius: 8px; font-weight: 900; cursor: pointer; transition: all 0.3s ease; white-space: nowrap; }
        #az-audit-form button:hover { transform: translateY(-2px); box-shadow: 0 5px 25px rgba(230,184,77,0.3); }
        #az-audit-form button:active { transform: translateY(0); }
        #az-audit-progress { margin-top: 30px; text-align: center; padding: 20px; }
        .az-bar-bg { background: rgba(255,255,255,0.08); height: 6px; border-radius: 10px; overflow: hidden; margin-bottom: 12px; max-width: 600px; margin-left: auto; margin-right: auto; }
        .az-bar-fill { background: linear-gradient(90deg, var(--az-gold), #f5d78e); height: 100%; width: 0%; transition: width 0.5s ease-in-out; border-radius: 10px; }
        .pulse { animation: azpulse 1.5s infinite; font-size: 13px; font-weight: 600; text-transform: uppercase; color: var(--az-gold); letter-spacing: 1px; }
        @keyframes azpulse { 0% { opacity: 0.4; transform: scale(0.98); } 50% { opacity: 1; transform: scale(1); } 100% { opacity: 0.4; transform: scale(0.98); } }
        .az-progress-target { font-size: 13px; color: rgba(255,255,255,0.5); margin: 0 0 16px; }
        .az-progress-target strong { color: var(--az-gold); font-weight: 700; }
        /* ---- Live crawl log -------------------------------------------------
           Every row here is a request the audit genuinely made. Nothing is
           synthesised to fill space: if the scan came from cache, the log stays
           empty and the note explains why. */
        .az-crawl { max-width: 940px; margin: 22px auto 0; text-align: left; }
        .az-crawl-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 14px; }
        .az-cstat { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07); border-radius: 10px; padding: 10px 14px; }
        .az-cstat-label { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: 0.8px; color: rgba(255,255,255,0.4); margin-bottom: 4px; }
        .az-cstat-val { font-size: 20px; font-weight: 800; color: #fff; font-variant-numeric: tabular-nums; }
        .az-crawl-phase { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: var(--az-gold); margin-bottom: 12px; font-weight: 600; }
        .az-crawl-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--az-gold); flex-shrink: 0; animation: azpulse 1.2s infinite; }
        .az-statusbar { margin-bottom: 14px; }
        .az-statusbar-track { display: flex; height: 8px; border-radius: 6px; overflow: hidden; background: rgba(255,255,255,0.06); }
        .az-sb { height: 100%; transition: width 0.45s ease; }
        .az-sb-2xx { background: var(--az-pass); }
        .az-sb-3xx { background: var(--az-warn); }
        .az-sb-4xx { background: var(--az-fail); }
        .az-sb-err { background: #6b7280; }
        .az-statusbar-key { display: flex; flex-wrap: wrap; gap: 16px; margin-top: 8px; font-size: 11.5px; color: rgba(255,255,255,0.55); }
        .az-statusbar-key b { color: #fff; font-variant-numeric: tabular-nums; }
        .az-k { display: inline-block; width: 8px; height: 8px; border-radius: 2px; margin-right: 6px; }
        .az-k-2xx { background: var(--az-pass); } .az-k-3xx { background: var(--az-warn); }
        .az-k-4xx { background: var(--az-fail); } .az-k-err { background: #6b7280; }
        .az-crawl-log { border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; overflow: hidden; background: rgba(0,0,0,0.28); }
        .az-crawl-log-head, .az-cl-row { display: grid; grid-template-columns: 74px 64px 68px 72px 1fr; gap: 10px; padding: 8px 14px; align-items: center; }
        .az-crawl-log-head { background: rgba(255,255,255,0.04); font-size: 10px; text-transform: uppercase; letter-spacing: 0.7px; color: rgba(255,255,255,0.42); font-weight: 700; }
        .az-crawl-log-rows { max-height: 320px; overflow-y: auto; }
        .az-cl-row { font-size: 12px; border-top: 1px solid rgba(255,255,255,0.05); color: rgba(255,255,255,0.75); font-variant-numeric: tabular-nums; animation: azrowin 0.45s ease; }
        @keyframes azrowin { from { background: rgba(230,184,77,0.14); opacity: 0.3; } to { background: transparent; opacity: 1; } }
        .az-cl-u { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: rgba(255,255,255,0.55); direction: rtl; text-align: left; }
        .az-pill { display: inline-block; min-width: 34px; text-align: center; padding: 1px 6px; border-radius: 4px; font-size: 11px; font-weight: 800; }
        .az-pill-2xx { background: rgba(15,157,88,0.18); color: #4ade80; }
        .az-pill-3xx { background: rgba(232,163,61,0.18); color: #fbbf24; }
        .az-pill-4xx { background: rgba(214,69,69,0.18); color: #f87171; }
        .az-pill-err { background: rgba(107,114,128,0.22); color: #cbd5e1; }
        .az-crawl-note { font-size: 12px; color: rgba(255,255,255,0.45); margin: 12px 0 0; text-align: center; }
        @media (max-width: 720px) {
            .az-crawl-stats { grid-template-columns: repeat(2, 1fr); }
            .az-crawl-log-head, .az-cl-row { grid-template-columns: 58px 54px 56px 1fr; }
            .az-cl-b { display: none; }
        }
        .az-progress-steps { list-style: none; margin: 20px auto 0; padding: 0; max-width: 420px; text-align: left; display: flex; flex-direction: column; gap: 10px; }
        .az-progress-steps li { display: flex; align-items: center; gap: 10px; font-size: 13px; color: rgba(255,255,255,0.28); transition: color 0.3s ease; }
        .az-progress-steps li .az-step-icon { width: 16px; flex-shrink: 0; text-align: center; }
        .az-progress-steps li.is-active { color: var(--az-gold); }
        .az-progress-steps li.is-active .az-step-icon { animation: azpulse 1s infinite; }
        .az-progress-steps li.is-done { color: rgba(255,255,255,0.65); }

        .az-dashboard { display: grid; grid-template-columns: 320px 1fr; gap: 24px; margin-top: 40px; }
        .az-score-circle { position: relative; width: 180px; height: 180px; margin: 0 auto 20px; }
        .az-score-circle svg { transform: rotate(-90deg); }
        .az-score-circle circle { fill: none; stroke-width: 10; stroke-linecap: round; }
        .az-score-circle .bg { stroke: rgba(255,255,255,0.06); }
        .az-score-circle .val { stroke: var(--az-gold); stroke-dasharray: 440; transition: stroke-dashoffset 1.5s ease-in-out; }
        .az-score-num { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 44px; font-weight: 900; color: #fff; }
        .az-score-num small { font-size: 18px; opacity: 0.5; font-weight: 400; }
        .az-side-panel { text-align: center; }
        .az-side-panel h3 { color: #fff; margin: 0 0 4px 0; font-size: 20px; }
        .az-side-panel .sub { color: rgba(255,255,255,0.4); font-size: 13px; margin: 0 0 20px 0; }
        .az-check-item { display: flex; align-items: flex-start; gap: 15px; padding: 16px 0; border-bottom: 1px solid rgba(255,255,255,0.05); transition: all 0.2s ease; }
        .az-check-item:hover { background: rgba(255,255,255,0.02); padding-left: 8px; border-radius: 4px; }
        .az-check-item:last-child { border-bottom: 0; }
        .az-status-pill { padding: 3px 12px; border-radius: 20px; font-size: 9px; font-weight: 900; text-transform: uppercase; margin-bottom: 4px; display: inline-block; letter-spacing: 0.5px; }
        .az-status-pill.pass { background: rgba(15,157,88,0.2); color: #4ccb8a; }
        .az-status-pill.fail { background: rgba(214,69,69,0.2); color: #ff5f5f; }
        .az-status-pill.warn { background: rgba(232,163,61,0.2); color: #ffb143; }
        .az-status-pill.info { background: rgba(100,149,237,0.15); color: #6b8cff; }
        .az-check-text { flex: 1; }
        .az-check-text h4 { margin: 0; font-size: 15px; font-weight: 700; color: #fff; }
        .az-check-text .detail { color: rgba(255,255,255,0.6); font-size: 13px; margin: 4px 0 2px 0; }
        .az-check-text .explainer { color: var(--az-gold); font-size: 12px; opacity: 0.7; font-style: italic; margin: 2px 0 0 0; }
        .az-score-detail { margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.05); }
        .az-score-detail .stat { display: flex; justify-content: space-between; padding: 4px 0; font-size: 13px; color: rgba(255,255,255,0.6); }
        .az-score-detail .stat span:last-child { color: #fff; font-weight: 600; }
        .az-check-icon { font-size: 18px; margin-top: 2px; }
        .az-group-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 9px; font-weight: 700; text-transform: uppercase; background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.3); margin-top: 4px; letter-spacing: 0.5px; }
        .az-results-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .az-results-header h3 { margin: 0; font-size: 20px; color: #fff; }
        .az-results-header .count { color: rgba(255,255,255,0.3); font-size: 13px; }
        .az-group-score { display: inline-block; margin-left: 8px; font-size: 11px; color: rgba(255,255,255,0.3); }
        @media (max-width: 820px) { .az-dashboard { grid-template-columns: 1fr; } .az-score-circle { width: 150px; height: 150px; } .az-score-num { font-size: 36px; } #az-audit-form { flex-direction: column; } #az-audit-form button { padding: 14px; } .search-card h2 { font-size: 22px; } }
        @media (max-width: 480px) { .az-audit-card { padding: 20px; } .az-score-circle { width: 120px; height: 120px; } .az-score-num { font-size: 28px; } }

        /* Live build bar — segments stack in left-to-right as each result lands. */
        .az-build-bar { display: flex; gap: 3px; height: 8px; margin-bottom: 22px; }
        .az-build-seg { flex: 1; min-width: 2px; border-radius: 3px; background: rgba(255,255,255,0.06); transform: scaleY(0.2); opacity: 0; transform-origin: bottom; transition: transform 0.35s cubic-bezier(.2,.8,.3,1.4), opacity 0.25s ease; }
        .az-build-seg.is-in { transform: scaleY(1); opacity: 1; }
        .az-build-seg.az-build-pass { background: var(--az-pass); }
        .az-build-seg.az-build-warn { background: var(--az-warn); }
        .az-build-seg.az-build-fail { background: var(--az-fail); }
        .az-build-seg.az-build-info { background: #6b8cff; }
        .az-build-seg.is-ghost { opacity: 0.35 !important; }

        /* Staggered item entrance. */
        .az-check-item.az-anim-in { opacity: 0; transform: translateY(10px); transition: opacity 0.35s ease, transform 0.35s ease; }
        .az-check-item.az-anim-in.is-in { opacity: 1; transform: translateY(0); }

        /* Ghost rows: real status colour, blurred text, fading toward nothing. */
        .az-check-item.az-ghost .az-check-text > *:not(.az-status-pill) { filter: blur(5px); user-select: none; pointer-events: none; }
        .az-check-item.az-ghost.is-in.az-ghost-1 { opacity: 0.65; }
        .az-check-item.az-ghost.is-in.az-ghost-2 { opacity: 0.35; }
        .az-check-item.az-ghost.is-in.az-ghost-3 { opacity: 0.1; }
        #az-checks-wrap.is-locked { position: relative; }
        #az-checks-wrap.is-locked::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 90px; background: linear-gradient(to bottom, transparent, rgba(5,6,8,0.9) 85%); pointer-events: none; }

        /* Registration gate. */
        .az-gate { text-align: center; padding: 40px 24px 34px; margin-top: 10px; border-radius: 16px; border: 1px solid rgba(230,184,77,0.4); background: linear-gradient(180deg, rgba(230,184,77,0.07), rgba(230,184,77,0.02)); animation: az-gate-glow 2.6s ease-in-out infinite; }
        @keyframes az-gate-glow { 0%, 100% { box-shadow: 0 0 0 1px rgba(230,184,77,0.15), 0 0 26px rgba(230,184,77,0.08); } 50% { box-shadow: 0 0 0 1px rgba(230,184,77,0.35), 0 0 44px rgba(230,184,77,0.22); } }
        .az-gate-lock { font-size: 32px; margin-bottom: 10px; }
        .az-gate h3 { margin: 0 0 6px; color: #fff; font-size: 22px; }
        .az-gate p { margin: 0 0 22px; color: rgba(255,255,255,0.65); font-size: 14px; }
        #az-gate-form { max-width: 480px; margin: 0 auto; }
        #az-gate-form input[type="text"] { width: 100%; padding: 12px 16px; margin-bottom: 10px; border-radius: 8px; border: 1px solid var(--az-border); background: rgba(0,0,0,0.4); color: #fff; font-size: 14px; box-sizing: border-box; }
        .az-gate-email-row { display: flex; border-radius: 999px; border: 2px solid var(--az-gold); background: rgba(0,0,0,0.4); overflow: hidden; box-shadow: 0 0 24px rgba(230,184,77,0.12); }
        .az-gate-email-row input[type="email"] { flex: 1; min-width: 0; padding: 14px 18px; border: 0; background: transparent; color: #fff; font-size: 16px; }
        .az-gate-email-row input[type="email"]:focus { outline: none; }
        .az-gate-email-row button { flex: 0 0 auto; background: var(--az-gold); color: #000; border: 0; padding: 0 24px; font-weight: 900; cursor: pointer; white-space: nowrap; font-size: 14px; }
        .az-gate-email-row button:hover { background: #f5d78e; }
        .az-gate-email-row button[disabled] { opacity: 0.6; cursor: default; }
        #az-gate-form input:focus { outline: none; border-color: var(--az-gold); box-shadow: 0 0 20px rgba(230,184,77,0.1); }
        .az-gate-hp { position: absolute !important; left: -9999px !important; width: 1px !important; height: 1px !important; overflow: hidden; }
        .az-gate-note { margin: 14px 0 0; font-size: 13px; color: var(--az-warn); min-height: 16px; }

        .az-sticky-cta { position: fixed; left: 50%; bottom: 20px; z-index: 999; transform: translateX(-50%); display: flex; align-items: center; justify-content: center; gap: 10px; width: max-content; max-width: calc(100vw - 32px); margin: 0; padding: 14px 22px; border-radius: 999px; background: var(--az-gold); color: #000; font-weight: 800; font-size: 14px; cursor: pointer; box-shadow: 0 8px 30px rgba(0,0,0,0.45), 0 0 0 4px rgba(230,184,77,0.15); animation: az-sticky-in 0.35s ease-out; transition: background 0.2s ease, box-shadow 0.2s ease; }
        .az-sticky-cta:hover, .az-sticky-cta:focus-visible { background: #f5d78e; box-shadow: 0 10px 34px rgba(0,0,0,0.5), 0 0 0 4px rgba(230,184,77,0.25); }
        .az-sticky-cta-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .az-sticky-cta-arrow { transition: transform 0.2s ease; }
        .az-sticky-cta:hover .az-sticky-cta-arrow { transform: translateX(4px); }
        /* One-shot entrance only — NOT a continuous pulse: a permanently
           moving element never settles for a real click/tap to land
           cleanly, and undercuts the "measured, not gimmicky" tone the
           rest of this report earns by never fabricating a number. */
        @keyframes az-sticky-in { from { transform: translateX(-50%) translateY(12px); opacity: 0; } to { transform: translateX(-50%) translateY(0); opacity: 1; } }
        @media (max-width: 560px) { .az-sticky-cta { font-size: 13px; padding: 12px 16px; } .az-sticky-cta-text { max-width: 62vw; } }
        /* Reserve room so the fixed sticky CTA never sits on top of the
           last bit of real content (e.g. a details list right at the
           bottom of the results area). */
        #az-audit-results { padding-bottom: 76px; }

        /* ==================================================================
           v3 REPORT UI — sidebar + charts ("towers", pies, meters)
           Appended after the original rules so these win on equal specificity.
           ================================================================== */
        #az-premium-audit { --az-ink: #f4f6f8; --az-muted: rgba(255,255,255,.55); --az-faint: rgba(255,255,255,.32);
            --az-line: rgba(255,255,255,.09); --az-surface: rgba(255,255,255,.035); --az-surface-2: rgba(255,255,255,.055); }

        /* Full-page takeover on the dedicated results page only */
        body.azwc-audit-fullpage .page-title.entry-title,
        body.azwc-audit-fullpage .breadcrumb-wrap { display: none !important; }
        body.azwc-audit-fullpage #az-premium-audit { max-width: 1440px; margin: 0 auto 40px; padding: 0 20px; }
        body.azwc-audit-fullpage .az-report { min-height: calc(100vh - 120px); }
        /* The theme parks its off-canvas menu just off-screen right; in this
           page state it escapes the theme's own containment and gives mobile a
           45px horizontal scroll. Contain it here only - scoped to the report. */
        html.azwc-audit-fullpage, body.azwc-audit-fullpage { overflow-x: hidden; max-width: 100%; }

        /* ---------- shell ---------- */
        .az-report { margin-top: 18px; }
        .az-rep-top { display: flex; flex-wrap: wrap; align-items: center; gap: 14px 20px; padding: 16px 20px; margin-bottom: 18px;
            background: linear-gradient(120deg, rgba(230,184,77,.09), rgba(255,255,255,.03));
            border: 1px solid var(--az-border); border-radius: 14px; }
        .az-rep-top .az-rep-host { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .az-rep-top .az-rep-host b { font-size: 17px; color: #fff; font-weight: 800; letter-spacing: -.2px; word-break: break-all; }
        .az-rep-top .az-rep-host span { font-size: 11px; color: var(--az-faint); text-transform: uppercase; letter-spacing: .09em; }
        .az-rep-chip { display: inline-flex; align-items: center; gap: 7px; padding: 6px 12px; border-radius: 999px;
            font-size: 12px; font-weight: 800; border: 1px solid var(--az-line); background: rgba(0,0,0,.28); color: var(--az-ink); }
        .az-rep-chip i { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
        .az-rep-spacer { flex: 1 1 auto; }
        .az-rep-rerun { background: transparent; border: 1px solid var(--az-border); color: var(--az-gold);
            padding: 9px 16px; border-radius: 9px; font-weight: 800; font-size: 12px; cursor: pointer; transition: .2s; }
        .az-rep-rerun:hover { background: rgba(230,184,77,.12); }

        .az-rep-body { display: grid; grid-template-columns: 232px minmax(0,1fr); gap: 22px; align-items: start; }

        /* ---------- left vertical menu ---------- */
        .az-nav { position: sticky; top: 20px; background: var(--az-surface); border: 1px solid var(--az-line);
            border-radius: 14px; padding: 10px; }
        .az-nav-head { padding: 10px 10px 12px; border-bottom: 1px solid var(--az-line); margin-bottom: 8px; text-align: center; }
        .az-nav-mini { position: relative; width: 96px; height: 96px; margin: 0 auto 8px; }
        .az-nav-mini svg { transform: rotate(-90deg); }
        .az-nav-mini .mv { font-size: 24px; font-weight: 900; color: #fff; position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center; }
        .az-nav-head small { display: block; font-size: 10px; letter-spacing: .1em; text-transform: uppercase; color: var(--az-faint); }
        .az-nav-item { display: flex; align-items: center; gap: 9px; width: 100%; text-align: left; padding: 9px 11px;
            border: 0; border-radius: 9px; background: transparent; color: var(--az-muted); font-size: 13px; font-weight: 600;
            cursor: pointer; transition: .16s; font-family: inherit; }
        .az-nav-item:hover { background: var(--az-surface-2); color: var(--az-ink); }
        .az-nav-item.is-on { background: rgba(230,184,77,.13); color: var(--az-gold); }
        .az-nav-item .dot { width: 7px; height: 7px; border-radius: 50%; flex: 0 0 auto; }
        .az-nav-item .n { margin-left: auto; font-size: 11px; color: var(--az-faint); font-weight: 700; }
        .az-nav-item.is-on .n { color: var(--az-gold); }

        /* ---------- charts row ---------- */
        .az-viz { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px; margin-bottom: 18px; }
        .az-viz-card { background: var(--az-surface); border: 1px solid var(--az-line); border-radius: 14px; padding: 18px; }
        .az-viz-card h4 { margin: 0 0 14px; font-size: 11px; letter-spacing: .1em; text-transform: uppercase;
            color: var(--az-faint); font-weight: 800; }
        .az-donut { position: relative; width: 168px; height: 168px; margin: 0 auto; }
        .az-donut svg { transform: rotate(-90deg); }
        .az-donut circle { fill: none; stroke-width: 13; stroke-linecap: round; }
        .az-donut .bg { stroke: rgba(255,255,255,.07); }
        .az-donut .val { stroke-dasharray: 440; stroke-dashoffset: 440; transition: stroke-dashoffset 1.4s cubic-bezier(.2,.8,.2,1); }
        .az-donut-mid { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .az-donut-mid b { font-size: 42px; font-weight: 900; color: #fff; line-height: 1; }
        .az-donut-mid span { font-size: 10px; letter-spacing: .11em; text-transform: uppercase; color: var(--az-faint); margin-top: 4px; }

        /* pie (severity split) */
        .az-pie-wrap { display: flex; align-items: center; gap: 16px; }
        .az-pie { width: 132px; height: 132px; flex: 0 0 auto; }
        .az-pie path { transition: opacity .2s; cursor: default; }
        .az-pie path:hover { opacity: .82; }
        .az-legend { display: grid; gap: 9px; min-width: 0; }
        .az-legend div { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: var(--az-muted); }
        .az-legend i { width: 10px; height: 10px; border-radius: 3px; flex: 0 0 auto; }
        .az-legend b { color: #fff; font-weight: 800; margin-left: auto; padding-left: 10px; }

        /* stat tiles */
        .az-tiles { display: grid; gap: 10px; }
        .az-tile { display: flex; align-items: baseline; justify-content: space-between; gap: 10px;
            padding: 11px 13px; border-radius: 10px; background: rgba(0,0,0,.24); border: 1px solid var(--az-line); }
        .az-tile span { font-size: 11.5px; color: var(--az-muted); }
        .az-tile b { font-size: 17px; font-weight: 900; color: #fff; }
        .az-tile b small { font-size: 11px; font-weight: 700; color: var(--az-faint); margin-left: 2px; }

        /* ---------- category "towers" ---------- */
        .az-towers-card { background: var(--az-surface); border: 1px solid var(--az-line); border-radius: 14px;
            padding: 20px 20px 14px; margin-bottom: 18px; }
        .az-towers { display: flex; align-items: flex-end; gap: 14px; height: 190px; padding-top: 8px; overflow-x: auto; }
        .az-tower { flex: 1 1 0; min-width: 62px; display: flex; flex-direction: column; align-items: center; height: 100%; }
        .az-tower .col { position: relative; width: 100%; max-width: 62px; flex: 1 1 auto; display: flex; align-items: flex-end; }
        .az-tower .fill { width: 100%; height: 0; border-radius: 8px 8px 3px 3px; transition: height 1.1s cubic-bezier(.2,.8,.2,1);
            background: linear-gradient(180deg, var(--c), rgba(0,0,0,.28)); box-shadow: 0 0 22px -8px var(--c); }
        .az-tower .pct { font-size: 13px; font-weight: 900; color: #fff; margin-bottom: 6px; }
        .az-tower .lb { font-size: 10.5px; color: var(--az-faint); margin-top: 9px; text-align: center; line-height: 1.25;
            text-transform: uppercase; letter-spacing: .05em; font-weight: 700; }

        /* ---------- core web vitals meters ---------- */
        .az-cwv { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 14px; }
        .az-cwv-item { padding: 13px 15px; border-radius: 11px; background: rgba(0,0,0,.24); border: 1px solid var(--az-line); }
        .az-cwv-item .top { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 9px; }
        .az-cwv-item .top span { font-size: 11px; letter-spacing: .07em; text-transform: uppercase; color: var(--az-faint); font-weight: 800; }
        .az-cwv-item .top b { font-size: 16px; font-weight: 900; color: #fff; }
        .az-cwv-track { height: 7px; border-radius: 99px; background: rgba(255,255,255,.08); overflow: hidden; }
        .az-cwv-fill { height: 100%; width: 0; border-radius: 99px; transition: width 1.1s cubic-bezier(.2,.8,.2,1); }
        .az-cwv-bands { position: relative; display: flex; height: 7px; border-radius: 99px; overflow: hidden; }
        .az-cwv-bands i { display: block; height: 100%; }
        .az-cwv-bands i.g { width: 40%; background: rgba(47,191,113,.34); }
        .az-cwv-bands i.n { width: 30%; background: rgba(232,163,61,.34); }
        .az-cwv-bands i.p { width: 30%; background: rgba(224,85,85,.34); }
        .az-cwv-mark { position: absolute; top: -3px; width: 3px; height: 13px; border-radius: 2px; transform: translateX(-50%); box-shadow: 0 0 0 2px rgba(0,0,0,.45); }
        .az-cwv-verdict { margin-top: 7px; font-size: 11px; font-weight: 800; }
        .az-cwv-verdict span { color: var(--az-faint); font-weight: 600; }
        .az-nomeasure { margin-top: 18px; padding: 15px 18px; border: 1px dashed var(--az-line); border-radius: 12px;
            font-size: 12.5px; line-height: 1.65; color: var(--az-muted); background: rgba(0,0,0,.18); }
        .az-nomeasure b { display: block; color: var(--az-ink); font-size: 12.5px; margin-bottom: 4px; }

        /* ---------- results panel ---------- */
        .az-panel { background: var(--az-surface); border: 1px solid var(--az-line); border-radius: 14px; padding: 20px; }
        .az-panel-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
        .az-panel-head h3 { margin: 0; font-size: 16px; color: #fff; font-weight: 800; }
        .az-panel-head .count { font-size: 11.5px; color: var(--az-faint); font-weight: 700; }
        .az-filter-note { font-size: 11.5px; color: var(--az-faint); margin: -4px 0 12px; }

        /* nothing inside the report may out-size its box */
        #az-premium-audit, #az-premium-audit * { box-sizing: border-box; }
        #az-premium-audit { width: 100%; }
        .az-report, .az-rep-body, .az-viz, .az-panel, .az-towers-card, .az-viz-card { max-width: 100%; min-width: 0; }
        /* Grid/flex children default to min-width:auto, so <main> and <aside>
           were sizing to their content (408px) instead of their 336px track and
           pushing the whole report past the viewport on phones. */
        .az-rep-body > * { min-width: 0; }
        .az-nav-item { min-width: 0; }
        .az-nav-item > span:not(.dot):not(.n) { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        @media (max-width: 900px) {
            .az-rep-body { grid-template-columns: 1fr; }
            .az-nav { position: static; }
            /* the big donut is right below on mobile - one score readout is enough */
            .az-nav-head { display: none; }
            .az-nav-list { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
            .az-nav-item { padding: 9px 8px; font-size: 12px; }
            .az-nav-item .n { font-size: 10px; }
        }
        @media (max-width: 640px) {
            body.azwc-audit-fullpage #az-premium-audit { padding: 0 12px; }
            .az-rep-top { padding: 14px; gap: 10px 12px; }
            .az-rep-top .az-rep-host b { font-size: 15px; }
            .az-rep-spacer { display: none; }
            .az-rep-rerun { width: 100%; }
            .az-viz-card, .az-panel, .az-towers-card { padding: 15px; }
            .az-nav-list { grid-template-columns: 1fr; }
            .az-pie-wrap { flex-direction: column; align-items: flex-start; gap: 12px; }
            .az-pie { width: 118px; height: 118px; margin: 0 auto; }
            .az-legend { width: 100%; }
            .az-donut { width: 148px; height: 148px; }
            .az-donut svg { width: 148px; height: 148px; }
            .az-towers { gap: 10px; height: 165px; }
            .az-tower { min-width: 52px; }
        }
    </style>

    <script>
    (function() {
        const container = document.getElementById('az-premium-audit');
        const form = document.getElementById('az-audit-form');
        const progress = document.getElementById('az-audit-progress');
        const results = document.getElementById('az-audit-results');
        const fill = document.querySelector('.az-bar-fill');

        if (!container || !form) return;

        const input = form.querySelector('input[name="domain"]');
        const stickyCta = document.getElementById('az-sticky-cta');
        if (stickyCta) {
            stickyCta.addEventListener('click', () => jumpToGate());
            stickyCta.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); jumpToGate(); }
            });
        }

        // The audit is one long request. Rather than animate a guess at what
        // it might be doing, the server now records every request it genuinely
        // makes against a job id, and this polls that feed while the long
        // request is still in flight. Every row rendered below is real.
        const POLL_MS = 600;

        function azFmtBytes(n) {
            if (!n) return '\u2014';
            if (n < 1024) return n + ' B';
            if (n < 1048576) return (n / 1024).toFixed(1) + ' KB';
            return (n / 1048576).toFixed(1) + ' MB';
        }

        function azAgo(iso) {
            const then = Date.parse(iso);
            if (!then) return 'earlier';
            const mins = Math.max(1, Math.round((Date.now() - then) / 60000));
            if (mins < 60) return mins + (mins === 1 ? ' minute ago' : ' minutes ago');
            const hrs = Math.round(mins / 60);
            return hrs + (hrs === 1 ? ' hour ago' : ' hours ago');
        }

        function azFmtClock(sec) {
            const m = Math.floor(sec / 60), ss = Math.floor(sec % 60);
            return m + ':' + String(ss).padStart(2, '0');
        }

        function azStatusClass(code) {
            if (!code) return 'err';
            if (code < 300) return '2xx';
            if (code < 400) return '3xx';
            return '4xx';
        }

        function azShortUrl(u) {
            try {
                const parsed = new URL(u);
                const path = parsed.pathname === '/' ? '/' : parsed.pathname.replace(/\/$/, '');
                return parsed.hostname + path;
            } catch (e) { return u; }
        }

        async function runAudit(domain, opts) {
            const fresh = !!(opts && opts.fresh);
            results.hidden = true;
            results.innerHTML = '';
            progress.hidden = false;
            if (stickyCta) stickyCta.hidden = true;
            fill.style.width = '6%';

            const domainEl = document.getElementById('az-progress-domain');
            if (domainEl) domainEl.textContent = domain;

            const elCount = document.getElementById('az-cs-count');
            const elElapsed = document.getElementById('az-cs-elapsed');
            const elAvg = document.getElementById('az-cs-avg');
            const elBytes = document.getElementById('az-cs-bytes');
            const elPhase = document.getElementById('az-cs-phase');
            const elBar = document.getElementById('az-cs-statusbar');
            const elLog = document.getElementById('az-cs-log');
            const elRows = document.getElementById('az-cs-rows');
            const elNote = document.getElementById('az-cs-note');

            if (elRows) elRows.innerHTML = '';
            if (elLog) elLog.hidden = true;
            if (elBar) elBar.hidden = true;
            if (elNote) elNote.hidden = true;

            const job = 'j' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
            const startedAt = Date.now();
            const counts = { '2xx': 0, '3xx': 0, '4xx': 0, 'err': 0 };
            let seen = 0, totalMs = 0, totalBytes = 0, finished = false;

            const ticker = setInterval(() => {
                if (elElapsed) elElapsed.textContent = azFmtClock((Date.now() - startedAt) / 1000);
            }, 250);

            function paint(ev) {
                seen++;
                const cls = azStatusClass(ev.status);
                counts[cls]++;
                totalMs += ev.ms || 0;
                totalBytes += ev.bytes || 0;

                if (elCount) elCount.textContent = seen;
                if (elAvg) elAvg.textContent = Math.round(totalMs / seen) + ' ms';
                if (elBytes) elBytes.textContent = azFmtBytes(totalBytes);

                if (elBar) {
                    elBar.hidden = false;
                    ['2xx', '3xx', '4xx', 'err'].forEach(function (k) {
                        const el = elBar.querySelector('.az-sb-' + k);
                        if (el) el.style.width = ((counts[k] / seen) * 100).toFixed(1) + '%';
                    });
                    const map = { '2xx': 'az-cs-n2', '3xx': 'az-cs-n3', '4xx': 'az-cs-n4', 'err': 'az-cs-n0' };
                    Object.keys(map).forEach(function (k) {
                        const el = document.getElementById(map[k]);
                        if (el) el.textContent = counts[k];
                    });
                }

                if (elLog && elRows) {
                    elLog.hidden = false;
                    const row = document.createElement('div');
                    row.className = 'az-cl-row';
                    const label = ev.status ? ev.status : 'ERR';
                    row.innerHTML =
                        '<span class="az-cl-t">+' + (ev.at != null ? ev.at.toFixed(1) : '0.0') + 's</span>' +
                        '<span class="az-cl-s"><span class="az-pill az-pill-' + cls + '">' + label + '</span></span>' +
                        '<span class="az-cl-ms">' + (ev.ms || 0) + ' ms</span>' +
                        '<span class="az-cl-b">' + azFmtBytes(ev.bytes) + '</span>' +
                        '<span class="az-cl-u" title="' + esc(ev.url) + '">' + esc(azShortUrl(ev.url)) + '</span>';
                    elRows.prepend(row);
                    while (elRows.children.length > 60) elRows.removeChild(elRows.lastChild);
                }

                // The bar tracks real work against a ceiling, because the total
                // number of requests is not known until the crawl finishes.
                fill.style.width = Math.min(6 + seen * 2.4, 92) + '%';
            }

            async function poll() {
                try {
                    const r = await fetch(container.dataset.progress + '?job=' + encodeURIComponent(job) + '&after=' + seen);
                    if (r.ok) {
                        const state = await r.json();
                        if (state && !state.pending) {
                            (state.events || []).forEach(paint);
                            if (elPhase && state.phase) elPhase.textContent = state.phase;
                            if (state.cached && elNote) {
                                elNote.hidden = false;
                                elNote.textContent = 'This domain was scanned recently, so the saved report was reused instead of crawling it again.';
                            }
                        }
                    }
                } catch (e) { /* a dropped poll is not worth surfacing */ }
                if (!finished) setTimeout(poll, POLL_MS);
            }
            setTimeout(poll, 250);

            try {
                const response = await fetch(container.dataset.endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ domain: domain, job: job, fresh: fresh })
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.error || 'Failed to complete scan.');
                }

                const data = await response.json();

                finished = true;
                // One final read so the last few requests appear before the log
                // gives way to the report, rather than being cut off mid-crawl.
                await poll();
                clearInterval(ticker);


                // A cache hit performs no requests, so nothing has streamed in.
                // Rather than show an empty panel, paint the crawl this report
                // was actually built from — stated plainly as a past scan, not
                // replayed as though it were happening now.
                let hold = seen ? 650 : 250;
                if (data.cached && Array.isArray(data.crawl) && data.crawl.length) {
                    data.crawl.forEach(paint);
                    if (elElapsed) elElapsed.textContent = azFmtClock(data.crawl[data.crawl.length - 1].at || 0);
                    if (elNote) {
                        elNote.hidden = false;
                        elNote.textContent = 'Showing the crawl from the last scan of this domain, ' +
                            azAgo(data.fetched) + '. Nothing was re-crawled just now — use Re-run scan for a fresh crawl.';
                    }
                    hold = 1600;
                }

                if (elPhase) {
                    elPhase.textContent = data.cached
                        ? 'Loaded from a scan ' + azAgo(data.fetched)
                        : 'Scan complete';
                }
                fill.style.width = '100%';

                setTimeout(function () {
                    progress.hidden = true;
                    results.hidden = false;
                    renderResults(data, domain);
                }, hold);

            } catch (err) {
                finished = true;
                clearInterval(ticker);
                progress.hidden = true;
                results.hidden = false;
                results.innerHTML =
                    '<div class="az-audit-card" style="text-align:center;padding:40px;border-color:rgba(214,69,69,0.3);">' +
                    '<h3 style="color:#ff5f5f;">Scan failed</h3>' +
                    '<p style="color:rgba(255,255,255,0.6);">' + esc(err.message || 'Unable to complete the audit. Please try again.') + '</p>' +
                    '</div>';
            }
        }

        const resultsUrl = container.dataset.results || '';
        const targetParam = (function () {
            try { return new URLSearchParams(window.location.search).get('target') || ''; }
            catch (e) { return ''; }
        })();

        // On the dedicated results tab the domain is already decided in the
        // URL — skip the search card and run immediately.
        if (targetParam) {
            input.value = targetParam;
            const searchCard = container.querySelector('.search-card');
            if (searchCard) searchCard.hidden = true;
            runAudit(targetParam);
        }

        form.onsubmit = (e) => {
            e.preventDefault();
            const domain = input.value.trim();
            if (!domain) return;

            // From the audit page, hand off to the dedicated results tab so the
            // report and the "book a call"/"email me a PDF" follow-up live on
            // their own shareable URL. Opened directly from this click so it
            // is a user gesture and survives popup blocking. On the results
            // page itself targetParam is already set, so that branch above
            // runs inline instead of spawning tabs forever.
            if (resultsUrl && !targetParam) {
                window.open(resultsUrl + '?target=' + encodeURIComponent(domain), '_blank', 'noopener');
                return;
            }
            runAudit(domain);
        };

        /* ---- v3 report: colours, chart builders, sidebar ---------------- */

        const AZ_C = { pass: '#2fbf71', warn: '#e8a33d', fail: '#e05555', idle: 'rgba(255,255,255,.25)' };

        function azBand(score) {
            if (score === null || score === undefined) return AZ_C.idle;
            if (score >= 90) return AZ_C.pass;
            if (score >= 70) return AZ_C.warn;
            return AZ_C.fail;
        }

        const AZ_GROUP_LABELS = {
            technical: 'Technical',
            onpage: 'On-Page',
            indexability: 'Indexability',
            structured: 'Structured Data',
            accessibility: 'Accessibility',
            branding: 'Branding',
            internationalization: 'International',
            performance: 'Performance'
        };
        function azGroupLabel(g) { return AZ_GROUP_LABELS[g] || (g ? g.charAt(0).toUpperCase() + g.slice(1) : 'Other'); }

        /** SVG pie slice path. Full-circle case is handled by the caller. */
        function azSlice(cx, cy, r, a0, a1) {
            const rad = a => (a - 90) * Math.PI / 180;
            const x1 = cx + r * Math.cos(rad(a0)), y1 = cy + r * Math.sin(rad(a0));
            const x2 = cx + r * Math.cos(rad(a1)), y2 = cy + r * Math.sin(rad(a1));
            const large = (a1 - a0) > 180 ? 1 : 0;
            return 'M ' + cx + ' ' + cy + ' L ' + x1 + ' ' + y1 + ' A ' + r + ' ' + r + ' 0 ' + large + ' 1 ' + x2 + ' ' + y2 + ' Z';
        }

        function azPieHTML(parts) {
            const total = parts.reduce((s, p) => s + p.v, 0);
            if (!total) return '<div style="font-size:12px;color:rgba(255,255,255,.35)">No data</div>';
            const live = parts.filter(p => p.v > 0);
            let svg = '<svg class="az-pie" viewBox="0 0 120 120" role="img" aria-label="Result breakdown">';
            if (live.length === 1) {
                svg += '<circle cx="60" cy="60" r="52" fill="' + live[0].c + '"></circle>';
            } else {
                let a = 0;
                live.forEach(p => {
                    const sweep = (p.v / total) * 360;
                    svg += '<path d="' + azSlice(60, 60, 52, a, a + sweep) + '" fill="' + p.c + '"><title>' + p.k + ': ' + p.v + '</title></path>';
                    a += sweep;
                });
            }
            svg += '<circle cx="60" cy="60" r="27" fill="#0a0c10"></circle></svg>';
            const legend = parts.map(p =>
                '<div><i style="background:' + p.c + '"></i>' + p.k + '<b>' + p.v + '</b></div>'
            ).join('');
            return '<div class="az-pie-wrap">' + svg + '<div class="az-legend">' + legend + '</div></div>';
        }

        function azDonutHTML(score, cls) {
            const col = azBand(score);
            return '<div class="az-donut ' + (cls || '') + '">' +
                '<svg width="168" height="168" viewBox="0 0 168 168">' +
                '<circle class="bg" cx="84" cy="84" r="70"></circle>' +
                '<circle class="val" cx="84" cy="84" r="70" stroke="' + col + '" data-score="' + (score || 0) + '"></circle>' +
                '</svg><div class="az-donut-mid"><b>' + (score === null || score === undefined ? '—' : score) + '</b><span>Health score</span></div></div>';
        }

        function azScoredCount(groupScores) {
            return Object.values(groupScores).filter(v => v !== null && v !== undefined).length;
        }

        function azTowersHTML(groupScores) {
            const entries = Object.entries(groupScores).filter(([, v]) => v !== null && v !== undefined);
            if (!entries.length) return '';
            entries.sort((a, b) => a[1] - b[1]);
            return '<div class="az-towers">' + entries.map(([g, sc]) => {
                const c = azBand(sc);
                return '<div class="az-tower"><div class="pct">' + sc + '%</div>' +
                    '<div class="col"><div class="fill" style="--c:' + c + '" data-h="' + sc + '"></div></div>' +
                    '<div class="lb">' + azGroupLabel(g) + '</div></div>';
            }).join('') + '</div>';
        }

        /* Google's published Core Web Vitals thresholds — their numbers, not ours. */
        const AZ_CWV = {
            lcp: { label: 'LCP', good: 2500, poor: 4000, thr: '2.5s', fmt: v => (v / 1000).toFixed(1) + 's' },
            cls: { label: 'CLS', good: 0.1, poor: 0.25, thr: '0.1', fmt: v => v.toFixed(2) },
            tbt: { label: 'TBT', good: 200, poor: 600, thr: '200ms', fmt: v => Math.round(v) + 'ms' },
            fcp: { label: 'FCP', good: 1800, poor: 3000, thr: '1.8s', fmt: v => (v / 1000).toFixed(1) + 's' }
        };

        function azCwvHTML(psi) {
            if (!psi || !psi.lab) return '';
            const items = Object.keys(AZ_CWV).map(k => {
                const m = psi.lab[k];
                if (!m || m.value === null || m.value === undefined) return '';
                const spec = AZ_CWV[k];
                const good = m.value <= spec.good, poor = m.value > spec.poor;
                const col = good ? AZ_C.pass : (poor ? AZ_C.fail : AZ_C.warn);
                const verdict = good ? 'Good' : (poor ? 'Poor' : 'Needs improvement');
                // The track is Google's own bands: good | needs-improvement | poor.
                // Band widths are fixed at 40/30/30 and the marker sits at the real
                // measured value inside its band - no invented scale.
                let pos;
                if (good) { pos = (m.value / spec.good) * 40; }
                else if (!poor) { pos = 40 + ((m.value - spec.good) / (spec.poor - spec.good)) * 30; }
                else { pos = 70 + Math.min(1, (m.value - spec.poor) / (spec.poor * 2)) * 30; }
                pos = Math.max(1.5, Math.min(98.5, pos));
                return '<div class="az-cwv-item"><div class="top"><span>' + spec.label + '</span><b>' +
                    (m.display || spec.fmt(m.value)) + '</b></div>' +
                    '<div class="az-cwv-bands"><i class="g"></i><i class="n"></i><i class="p"></i>' +
                    '<u class="az-cwv-mark" style="left:' + pos.toFixed(1) + '%;background:' + col + '"></u></div>' +
                    '<div class="az-cwv-verdict" style="color:' + col + '">' + verdict +
                    ' <span>&middot; Google &quot;good&quot; threshold ' + spec.thr + '</span></div></div>';
            }).join('');
            if (!items) return '';
            const head = psi.score !== null && psi.score !== undefined
                ? '<span class="az-rep-chip" style="margin-left:auto"><i style="background:' + azBand(psi.score) + '"></i>Performance ' + psi.score + '</span>'
                : '';
            return '<div class="az-panel-head" style="margin-bottom:12px"><h3>Core Web Vitals <span style="font-size:11px;color:rgba(255,255,255,.35);font-weight:600">· Google PageSpeed, mobile</span></h3>' + head + '</div>' +
                '<div class="az-cwv">' + items + '</div>';
        }

        function azAnimateCharts(root) {
            requestAnimationFrame(() => {
                root.querySelectorAll('.az-donut .val, .az-nav-mini .val').forEach(c => {
                    const s = parseInt(c.dataset.score || '0', 10);
                    c.style.strokeDashoffset = String(440 - (440 * s) / 100);
                });
                root.querySelectorAll('.az-tower .fill').forEach(f => { f.style.height = (f.dataset.h || 0) + '%'; });
                root.querySelectorAll('.az-cwv-fill').forEach(f => { f.style.width = (f.dataset.w || 0) + '%'; });
            });
        }

        /** Left menu filters whatever is currently in the list (works gated or unlocked). */
        function azWireNav(root) {
            const list = root.querySelector('#az-check-list');
            const note = root.querySelector('#az-filter-note');
            root.querySelectorAll('.az-nav-item').forEach(btn => {
                btn.addEventListener('click', () => {
                    root.querySelectorAll('.az-nav-item').forEach(b => b.classList.remove('is-on'));
                    btn.classList.add('is-on');
                    const g = btn.dataset.g;
                    let shown = 0;
                    list.querySelectorAll(':scope > *').forEach(item => {
                        const badge = item.querySelector('.az-group-badge');
                        const ig = badge ? badge.textContent.trim().toLowerCase() : '';
                        const on = (g === 'all') || (ig === g);
                        item.style.display = on ? '' : 'none';
                        if (on) shown++;
                    });
                    if (note) {
                        note.textContent = g === 'all'
                            ? ''
                            : 'Showing ' + shown + ' ' + azGroupLabel(g) + ' check' + (shown === 1 ? '' : 's') + ' of those revealed so far.';
                    }
                });
            });
        }

        /**
         * PSI genuinely takes 60-100s, and the endpoint is a *stage*: the first
         * call does the work, and it answers {pending:true} while another run
         * holds the lock. So kick the work off, then poll the cheap peek call
         * until the result lands. A single request would give up on the very
         * first "pending" and the speed card would never appear.
         */
        function azLoadPsi(domain, root) {
            const card = root.querySelector('#az-cwv-card');
            if (!card) return;
            const endpoint = container.dataset.endpoint;
            const post = (body) => fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(Object.assign({ domain: domain, stage: 'psi', strategy: 'mobile' }, body))
            }).then(r => r.ok ? r.json() : null).catch(() => null);

            let settled = false;
            const paint = (psi) => {
                if (settled || !psi) return false;
                const html = azCwvHTML(psi);
                if (!html) return false;
                settled = true;
                card.innerHTML = html;
                card.hidden = false;
                azAnimateCharts(card);
                return true;
            };

            card.hidden = false;
            card.innerHTML = '<div class="az-panel-head" style="margin-bottom:0"><h3>Core Web Vitals</h3>' +
                '<span class="count" id="az-cwv-wait">measuring live speed with Google&hellip;</span></div>';

            // Fire the worker (its own response may also carry the data).
            post({}).then(d => { if (d && d.psi) paint(d.psi); });

            // Poll the instant peek until it lands.
            const deadline = Date.now() + 180000;
            const tick = () => {
                if (settled) return;
                if (Date.now() > deadline) {
                    if (!settled) card.hidden = true;
                    return;
                }
                post({ peek: true }).then(d => {
                    if (d && d.psi && paint(d.psi)) return;
                    setTimeout(tick, 6000);
                });
            };
            setTimeout(tick, 8000);
        }
        function renderResults(data, domain) {
            const score = data.score?.overall ?? null;
            const SEVERITY_ORDER = { fail: 0, warn: 1, pass: 2, info: 3 };
            const checks = (data.checks || []).slice().sort(function (a, b) {
                const sevDiff = (SEVERITY_ORDER[a.status] ?? 4) - (SEVERITY_ORDER[b.status] ?? 4);
                if (sevDiff !== 0) return sevDiff;
                // Within the same severity, surface checks carrying concrete
                // evidence (e.g. actual broken URLs) first — that's the proof
                // a first-time visitor needs to trust the report is real.
                return (b.items && b.items.length ? 1 : 0) - (a.items && a.items.length ? 1 : 0);
            });
            const passCount = checks.filter(c => c.status === 'pass').length;
            const warnCount = checks.filter(c => c.status === 'warn').length;
            const failCount = checks.filter(c => c.status === 'fail').length;
            const infoCount = checks.filter(c => c.status === 'info').length;
            const groupScores = data.score?.groups || {};

            // group -> {count, worst}
            const gStats = {};
            checks.forEach(c => {
                const g = c.group || 'other';
                if (!gStats[g]) gStats[g] = { n: 0, worst: 'pass' };
                gStats[g].n++;
                if (c.status === 'fail') gStats[g].worst = 'fail';
                else if (c.status === 'warn' && gStats[g].worst !== 'fail') gStats[g].worst = 'warn';
            });

            const navItems = ['<button class="az-nav-item is-on" data-g="all"><span class="dot" style="background:' +
                (failCount ? AZ_C.fail : warnCount ? AZ_C.warn : AZ_C.pass) + '"></span>All checks<span class="n">' +
                checks.length + '</span></button>']
                .concat(Object.keys(gStats).sort((a, b) => gStats[b].n - gStats[a].n).map(g =>
                    '<button class="az-nav-item" data-g="' + esc(g) + '"><span class="dot" style="background:' +
                    (AZ_C[gStats[g].worst] || AZ_C.idle) + '"></span>' + esc(azGroupLabel(g)) +
                    '<span class="n">' + gStats[g].n + '</span></button>'
                )).join('');

            const delta = data.history
                ? (data.history.delta > 0 ? '+' + data.history.delta : String(data.history.delta))
                : null;

            results.innerHTML = `
                <div class="az-report">
                    <div class="az-rep-top">
                        <div class="az-rep-host">
                            <div>
                                <span>Report for</span><br>
                                <b>${esc(domain)}</b>
                            </div>
                        </div>
                        <span class="az-rep-chip"><i style="background:${azBand(score)}"></i>${score === null ? '—' : score + '/100'}</span>
                        ${data.ms ? `<span class="az-rep-chip">${data.ms} ms</span>` : ''}
                        ${data.bytes ? `<span class="az-rep-chip">${(data.bytes / 1024).toFixed(0)} KB</span>` : ''}
                        ${delta !== null ? `<span class="az-rep-chip"><i style="background:${data.history.delta >= 0 ? AZ_C.pass : AZ_C.fail}"></i>${delta} vs last scan</span>` : ''}
                        <span class="az-rep-spacer"></span>
                        <button type="button" class="az-rep-rerun" id="az-rep-rerun">Re-run scan</button>
                    </div>

                    <div class="az-rep-body">
                        <aside class="az-nav">
                            <div class="az-nav-head">
                                <div class="az-nav-mini">
                                    <svg width="96" height="96" viewBox="0 0 168 168" style="width:96px;height:96px">
                                        <circle class="bg" cx="84" cy="84" r="70" fill="none" stroke="rgba(255,255,255,.07)" stroke-width="13"></circle>
                                        <circle class="val" cx="84" cy="84" r="70" fill="none" stroke="${azBand(score)}" stroke-width="13"
                                            stroke-linecap="round" stroke-dasharray="440" stroke-dashoffset="440" data-score="${score || 0}"
                                            style="transition:stroke-dashoffset 1.4s cubic-bezier(.2,.8,.2,1)"></circle>
                                    </svg>
                                    <div class="mv">${score === null ? '—' : score}</div>
                                </div>
                                <small>Overall health</small>
                            </div>
                            <div class="az-nav-list">${navItems}</div>
                        </aside>

                        <main>
                            <div class="az-viz">
                                <div class="az-viz-card">
                                    <h4>Overall score</h4>
                                    ${azDonutHTML(score)}
                                </div>
                                <div class="az-viz-card">
                                    <h4>Result breakdown</h4>
                                    ${azPieHTML([
                                        { k: 'Passed', v: passCount, c: AZ_C.pass },
                                        { k: 'Warnings', v: warnCount, c: AZ_C.warn },
                                        { k: 'Failures', v: failCount, c: AZ_C.fail },
                                        { k: 'Informational', v: infoCount, c: 'rgba(255,255,255,.28)' }
                                    ])}
                                </div>
                                <div class="az-viz-card">
                                    <h4>At a glance</h4>
                                    <div class="az-tiles">
                                        <div class="az-tile"><span>Checks run</span><b>${checks.length}</b></div>
                                        <div class="az-tile"><span>Server response</span><b>${data.ms ? data.ms : '—'}<small>ms</small></b></div>
                                        <div class="az-tile"><span>Page weight</span><b>${data.bytes ? (data.bytes / 1024).toFixed(0) : '—'}<small>KB</small></b></div>
                                        <div class="az-tile"><span>Categories</span><b>${Object.keys(gStats).length}</b></div>
                                    </div>
                                </div>
                            </div>

                            ${Object.keys(groupScores).length ? `
                                <div class="az-towers-card">
                                    <div class="az-panel-head"><h3>Score by category</h3><span class="count">${azScoredCount(groupScores)} of ${Object.keys(gStats).length} categories scored &middot; weakest first</span></div>
                                    ${azTowersHTML(groupScores)}
                                </div>` : ''}

                            <div class="az-towers-card" id="az-cwv-card" hidden></div>

                            <div class="az-panel">
                                <div class="az-panel-head">
                                    <h3>Detailed findings</h3>
                                    <span class="count">${checks.length} checks</span>
                                </div>
                                <p class="az-filter-note" id="az-filter-note"></p>
                                <div class="az-build-bar" id="az-build-bar"></div>
                                <div id="az-checks-wrap">
                                    <div id="az-check-list"></div>
                                </div>
                                <div class="az-gate" id="az-gate" hidden>
                                    <div class="az-gate-lock">🔒</div>
                                    <h3>See all <span id="az-gate-total">${checks.length}</span> results</h3>
                                    <p>Provide your email address to view the full results and receive a free PDF copy — free, no card required.</p>
                                    <form id="az-gate-form" novalidate>
                                        <input type="text" id="az-gate-name" placeholder="Your name" required autocomplete="name">
                                        <div class="az-gate-email-row">
                                            <input type="email" id="az-gate-email" placeholder="Enter your email to unlock the full report" required autocomplete="email">
                                            <button type="submit">Unlock &amp; email me the PDF</button>
                                        </div>
                                        <div class="az-gate-hp" aria-hidden="true">
                                            <label for="az-gate-hp">Leave this empty</label>
                                            <input type="text" id="az-gate-hp" tabindex="-1" autocomplete="off">
                                        </div>
                                    </form>
                                    <p class="az-gate-note" id="az-gate-note" role="status" aria-live="polite"></p>
                                </div>
                            </div>
                        </main>
                    </div>
                </div>
                <div class="az-nomeasure">
                    <b>What this report deliberately does not show.</b>
                    ${esc((data.authority && data.authority.reason) ? data.authority.reason : 'Backlink and authority data require direct crawler API connections.')}
                    Every number above is measured from your live page or returned by Google &mdash; none of it is estimated.
                </div>
                <div id="azwc-followup-cta" class="az-audit-card" style="margin-top:24px;" hidden>
                    <h3 style="margin:0 0 8px;color:#fff;">Want these fixed?</h3>
                    <p style="color:rgba(255,255,255,0.6);margin:0 0 14px;">We are in Gilbert. Call
                        <a href="tel:+14808185761" style="color:var(--az-gold);">(480) 818-5761</a> or email
                        <a href="mailto:info@azwebcorp.com" style="color:var(--az-gold);">info@azwebcorp.com</a>
                        and we will walk through this report with you — no charge, no obligation.</p>
                </div>
            `;

            if (targetParam) { document.body.classList.add('azwc-audit-fullpage'); document.documentElement.classList.add('azwc-audit-fullpage'); }

            azAnimateCharts(results);
            azWireNav(results);

            const rerun = results.querySelector('#az-rep-rerun');
            // Without fresh:true this re-read the same cached report for six
            // hours and looked like a button that did nothing.
            if (rerun) rerun.addEventListener('click', () => runAudit(domain, { fresh: true }));

            startReveal(checks, data, domain);
            azLoadPsi(domain, results);
        }

        /* ---- gated, animated reveal ------------------------------------ */

        const TEASER_COUNT = 5;
        const GHOST_COUNT = 3;
        const STAGGER_MS = 90;
        const UNLOCK_ENDPOINT = container.dataset.endpoint.replace(/\/audit$/, '/unlock');
        const LEAD_COOKIE = 'azwc_lead';

        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        function getCookie(name) {
            const m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
            return m ? decodeURIComponent(m[1]) : null;
        }

        function setCookie(name, value, days) {
            const d = new Date();
            d.setTime(d.getTime() + days * 864e5);
            document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + d.toUTCString() + '; path=/; SameSite=Lax';
        }

        function knownLead() {
            try {
                const raw = getCookie(LEAD_COOKIE);
                const obj = raw ? JSON.parse(raw) : null;
                return (obj && obj.email) ? obj : null;
            } catch (e) { return null; }
        }

        function checkIcon(status) {
            return status === 'pass' ? '✅' : status === 'warn' ? '⚠️' : status === 'fail' ? '❌' : 'ℹ️';
        }

        function checkItemHTML(c) {
            let list = '';
            if (c.items && c.items.length > 0) {
                // Any check carrying real evidence (actual URLs, not just a
                // count) starts expanded — that's the concrete proof a
                // visitor needs to see this is a genuine crawl, not a canned
                // result. Ghost rows stay safe: the surrounding .az-ghost
                // blur + pointer-events:none still hides the content and
                // blocks clicking either way.
                const openAttr = ' open';
                list = `<details${openAttr} style="margin-top:4px;">
                    <summary style="cursor:pointer;font-size:11px;color:rgba(255,255,255,0.3);">Show details (${c.items.length})</summary>
                    <ul style="margin:4px 0 0 0;padding-left:16px;font-size:11px;color:rgba(255,255,255,0.4);max-height:80px;overflow-y:auto;">
                        ${c.items.map(item => `<li style="word-break:break-all;">${esc(item)}</li>`).join('')}
                    </ul>
                </details>`;
            }
            return `<div class="az-check-icon">${checkIcon(c.status)}</div>
                <div class="az-check-text">
                    <span class="az-status-pill ${c.status}">${esc(c.status.toUpperCase())}</span>
                    <h4>${esc(c.label)}</h4>
                    <p class="detail">${esc(c.detail)}</p>
                    ${c.plain ? `<p class="explainer">💡 ${esc(c.plain)}</p>` : ''}
                    ${c.group ? `<span class="az-group-badge">${esc(c.group)}</span>` : ''}
                    ${list}
                </div>`;
        }

        /** One real, fully-shown result — the icon strip "builds" alongside it. */
        function appendReal(list, buildBar, c) {
            const seg = document.createElement('div');
            seg.className = 'az-build-seg az-build-' + c.status;
            buildBar.appendChild(seg);
            requestAnimationFrame(() => seg.classList.add('is-in'));

            const el = document.createElement('div');
            el.className = 'az-check-item az-anim-in';
            el.innerHTML = checkItemHTML(c);
            list.appendChild(el);
            requestAnimationFrame(() => el.classList.add('is-in'));
        }

        /**
         * A locked row: the real status colour shows (so a visitor can see
         * real fails are waiting), but the label/detail text is blurred, and
         * later rows fade toward fully invisible — "fading till completely
         * disappearing" into the gate below.
         */
        function jumpToGate() {
            const gate = document.getElementById('az-gate');
            if (!gate) return;
            gate.scrollIntoView({ behavior: 'smooth', block: 'center' });
            const email = document.getElementById('az-gate-email');
            if (email) setTimeout(() => email.focus(), 350);
        }

        function appendGhost(list, buildBar, c, depth) {
            const seg = document.createElement('div');
            seg.className = 'az-build-seg az-build-' + c.status + ' is-ghost';
            buildBar.appendChild(seg);
            requestAnimationFrame(() => seg.classList.add('is-in'));

            const el = document.createElement('div');
            el.className = 'az-check-item az-anim-in az-ghost az-ghost-' + depth;
            el.innerHTML = checkItemHTML(c);
            // A blurred row's natural next move is to click it trying to
            // read it — send that click straight to the unlock form instead
            // of doing nothing, so there's no hunting for where to go.
            el.style.cursor = 'pointer';
            el.addEventListener('click', jumpToGate);
            list.appendChild(el);
            requestAnimationFrame(() => el.classList.add('is-in'));
        }

        function revealSequence(list, buildBar, items, renderer, onDone) {
            let i = 0;
            (function step() {
                if (i >= items.length) { if (onDone) onDone(); return; }
                renderer(items[i]);
                i++;
                setTimeout(step, STAGGER_MS);
            })();
        }

        function callUnlock(name, email, domain, score, hp, elapsed) {
            return fetch(UNLOCK_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: name, email: email, domain: domain, score: score, hp: hp || '', elapsed: elapsed })
            }).then(r => r.json().catch(() => ({})).then(body => ({ ok: r.ok, status: r.status, body: body })));
        }

        function unlockFollowupCta(domain) {
            const cta = document.getElementById('azwc-followup-cta');
            if (cta) cta.hidden = false;
            if (typeof CustomEvent === 'function') {
                container.dispatchEvent(new CustomEvent('azwc:rendered', {
                    bubbles: true,
                    detail: { domain: domain }
                }));
            }
        }

        function startReveal(checks, data, domain) {
            const buildBar = document.getElementById('az-build-bar');
            const list = document.getElementById('az-check-list');
            const wrap = document.getElementById('az-checks-wrap');
            const gate = document.getElementById('az-gate');
            const score = data.score?.overall ?? null;
            const lead = knownLead();

            function revealRest(fromIndex) {
                revealSequence(list, buildBar, checks.slice(fromIndex), (c) => appendReal(list, buildBar, c));
            }

            function showGateForm(afterIndex) {
                wrap.classList.add('is-locked');
                gate.hidden = false;
                if (stickyCta) stickyCta.hidden = false;
                const form = document.getElementById('az-gate-form');
                const note = document.getElementById('az-gate-note');
                const shownAt = Date.now();

                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const name = document.getElementById('az-gate-name').value.trim();
                    const email = document.getElementById('az-gate-email').value.trim();
                    const hp = document.getElementById('az-gate-hp').value;
                    const btn = form.querySelector('button');

                    note.textContent = '';
                    btn.disabled = true;
                    btn.textContent = 'Unlocking…';

                    callUnlock(name, email, domain, score, hp, Date.now() - shownAt).then(res => {
                        if (res.ok && res.body.ok) {
                            setCookie(LEAD_COOKIE, JSON.stringify({ name: name, email: email }), 180);
                            wrap.classList.remove('is-locked');
                            gate.hidden = true;
                            if (stickyCta) stickyCta.hidden = true;
                            // Ghost placeholders covered these same indexes; fade
                            // them out before the real versions replace them, so
                            // nothing briefly shows twice.
                            list.querySelectorAll('.az-ghost').forEach(el => { el.style.transition = 'opacity 0.25s ease'; el.style.opacity = '0'; });
                            buildBar.querySelectorAll('.is-ghost').forEach(el => el.remove());
                            setTimeout(() => {
                                list.querySelectorAll('.az-ghost').forEach(el => el.remove());
                                revealRest(afterIndex);
                            }, 260);
                            unlockFollowupCta(domain);
                            return;
                        }
                        btn.disabled = false;
                        btn.textContent = 'Unlock & email me the PDF';
                        note.textContent = (res.body && res.body.error) || 'Something went wrong. Please try again.';
                    }).catch(() => {
                        btn.disabled = false;
                        btn.textContent = 'Unlock & email me the PDF';
                        note.textContent = 'We could not reach the server. Please try again.';
                    });
                });
            }

            function teaseAndGate() {
                const teaser = checks.slice(0, TEASER_COUNT);
                const ghosts = checks.slice(TEASER_COUNT, TEASER_COUNT + GHOST_COUNT);
                revealSequence(list, buildBar, teaser, (c) => appendReal(list, buildBar, c), () => {
                    revealSequence(list, buildBar, ghosts, (c) => appendGhost(list, buildBar, c, ghosts.indexOf(c) + 1), () => {
                        showGateForm(TEASER_COUNT);
                    });
                });
            }

            if (lead) {
                // Known visitor: try a silent re-unlock before showing anything
                // real, so someone still within their limit never sees a gate.
                callUnlock(lead.name, lead.email, domain, score, '', 5000).then(res => {
                    if (res.ok && res.body.ok) {
                        revealRest(0);
                        unlockFollowupCta(domain);
                    } else {
                        teaseAndGate();
                        if (res.status === 403) {
                            window.setTimeout(() => {
                                const note = document.getElementById('az-gate-note');
                                const form = document.getElementById('az-gate-form');
                                if (note && form) {
                                    form.hidden = true;
                                    note.textContent = (res.body && res.body.error) || "You've used your free full audits.";
                                }
                                if (stickyCta) stickyCta.hidden = true;
                            }, (TEASER_COUNT + GHOST_COUNT) * STAGGER_MS + 100);
                        }
                    }
                }).catch(teaseAndGate);
            } else {
                teaseAndGate();
            }
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}
