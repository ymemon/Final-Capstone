<?php
/**
 * Retire the business-email-hosting draft (ID 2292) so it can never be
 * published alongside /business-email/ and compete for the same intent.
 *
 *     wp --path=/html eval-file azw-retire-business-email-hosting.php          # DRY RUN
 *     wp --path=/html eval-file azw-retire-business-email-hosting.php apply    # write
 *
 * Trashed rather than force-deleted: trash is what WordPress's own Delete does
 * and it stays recoverable, which matters because this page's copy may be the
 * source the live /business-email/ page was built from.
 *
 * A 301 is registered at the same time. The slug is a draft today, so it
 * already 404s, but it was published at some point and may still be linked or
 * indexed - and once trashed it can never come back on its own. Pointing it at
 * /business-email/ sends any remaining equity and any human following an old
 * link to the page that replaced it.
 *
 * The redirect uses the azw_retired_redirects option, read by the
 * azw-retired-redirects mu-plugin already installed.
 */

$apply = (bool) array_intersect(array('apply', '--apply'), (array) $args);

const POST_ID = 2292;
const TARGET = '/business-email/';

$post = get_post(POST_ID);
if (!$post) {
    WP_CLI::error('post ' . POST_ID . ' not found (already deleted?)');
}

WP_CLI::line(sprintf('#%d %s', $post->ID, $post->post_name));
WP_CLI::line('  status now : ' . $post->post_status);
WP_CLI::line('  content    : ' . strlen($post->post_content) . ' bytes');
WP_CLI::line('  action     : trash, and 301 /' . $post->post_name . '/ -> ' . TARGET);

// Confirm the destination actually exists before pointing anything at it.
$target = get_page_by_path(trim(TARGET, '/'), OBJECT, 'page');
if (!$target || 'publish' !== $target->post_status) {
    WP_CLI::error('refusing to redirect: ' . TARGET . ' is not a published page');
}
WP_CLI::line('  target ok  : ' . TARGET . ' is published (ID ' . $target->ID . ')');

$map = get_option('azw_retired_redirects', array());
WP_CLI::line('  existing redirect entries: ' . count($map));

if (!$apply) {
    WP_CLI::line('');
    WP_CLI::line("DRY RUN - nothing written. Re-run with 'apply'.");
    return;
}

$slug = $post->post_name;
$map[$slug] = TARGET;
update_option('azw_retired_redirects', $map, false);
WP_CLI::line('  redirect registered for /' . $slug . '/');

$trashed = wp_trash_post(POST_ID);
if (!$trashed) {
    WP_CLI::error('wp_trash_post failed');
}

wp_cache_flush();
if (class_exists('\RankMath\Sitemap\Cache')) {
    \RankMath\Sitemap\Cache::invalidate_storage();
    WP_CLI::line('  Rank Math sitemap cache invalidated');
}

WP_CLI::success('business-email-hosting trashed and redirected to ' . TARGET);
