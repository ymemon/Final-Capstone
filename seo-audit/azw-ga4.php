<?php
/**
 * Plugin Name: AZW Google Analytics 4
 * Description: Sends azwebcorp.com pageviews to G-R5RNDSH327, the verified AZ Web Corp GA4 stream.
 * Version: 1.0.0
 *
 * WHY THIS EXISTS
 * Until now this site reported into **G-FSGZKFRENB** (GA4 property 451955733,
 * account 323214753), configured through Google Site Kit. That Site Kit
 * connection belongs to WordPress user #3, **Junaid <junaid.p.110@gmail.com>**
 * — a separate administrator — not to Yasir. So the site's analytics were
 * going to a Google account Yasir has no access to, which is why every attempt
 * to read GA data failed and why he only has one measurement ID of his own.
 *
 * This adds **G-R5RNDSH327**, the verified AZ Web Corp stream, so the data he is
 * accountable for lands somewhere he can actually see.
 *
 * ADDED ALONGSIDE, NOT INSTEAD OF — DELIBERATELY
 * The old tag is left running. Two GA4 properties collecting in parallel is
 * not double-counting: each property counts each event once, they are simply
 * separate datasets. Removing the old one is a decision about Junaid's access
 * and about historical continuity, and it is not mine to make unprompted. It
 * also means there is no gap in collection while the changeover is decided.
 *
 * To retire the old tag once that is settled: Site Kit → Settings →
 * Analytics → disconnect, or set `useSnippet` false in
 * `googlesitekit_analytics-4_settings`. Do NOT simply delete the option; Site
 * Kit re-syncs it from Google and would restore it.
 *
 * FOR THE DATA API
 * A measurement ID cannot be queried. The GA4 Data API needs the numeric
 * property ID for G-R5RNDSH327 is 247570709. The Data API
 * enabled in the Cloud project, and the service account
 * gsc-reader@azwebcorp-gsc-77313.iam.gserviceaccount.com added to that
 * property as a Viewer.
 *
 * FLYING SCRIPTS WILL LAZY-LOAD THIS
 * "googletagmanager" is in Flying Scripts' include list, so this tag is
 * rewritten to a base64 data-src and held until interaction or a 3s timeout,
 * exactly like the GTM container. Searching the page source for
 * "G-R5RNDSH327" therefore finds nothing even when it is working — decode the
 * data-src, or watch the network for a /g/collect request, before concluding
 * it is broken.
 *
 * Safe at the mu-plugins root: pure hook registration.
 */

defined('ABSPATH') || exit;

const AZW_GA4_ID = 'G-R5RNDSH327';

add_action('wp_head', static function (): void {

    if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
        return;
    }
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }
    // Don't measure the people who run the site.
    if (current_user_can('manage_options')) {
        return;
    }

    $id = AZW_GA4_ID;
    ?>
<!-- Google Analytics 4 (AZ Web Corp) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($id); ?>"></script>
<script data-noptimize="1" data-cfasync="false">
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '<?php echo esc_js($id); ?>');
</script>
<!-- End Google Analytics 4 -->
    <?php
}, 2);
