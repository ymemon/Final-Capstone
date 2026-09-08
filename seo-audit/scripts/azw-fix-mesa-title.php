<?php
/**
 * Fix the title tag on /web-design-mesa-az/.
 *
 *     wp --path=/html eval-file azw-fix-mesa-title.php          # DRY RUN
 *     wp --path=/html eval-file azw-fix-mesa-title.php apply    # write
 *
 * The page was serving "Arizona Web Design Services | Custom Business
 * Websites" - a statewide title on a city page, naming neither Mesa nor the
 * brand. Every other page in this cluster that has a title names its city, so
 * this one was both off-pattern and competing with the statewide page for the
 * same phrasing.
 *
 * Matched to the Gilbert page, which is the benchmark for the cluster:
 * "Web Design in Gilbert, AZ | AZWebCorp".
 *
 * Worth knowing: this page is currently noindex,follow and holds 293 bytes of
 * content, so the corrected title changes nothing in search until real content
 * is written and the noindex lifted. It is fixed now so the page is not
 * carrying a known-wrong title while that work is queued.
 */

$apply = (bool) array_intersect(array('apply', '--apply'), (array) $args);

const SLUG = 'web-design-mesa-az';
const NEW_TITLE = 'Web Design in Mesa, AZ | AZWebCorp';
const NEW_DESC = 'Web design for Mesa businesses from AZWebCorp in nearby Gilbert. Straight assessments and realistic timelines. Call (480) 818-5761.';

$page = get_page_by_path(SLUG, OBJECT, 'page');
if (!$page) {
    WP_CLI::error(SLUG . ': not found');
}

$old_title = get_post_meta($page->ID, 'rank_math_title', true);
$old_desc = get_post_meta($page->ID, 'rank_math_description', true);

WP_CLI::line(sprintf('%s (ID %d)', SLUG, $page->ID));
WP_CLI::line('  title now: ' . ($old_title ?: '(none)'));
WP_CLI::line('  title new: ' . NEW_TITLE);
WP_CLI::line('  desc now : ' . ($old_desc ?: '(none)'));
WP_CLI::line('  desc new : ' . NEW_DESC);

if (!$apply) {
    WP_CLI::line('');
    WP_CLI::line("DRY RUN - nothing written. Re-run with 'apply'.");
    return;
}

update_post_meta($page->ID, 'rank_math_title', NEW_TITLE);
update_post_meta($page->ID, 'rank_math_description', NEW_DESC);
wp_cache_flush();

WP_CLI::success('title and description corrected');
