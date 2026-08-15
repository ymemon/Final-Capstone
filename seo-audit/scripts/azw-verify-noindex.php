<?php
/**
 * Confirm the final state of every page touched by the thin-pages
 * remediation, after correcting the web-design-gilbert-az mistake.
 *
 *     wp --path=/html eval-file azw-verify-noindex.php
 */
$slugs = array(
	'web-design-gilbert-az',
	'web-design-phoenix-az',
	'web-design-mesa-az',
	'web-design-chandler-az',
	'web-design-scottsdale-az',
	'web-design-tempe-az',
	'web-design-queen-creek-az',
	'phoenix-web-development',
	'case-studies',
	'phoenix-seo-services',
	'local-seo-phoenix',
);

foreach ( $slugs as $slug ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	if ( ! $page ) {
		WP_CLI::warning( "{$slug}: not found" );
		continue;
	}
	$robots = get_post_meta( $page->ID, 'rank_math_robots', true );
	$robots = is_array( $robots ) ? implode( ',', $robots ) : '(default)';
	WP_CLI::line( sprintf( '%-26s ID %-6d %-10s robots=%s', $slug, $page->ID, $page->post_status, $robots ) );
}
