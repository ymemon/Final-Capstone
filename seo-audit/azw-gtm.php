<?php
/**
 * Plugin Name: AZW Google Tag Manager
 * Description: Installs GTM container GTM-WDC9KMVG — loader high in <head>, noscript iframe immediately after <body>.
 * Version: 1.0.0
 *
 * HEADS UP: THIS IS THE SECOND GTM CONTAINER ON THIS SITE
 * Google Site Kit is already running **GTM-MXDXSS8W** (configured in the
 * `googlesitekit_tagmanager_settings` option, not in any file). Adding this
 * one does not replace it — both will now load.
 *
 * Two containers is legitimate, and common where an agency runs its own
 * alongside a client's. But if BOTH containers fire Google Analytics tags to
 * the same property, every pageview and event is counted twice, and that is
 * not obvious from the reports — it just looks like traffic doubled. Worth
 * checking in each container's Tag list before trusting any figures, and
 * removing whichever GA tag is redundant. Site Kit's container is disabled
 * from wp-admin (Site Kit → Settings → Tag Manager), not by deleting a file.
 *
 * PLACEMENT
 * - Loader: wp_head at priority 1, which puts it above everything except the
 *   charset/viewport meta WordPress emits first. That is as high as a plugin
 *   can place it without output-buffering the whole document, which costs more
 *   than the few milliseconds it would gain.
 * - noscript: wp_body_open, which fires immediately after <body>. Verified the
 *   active theme (bosa) actually calls it — many themes silently do not, and
 *   the iframe would then never render.
 *
 * AUTOPTIMIZE DROPS THIS SCRIPT WITHOUT data-noptimize
 * Autoptimize is active here with inline-JS optimisation on. On the first
 * deploy it removed the loader **entirely** — it was not in the page and not
 * in any of the five aggregate bundles either, while the noscript iframe
 * survived because that is HTML rather than JS. The container would have
 * looked installed (the iframe is right there in the source) and measured
 * nothing for every JavaScript-enabled visitor, which is all of them.
 *
 * data-noptimize="1" keeps it inline. data-cfasync="false" additionally stops
 * Cloudflare Rocket Loader deferring it, which would delay the container past
 * the point where early pageview events fire.
 *
 * FLYING SCRIPTS LAZY-LOADS THIS — BY EXISTING SITE POLICY, NOT BY ACCIDENT
 * The Flying Scripts plugin has "googletagmanager" in its include list
 * (`flying_scripts_include_list`), so it rewrites this tag to
 * `data-type="lazy" data-src="data:text/javascript;base64,..."` and holds it
 * until user interaction or a 3-second timeout.
 *
 * The container IS installed and the payload decodes to exactly this snippet —
 * but every plain-text check for "gtm.js" or "GTM-WDC9KMVG" in the page source
 * FAILS, because the code is base64. That looks identical to "the tag is
 * missing" and cost real time to diagnose. Decode the data-src before
 * concluding anything is broken.
 *
 * Autoptimize additionally strips HTML comments (`autoptimize_html_keepcomments`
 * is off), so the `<!-- Google Tag Manager -->` markers never appear either.
 *
 * The delay is a deliberate performance trade-off the site already applied to
 * the previous container. It costs measurement of immediate bounces. To load
 * GTM immediately instead, remove "googletagmanager" from Flying Scripts'
 * include list — do that knowingly, it will affect Core Web Vitals.
 *
 * Deliberately not loaded in wp-admin, on login, during REST/AJAX/cron, or for
 * logged-in administrators: tracking your own sessions pollutes the data.
 *
 * Safe at the mu-plugins root: pure hook registration.
 */

defined('ABSPATH') || exit;

const AZW_GTM_ID = 'GTM-WDC9KMVG';

/** Should the tag load for this request? */
function azw_gtm_active(): bool {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
        return false;
    }
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return false;
    }
    if (function_exists('is_login') && is_login()) {
        return false;
    }
    // Don't measure the people who run the site.
    if (current_user_can('manage_options')) {
        return false;
    }
    return true;
}

add_action('wp_head', static function (): void {
    if (!azw_gtm_active()) {
        return;
    }
    $id = AZW_GTM_ID;
    ?>
<!-- Google Tag Manager -->
<script data-noptimize="1" data-cfasync="false">(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','<?php echo esc_js($id); ?>');</script>
<!-- End Google Tag Manager -->
    <?php
}, 1);

add_action('wp_body_open', static function (): void {
    if (!azw_gtm_active()) {
        return;
    }
    $id = AZW_GTM_ID;
    ?>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr($id); ?>"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
    <?php
}, 1);
