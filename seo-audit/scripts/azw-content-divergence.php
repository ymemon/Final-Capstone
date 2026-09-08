<?php
/**
 * Compare what is live against what a publish would write, before writing it.
 *
 *     wp --path=/html eval-file azw-content-divergence.php slug [slug...]
 *
 * Read-only. azw-publish-content.php overwrites post_content wholesale from the
 * local file, so anything edited in WordPress since the local copy was last
 * synced is lost silently. This prints modified dates and sizes so that is a
 * decision rather than an accident.
 */
$slugs = (array) $args;
if ( ! $slugs ) {
	WP_CLI::error( 'Name at least one slug.' );
}

foreach ( $slugs as $slug ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	if ( ! $page ) {
		WP_CLI::line( sprintf( '%-26s NOT ON SITE (would be created)', $slug ) );
		continue;
	}
	WP_CLI::line( sprintf(
		'%-26s ID %-6d modified %s  %6d bytes  %s',
		$slug,
		$page->ID,
		$page->post_modified,
		strlen( $page->post_content ),
		$page->post_status
	) );
}
