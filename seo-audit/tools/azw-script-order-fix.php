<?php
/**
 * Plugin Name: AZW Script Order Fix
 * Description: Stops defer being applied to scripts whose inline consumers run synchronously.
 * Version: 1.0.0
 * Author: AZWebCorp
 *
 * ---------------------------------------------------------------------------
 * Flying Pages adds `defer` to enqueued scripts via flying_pages_add_defer()
 * on script_loader_tag. That is fine for self-contained libraries, but the Meta
 * Pixel plugin prints a synchronous inline block immediately after its library:
 *
 *     <script defer id="facebook-signal-js" src="...">   <-- runs after parse
 *     <script>FacebookSignal.init({...})</script>        <-- runs during parse
 *
 * The inline call therefore executes before the class exists and throws
 * "FacebookSignal is not defined", leaving the pixel's signal/CAPI layer
 * uninitialised on every page view.
 *
 * Rather than disable deferring site-wide (it is a real performance win for the
 * other ~30 scripts), strip `defer` from just the handles that have a
 * synchronous inline dependant. Runs at PHP_INT_MAX so it wins regardless of
 * what else filters the tag.
 * ---------------------------------------------------------------------------
 */

defined('ABSPATH') || exit;

/**
 * Handles that must stay synchronous because sibling inline code calls into
 * them during parse. Keep this list tight - each entry gives up a small
 * performance benefit, so only add a handle with a demonstrated ReferenceError.
 */
function azw_script_order_sync_handles() {
    return apply_filters('azw_script_order_sync_handles', array(
        'facebook-signal',   // Meta Pixel: inline FacebookSignal.init() follows it
    ));
}

function azw_script_order_strip_defer($tag, $handle) {
    if (!in_array($handle, azw_script_order_sync_handles(), true)) {
        return $tag;
    }
    // Remove the attribute in whichever form it was written.
    $tag = preg_replace('#\sdefer(=([\'"])defer\2)?#i', '', $tag);
    $tag = preg_replace('#\sasync(=([\'"])async\2)?#i', '', $tag);
    return $tag;
}

/**
 * Registered late, on purpose.
 *
 * wp-asset-clean-up also filters script_loader_tag at PHP_INT_MAX. Equal
 * priorities run in registration order, and a mu-plugin registers before every
 * regular plugin - so hooking at load time put this callback FIRST and let
 * asset-clean-up re-emit the tag afterwards, defer intact. Attaching the filter
 * from inside a late action instead puts it last in the PHP_INT_MAX queue.
 */
add_action('wp_enqueue_scripts', static function () {
    add_filter('script_loader_tag', 'azw_script_order_strip_defer', PHP_INT_MAX, 2);
}, PHP_INT_MAX);

/**
 * Last line of defence: Autoptimize rewrites script tags inside its own output
 * buffer, AFTER every script_loader_tag filter has run, re-emitting them as
 * `<script defer src=".../autoptimize_single_*.js">`. Nothing hooked to
 * script_loader_tag can influence that, which is why excluding the handle there
 * (and via autoptimize_js_exclude) left the defer in place.
 *
 * AO exposes the finished HTML on autoptimize_html_after_minify, so strip the
 * attribute there instead - by id, so exactly one tag is touched. The generic
 * `wp_loaded` ob_start fallback covers the case where AO is disabled and this
 * filter never fires.
 */
function azw_script_order_fix_html($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }
    return preg_replace_callback(
        '#<script\b[^>]*\bid=(["\'])facebook-signal-js\1[^>]*></script>#i',
        static function ($m) {
            return preg_replace('#\s(?:defer|async)(?:=(["\'])[^"\']*\1)?#i', '', $m[0]);
        },
        $html
    );
}
add_filter('autoptimize_html_after_minify', 'azw_script_order_fix_html', PHP_INT_MAX);
