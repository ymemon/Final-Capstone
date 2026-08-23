<?php
/**
 * Plugin Name: AZW CDN Auto-Purge
 * Description: Purges the GoDaddy CDN edge when content changes. Without this, edits sit behind a 31-day edge TTL.
 * Version: 1.0.0
 * Author: AZWebCorp
 *
 * ---------------------------------------------------------------------------
 * WHY THIS EXISTS
 *
 * GoDaddy's platform serves HTML with `Cache-Control: public, max-age=2678400`
 * (31 days). That is only safe if something purges the edge when content
 * changes. WPaaS\Cache_V2 wires do_ban_no_flush()/do_purge() to every relevant
 * event (save_post, clean_post_cache, wp_update_nav_menu, ...) which handles
 * the Varnish/gateway layer - but flush_cdn(), the method that actually calls
 * the CDN purge API, is hooked to NOTHING. Verified 2026-08-23 by enumerating
 * $wp_filter for every callback naming those methods.
 *
 * Observed consequence: the home page kept serving stale markup through
 * `wp cache flush`, repeated `wp post update` resaves, an Autoptimize cache
 * clear and several direct do_ban() calls (CF-Cache-Status: HIT,
 * x-gateway-cache-status: HIT). A single manual flush_cdn() released it in
 * about twenty seconds.
 *
 * DESIGN NOTES
 *
 * - Deferred to `shutdown` so the editor's save request returns before an
 *   outbound API call is made; the purge is not on the critical path.
 * - Throttled. flush_cdn() purges the whole zone, so firing it once per post
 *   in a bulk edit would be wasteful and invites rate limiting. One purge per
 *   THROTTLE seconds is enough - the edge only needs to be told once.
 * - Revisions, autosaves and post_status transitions that never touch a public
 *   URL are ignored, otherwise every autosave tick would queue a zone purge.
 * ---------------------------------------------------------------------------
 */

defined('ABSPATH') || exit;

const AZW_CDN_PURGE_THROTTLE = 60;   // seconds between real purges
const AZW_CDN_PURGE_LOCK     = 'azw_cdn_purge_last';

/** Mark that something changed; the actual call happens on shutdown. */
function azw_cdn_mark_dirty() {
    if (!defined('AZW_CDN_DIRTY')) {
        define('AZW_CDN_DIRTY', true);
    }
}

/** Only purge for transitions that can change what a visitor sees. */
function azw_cdn_on_transition($new_status, $old_status, $post) {
    if (!$post instanceof WP_Post) {
        return;
    }
    if (wp_is_post_revision($post) || wp_is_post_autosave($post)) {
        return;
    }
    if ('publish' !== $new_status && 'publish' !== $old_status) {
        return;   // draft -> draft and similar never affect the edge
    }
    azw_cdn_mark_dirty();
}
add_action('transition_post_status', 'azw_cdn_on_transition', 10, 3);

foreach (array(
    'wp_update_nav_menu',
    'wp_delete_nav_menu',
    'switch_theme',
    'customize_save_after',
    'activated_plugin',
    'deactivated_plugin',
) as $azw_cdn_hook) {
    add_action($azw_cdn_hook, 'azw_cdn_mark_dirty', 10, 0);
}

/**
 * Do the purge, once, after the response has been handed back.
 */
function azw_cdn_maybe_purge() {
    if (!defined('AZW_CDN_DIRTY')) {
        return;
    }
    if (empty($GLOBALS['wpaas_cache_class']) || !method_exists($GLOBALS['wpaas_cache_class'], 'flush_cdn')) {
        return;   // not on GoDaddy WPaaS, or the API changed - fail quiet
    }
    $last = (int) get_transient(AZW_CDN_PURGE_LOCK);
    if ($last && (time() - $last) < AZW_CDN_PURGE_THROTTLE) {
        return;
    }
    set_transient(AZW_CDN_PURGE_LOCK, time(), AZW_CDN_PURGE_THROTTLE * 2);
    $GLOBALS['wpaas_cache_class']->flush_cdn();
}
add_action('shutdown', 'azw_cdn_maybe_purge', 5);

/**
 * wp azw cdn-purge  - force one now, ignoring the throttle.
 */
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('azw cdn-purge', function () {
        if (empty($GLOBALS['wpaas_cache_class']) || !method_exists($GLOBALS['wpaas_cache_class'], 'flush_cdn')) {
            WP_CLI::error('No WPaaS cache class with flush_cdn() available.');
        }
        delete_transient(AZW_CDN_PURGE_LOCK);
        $GLOBALS['wpaas_cache_class']->flush_cdn();
        WP_CLI::success('CDN purge requested.');
    });
}
