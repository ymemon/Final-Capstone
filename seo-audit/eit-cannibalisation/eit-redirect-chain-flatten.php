<?php
/**
 * Plugin Name: EIT — flatten redirect chains
 * Description: Sends five URLs straight to their final destination instead of
 *              through a second redirect, and rescues one live 404.
 * Version: 1.0.0
 *
 * WHY THIS EXISTS AND WHY IT IS SEPARATE
 * `eit-legacy-slug-redirects.php` is a large, working, hand-maintained map. Four
 * of the URLs below reach their destination through it (or through WordPress's
 * own canonical guessing) in two hops rather than one. Each extra hop leaks a
 * little of the ranking signal a 301 is there to pass, and Google gives up after
 * a few. This file runs first and short-circuits those hops.
 *
 * It is deliberately a separate file rather than an edit to that map: it is five
 * lines of behaviour, it is verifiable on its own, and deleting this one file
 * restores the previous behaviour exactly. Fold these entries into the main map
 * whenever that file is next being edited properly, then delete this.
 *
 * WHAT WAS VERIFIED BEFORE WRITING IT (production, 2026-09-09, cache-busted)
 *   /it-support-cork-2/               301 -> /it-support-cork/ 301 -> /cork/ 200
 *   /it-support-ireland-nationwide-2/ 301 -> /it-support-ireland-nationwide/
 *                                     301 -> /managed-it-services/ 200
 *   /it-support-south-dublin/         301 -> /it-support-ireland-nationwide/
 *                                     301 -> /managed-it-services/ 200
 *   /dublin-city-centre/              301 -> /it-support-dublin-city-centre/
 *                                     301 -> /managed-it-services/ 200
 *   /managed-services/                404, with 419 impressions in Search Console
 *                                     (page 988434 is a draft; its live
 *                                     equivalent is /managed-it-services/)
 * Every destination was confirmed to return 200 and to not itself redirect, so
 * none of these can form a loop.
 *
 * WHAT THIS FILE DELIBERATELY DOES NOT DO
 * A twenty-entry consolidation map was drafted for this site from Search Console
 * data. Checked against production it turned out that most of it was already in
 * place, that the "duplicate" pages it targeted are unpublished drafts rather
 * than competing live pages, and that five of its rules pointed at URLs which
 * already redirect the other way - /procurement/, /third-party-services/ and
 * /business-continuity/ would each have entered an infinite redirect loop and
 * gone down. It was not deployed. See FINDINGS.md.
 *
 * PRIORITY
 * -1, so this runs ahead of eit-legacy-slug-redirects.php (0) and ahead of
 * WordPress's own redirect_canonical (10), both of which supply the first hop
 * of the chains above.
 */

defined('ABSPATH') || exit;

add_action('template_redirect', static function (): void {
    if (is_admin() || wp_doing_ajax() || is_feed()) {
        return;
    }

    // Keys are stored without a trailing slash, matching the convention in
    // eit-legacy-slug-redirects.php.
    $map = [
        '/it-support-cork-2'               => '/cork/',
        '/it-support-ireland-nationwide-2' => '/managed-it-services/',
        '/it-support-south-dublin'         => '/managed-it-services/',
        '/dublin-city-centre'              => '/managed-it-services/',
        '/managed-services'                => '/managed-it-services/',
    ];

    $path = wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return;
    }
    $path = untrailingslashit(strtolower($path));

    if (!isset($map[$path])) {
        return;
    }

    $target = home_url($map[$path]);

    // Cheap insurance: a rule that resolves to its own URL is an infinite loop
    // and takes the page down. The map above is verified loop-free, but this
    // means a future typo degrades to "no redirect" rather than "site down".
    if (untrailingslashit($target) === untrailingslashit(home_url($path))) {
        return;
    }

    // Campaign tags and gclid must survive the redirect.
    $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
    if ($qs !== '') {
        $target .= (strpos($target, '?') === false ? '?' : '&') . $qs;
    }

    wp_safe_redirect($target, 301, 'EIT Flatten Redirect Chains');
    exit;
}, -1);
