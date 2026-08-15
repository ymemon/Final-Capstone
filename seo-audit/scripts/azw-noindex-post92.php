<?php
/**
 * Noindex post #92 ("7 Best Internal Linking Plugins for WordPress
 * Websites"). Its live content was wiped down to a bare <h1> on
 * 2026-07-26. The only intact prior revision (#956) turned out to be
 * scraped from pagetraffic.com - 7 hotlinked images pulled straight from
 * their uploads folder, plus 4 outbound links to their blog - so it is
 * not being restored. This just stops the current thin page from sitting
 * in the index until someone writes real, original content for it.
 *
 *     wp --path=/html eval-file azw-noindex-post92.php          # DRY RUN
 *     wp --path=/html eval-file azw-noindex-post92.php apply    # write
 */
$apply = (bool) array_intersect( array( 'apply', '--apply' ), (array) $args );

$post = get_post( 92 );
if ( ! $post ) {
	WP_CLI::error( 'post 92 not found' );
}

WP_CLI::line( sprintf( '#%d %s (%s), %d bytes of content', $post->ID, $post->post_title, $post->post_status, strlen( $post->post_content ) ) );
WP_CLI::line( 'Setting rank_math_robots -> noindex, follow' );

if ( ! $apply ) {
	WP_CLI::line( "DRY RUN - nothing written. Re-run with 'apply'." );
	return;
}

update_post_meta( 92, 'rank_math_robots', array( 'noindex', 'follow' ) );
wp_cache_flush();
if ( class_exists( '\RankMath\Sitemap\Cache' ) ) {
	\RankMath\Sitemap\Cache::invalidate_storage();
}

WP_CLI::success( 'post 92 noindexed. robots meta: ' . print_r( get_post_meta( 92, 'rank_math_robots', true ), true ) );
