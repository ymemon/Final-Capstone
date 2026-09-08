<?php
/**
 * Add the case-studies cross-link to /arizona-seo-services/, which is built in
 * Elementor and therefore cannot be reached by editing post_content.
 *
 *     wp --path=/html eval-file azw-elementor-add-casestudy-link.php          # DRY RUN
 *     wp --path=/html eval-file azw-elementor-add-casestudy-link.php apply    # write
 *
 * The whole page lives in a single Elementor HTML widget, so this inserts a
 * section into that widget's markup rather than building new Elementor nodes -
 * fewer moving parts, and it inherits the page's own classes.
 *
 * The original _elementor_data is copied to postmeta before the first write.
 * Re-running is a no-op once the link is present.
 */

$apply = (bool) array_intersect(array('apply', '--apply'), (array) $args);

const POST_ID = 663;
const WIDGET_ID = 'fa18962';
const BACKUP_META = '_azw_elementor_backup_casestudy_link';

// Matches the page's existing section markup: azwc-sec wrapper, azwc-wrap
// inner, azwc-kicker eyebrow, azwc-actions button row.
$section = '<section class="azwc-sec"><div class="azwc-wrap"><div class="azwc-kicker">Proof</div>'
    . '<h2>Results You Can Verify</h2>' . "\n"
    . '<p class="azwc-lead">Published client work, with the measurements attached, is on our '
    . '<a href="/case-studies/">case studies page</a>.</p>'
    . '<p>We only put an engagement there when the client has agreed to be named and there is a '
    . 'measured before-and-after to show&mdash;which is why the list is short, and why every figure '
    . 'on it can be checked.</p>'
    . '<p>The current entry covers a location-page consolidation for a managed IT provider: clicks to '
    . 'the affected pages rose 32 percent, and two of the four pages gained more than ten positions. '
    . 'The write-up also records what did not go to plan, because that is the part that tells you how '
    . 'an agency reports when the numbers are mixed.</p>'
    . '<div class="azwc-actions"><a class="azwc-btn azwc-secondary" href="/case-studies/">'
    . 'Read the case study</a></div></div></section>' . "\n";

$raw = get_post_meta(POST_ID, '_elementor_data', true);
if (!$raw) {
    WP_CLI::error('no _elementor_data on post ' . POST_ID);
}
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = json_decode(wp_unslash($raw), true);
}
if (!is_array($data)) {
    WP_CLI::error('could not decode _elementor_data');
}

$hit = false;

$walk = function (&$nodes) use (&$walk, &$hit, $section) {
    foreach ($nodes as &$node) {
        if (($node['id'] ?? '') === WIDGET_ID) {
            $html = (string) ($node['settings']['html'] ?? '');
            if ('' === $html) {
                WP_CLI::warning('widget has no html setting');
                return;
            }
            if (false !== strpos($html, '/case-studies/')) {
                WP_CLI::line('already links to /case-studies/ - nothing to do');
                return;
            }

            // Anchor on the final call-to-action heading, then step back to the
            // <section> that opens it, so the new block lands immediately
            // before that closing pitch rather than after it.
            if (!preg_match('#<h2[^>]*>\s*Ready for a More Methodical SEO Strategy\?\s*</h2>#i', $html, $m, PREG_OFFSET_CAPTURE)) {
                WP_CLI::warning('anchor heading not found - page markup has changed, not editing');
                return;
            }
            $open = strrpos(substr($html, 0, $m[0][1]), '<section');
            if (false === $open) {
                WP_CLI::warning('no <section> found before the anchor - not editing');
                return;
            }

            $node['settings']['html'] = substr($html, 0, $open) . $section . substr($html, $open);
            $hit = true;
            WP_CLI::line(sprintf(
                'widget %s: %d -> %d bytes, inserted before the final CTA section',
                WIDGET_ID,
                strlen($html),
                strlen($node['settings']['html'])
            ));
            return;
        }
        if (!empty($node['elements'])) {
            $walk($node['elements']);
        }
    }
};
$walk($data);

if (!$hit) {
    WP_CLI::line('no change made');
    return;
}

if (!$apply) {
    WP_CLI::line('');
    WP_CLI::line("DRY RUN - nothing written. Re-run with 'apply'.");
    return;
}

if (!get_post_meta(POST_ID, BACKUP_META, true)) {
    update_post_meta(POST_ID, BACKUP_META, $raw);
}

// Elementor stores this slashed; saving unslashed corrupts quotes in the markup.
update_post_meta(POST_ID, '_elementor_data', wp_slash(wp_json_encode($data)));

// Force Elementor to rebuild the page's CSS, otherwise the new section can
// render unstyled until something else invalidates the cache.
delete_post_meta(POST_ID, '_elementor_css');
if (class_exists('\Elementor\Plugin')) {
    \Elementor\Plugin::$instance->files_manager->clear_cache();
    WP_CLI::line('Elementor CSS cache cleared');
}
wp_cache_flush();

WP_CLI::success('arizona-seo-services updated (original in postmeta ' . BACKUP_META . ')');
WP_CLI::line('Flush the CDN before checking the live page.');
