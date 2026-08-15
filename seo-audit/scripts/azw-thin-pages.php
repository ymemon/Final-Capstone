<?php
/**
 * Deal with the thin pages the content scan turned up.
 *
 *     wp --path=/html eval-file azw-thin-pages.php          # DRY RUN
 *     wp --path=/html eval-file azw-thin-pages.php apply    # write
 *
 * Two groups, treated differently on purpose.
 *
 * RETIRE — superseded by a page that covers the same intent properly. These
 * have nothing worth keeping and a better page already exists, so they are set
 * to draft and 301'd. Their URLs are not coming back.
 *
 * NOINDEX — the web-design city cluster. Seven near-identical 44-word pages
 * with the city swapped is the doorway pattern Google filters, and it can drag
 * quality signals sitewide. But these URLs are worth keeping: they are the
 * right slugs for real city pages later. So they come out of the index and the
 * sitemap while staying reachable. Writing genuine content for the two or three
 * cities that matter, then removing the noindex, is the follow-up.
 *
 * Redirects here are WordPress-side (template_redirect) rather than .htaccess,
 * so they live with the posts they belong to and survive a host migration.
 * The .htaccess map in ../redirects/ stays as it is.
 */

$apply = (bool) array_intersect( array( 'apply', '--apply' ), (array) $args );

// slug => where it should go instead
$retire = array(
	'phoenix-seo-services' => '/seo-company-phoenix-az/',
	'local-seo-phoenix'    => '/seo-company-phoenix-az/',
);

$noindex = array(
	// web-design-gilbert-az dropped from this list: it now carries 956 words of
	// real content (published after this list was written), not the 44-word
	// city-swap stub the rest of this batch is.
	'web-design-phoenix-az',
	'web-design-mesa-az',
	'web-design-chandler-az',
	'web-design-scottsdale-az',
	'web-design-tempe-az',
	'web-design-queen-creek-az',
	'phoenix-web-development',
	'case-studies',
);

if ( ! $apply ) {
	WP_CLI::line( "DRY RUN — nothing will be written. Re-run with 'apply'." );
}
WP_CLI::line( '' );

$map = get_option( 'azw_retired_redirects', array() );

WP_CLI::line( '--- retire + redirect ---' );
foreach ( $retire as $slug => $target ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	if ( ! $page ) {
		WP_CLI::warning( "{$slug}: not found" );
		continue;
	}
	WP_CLI::line( sprintf( '  %-24s ID %-6d draft + 301 -> %s', $slug, $page->ID, $target ) );
	if ( ! $apply ) {
		continue;
	}
	wp_update_post( array( 'ID' => $page->ID, 'post_status' => 'draft' ) );
	$map[ $slug ] = $target;
}

WP_CLI::line( '' );
WP_CLI::line( '--- noindex (URL kept, content still reachable) ---' );
foreach ( $noindex as $slug ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	if ( ! $page ) {
		WP_CLI::warning( "{$slug}: not found" );
		continue;
	}
	$words = str_word_count( wp_strip_all_tags( $page->post_content ) );
	WP_CLI::line( sprintf( '  %-26s ID %-6d %3d words -> noindex,follow', $slug, $page->ID, $words ) );
	if ( ! $apply ) {
		continue;
	}
	// noindex keeps it out of results; follow still passes link equity onward,
	// which nofollow would strand.
	update_post_meta( $page->ID, 'rank_math_robots', array( 'noindex', 'follow' ) );
}

if ( ! $apply ) {
	WP_CLI::line( '' );
	WP_CLI::line( "Dry run complete. Re-run with 'apply' to write." );
	return;
}

update_option( 'azw_retired_redirects', $map, false );

// Drop the redirect handler into an mu-plugin so it loads before the theme and
// cannot be switched off by accident from the plugins screen.
$mu  = WPMU_PLUGIN_DIR;
if ( ! is_dir( $mu ) ) {
	wp_mkdir_p( $mu );
}
$code = <<<'PHP'
<?php
/**
 * Plugin Name: AZWebCorp retired-page redirects
 * Description: 301s for pages retired during the 2026 content consolidation.
 *
 * The map is in the azw_retired_redirects option, written by azw-thin-pages.php.
 * Drafted pages would otherwise 404 for anyone holding an old link.
 */
add_action( 'template_redirect', function () {
	$map = get_option( 'azw_retired_redirects', array() );
	if ( ! $map ) {
		return;
	}
	$path = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) ?? '', '/' );
	if ( isset( $map[ $path ] ) ) {
		wp_redirect( home_url( $map[ $path ] ), 301 );
		exit;
	}
} );
PHP;
file_put_contents( $mu . '/azw-retired-redirects.php', $code );

wp_cache_flush();
if ( class_exists( '\RankMath\Sitemap\Cache' ) ) {
	\RankMath\Sitemap\Cache::invalidate_storage();
}

WP_CLI::line( '' );
WP_CLI::success( sprintf( '%d retired, %d noindexed.', count( $retire ), count( $noindex ) ) );
WP_CLI::line( 'mu-plugin written: ' . $mu . '/azw-retired-redirects.php' );
WP_CLI::line( 'Purge Cloudflare before checking the live URLs.' );
