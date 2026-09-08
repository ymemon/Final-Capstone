<?php
/**
 * Dump a live page's headings and post_content length, so a local file can be
 * compared against it before a publish overwrites the live copy.
 *
 *     wp --path=/html eval-file azw-dump-live-content.php slug
 *
 * Read-only.
 */
$slug = $args[0] ?? '';
if ( ! $slug ) {
	WP_CLI::error( 'Name a slug.' );
}

$page = get_page_by_path( $slug, OBJECT, 'page' );
if ( ! $page ) {
	WP_CLI::error( "No page with slug {$slug}" );
}

$c = $page->post_content;
WP_CLI::line( 'bytes: ' . strlen( $c ) );
WP_CLI::line( 'JSON-LD blocks: ' . substr_count( $c, '<script type="application/ld+json"' ) );
WP_CLI::line( '--- headings in live content ---' );
if ( preg_match_all( '#<(h[1-3])[^>]*>(.*?)</\1>#is', $c, $m, PREG_SET_ORDER ) ) {
	foreach ( $m as $hit ) {
		WP_CLI::line( sprintf( '  %s  %s', $hit[1], trim( wp_strip_all_tags( $hit[2] ) ) ) );
	}
} else {
	WP_CLI::line( '  (none found)' );
}
