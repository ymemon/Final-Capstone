<?php
/**
 * Publish the service pages in seo-audit/content/ to WordPress.
 *
 * Run through WP-CLI so WordPress is fully loaded:
 *
 *     wp eval-file azw-publish-content.php            # DRY RUN
 *     wp eval-file azw-publish-content.php --apply    # write
 *
 * Matching is by slug, so this is idempotent: a page that already exists is
 * updated in place and keeps its ID, its permalink and whatever link equity it
 * has accumulated. That matters most for /arizona-seo-services/, which is a
 * rewrite of a page that already ranks — creating a new post for it would throw
 * that away.
 *
 * The <h1> is moved out of the content and into post_title, because the theme
 * renders post_title as the page's <h1>. Leaving it in the body would give
 * every page two <h1> tags.
 */

$apply = in_array( '--apply', (array) $args, true );

$dir = __DIR__ . '/content';
if ( ! is_dir( $dir ) ) {
	WP_CLI::error( "Content directory not found: {$dir}" );
}

$files = glob( $dir . '/*.html' );
if ( ! $files ) {
	WP_CLI::error( "No .html files in {$dir}" );
}

if ( ! $apply ) {
	WP_CLI::line( 'DRY RUN — nothing will be written. Re-run with --apply.' );
}
WP_CLI::line( '' );

/** Pull the first capture of a pattern, or null. */
$grab = static function ( $pattern, $subject ) {
	return preg_match( $pattern, $subject, $m ) ? trim( $m[1] ) : null;
};

// Script tags in post_content are stripped by kses for users without the
// unfiltered_html capability. The JSON-LD is the point of these pages, so the
// filters come off for the duration of this run.
kses_remove_filters();

$published = 0;
$skipped   = 0;

foreach ( $files as $file ) {
	$slug = basename( $file, '.html' );
	$src  = file_get_contents( $file );

	$title     = $grab( '#<title>(.*?)</title>#s', $src );
	$desc      = $grab( '#<meta name="description" content="(.*?)">#s', $src );
	$canonical = $grab( '#<link rel="canonical" href="(.*?)">#s', $src );

	// JSON-LD blocks, kept verbatim and in source order.
	preg_match_all( '#<script type="application/ld\+json">.*?</script>#s', $src, $schema );
	$schema = implode( "\n", $schema[0] );

	$body = $grab( '#<body>(.*?)</body>#s', $src );
	if ( null === $body ) {
		WP_CLI::warning( "{$slug}: no <body> block — skipped" );
		$skipped++;
		continue;
	}

	// Move the <h1> into the post title; the theme will render it.
	$h1 = $grab( '#<h1>(.*?)</h1>#s', $body );
	if ( null === $h1 ) {
		WP_CLI::warning( "{$slug}: no <h1> — skipped" );
		$skipped++;
		continue;
	}
	$body = preg_replace( '#<h1>.*?</h1>#s', '', $body, 1 );

	$content = trim( $schema . "\n\n" . trim( $body ) );

	$existing = get_page_by_path( $slug, OBJECT, 'page' );
	$action   = $existing ? "UPDATE (ID {$existing->ID})" : 'CREATE';

	WP_CLI::line( sprintf( '%-30s %s', $slug, $action ) );
	WP_CLI::line( sprintf( '  title      %s', $h1 ) );
	WP_CLI::line( sprintf( '  seo title  %s', $title ) );
	WP_CLI::line( sprintf( '  canonical  %s', $canonical ) );
	WP_CLI::line( sprintf( '  content    %d bytes, %d JSON-LD block(s)',
		strlen( $content ), substr_count( $schema, '<script' ) ) );

	if ( ! $apply ) {
		WP_CLI::line( '' );
		continue;
	}

	$postarr = array(
		'post_title'   => $h1,
		'post_name'    => $slug,
		'post_content' => $content,
		'post_status'  => 'publish',
		'post_type'    => 'page',
	);

	if ( $existing ) {
		$postarr['ID'] = $existing->ID;
		$id            = wp_update_post( $postarr, true );
	} else {
		$id = wp_insert_post( $postarr, true );
	}

	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( "{$slug}: " . $id->get_error_message() );
		$skipped++;
		WP_CLI::line( '' );
		continue;
	}

	// Rank Math reads these; without them it falls back to the post title and
	// an auto-generated excerpt, discarding the hand-written SERP copy.
	if ( $title ) {
		update_post_meta( $id, 'rank_math_title', $title );
	}
	if ( $desc ) {
		update_post_meta( $id, 'rank_math_description', $desc );
	}
	if ( $canonical ) {
		update_post_meta( $id, 'rank_math_canonical_url', $canonical );
	}
	update_post_meta( $id, 'rank_math_robots', array( 'index', 'follow' ) );

	WP_CLI::success( "{$slug} → " . get_permalink( $id ) );
	WP_CLI::line( '' );
	$published++;
}

kses_init_filters();

if ( $apply ) {
	WP_CLI::line( '--- flushing ---' );
	wp_cache_flush();
	flush_rewrite_rules( false );
	if ( function_exists( 'rank_math' ) && class_exists( '\RankMath\Sitemap\Cache' ) ) {
		\RankMath\Sitemap\Cache::invalidate_storage();
		WP_CLI::line( 'Rank Math sitemap cache invalidated' );
	}
	WP_CLI::line( 'object cache + rewrite rules flushed' );
	WP_CLI::line( '' );
	WP_CLI::success( "{$published} page(s) published, {$skipped} skipped." );
	WP_CLI::line( 'Cloudflare still caches at the edge — purge it before checking the live URLs.' );
} else {
	WP_CLI::line( 'Dry run complete. Re-run with --apply to write.' );
}
