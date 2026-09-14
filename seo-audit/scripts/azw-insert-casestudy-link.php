<?php
/**
 * Insert a case-studies cross-link into two live pages WITHOUT republishing
 * them from the local content files.
 *
 *     wp --path=/html eval-file azw-insert-casestudy-link.php          # DRY RUN
 *     wp --path=/html eval-file azw-insert-casestudy-link.php apply    # write
 *
 * Why not azw-publish-content.php: both of these pages have diverged from
 * seo-audit/content/. /arizona-seo-services/ has been rewritten wholesale on
 * the site (different h1, a ten-step process section that exists nowhere
 * locally), and /seo-company-phoenix-az/ has gained two sections and two FAQ
 * entries since. Publishing the local copy over either would delete live copy
 * that someone wrote deliberately, so this edits the live post_content in place
 * and touches nothing else.
 *
 * The original post_content is copied to a postmeta key before the first write,
 * so the edit is reversible. Re-running is a no-op once the link is present.
 */

$apply = (bool) array_intersect( array( 'apply', '--apply' ), (array) $args );

const BACKUP_META = '_azw_content_backup_casestudy_link';

$targets = array(
	'seo-company-phoenix-az' => array(
		// Anchor: insert immediately BEFORE this heading, matched loosely so a
		// class attribute or changed whitespace does not break the match.
		'anchor' => '#<h2[^>]*>\s*When we are the wrong choice\s*</h2>#i',
		'html'   => "\n<h2>What we can show you</h2>\n\n"
			. "<p>We are not going to put a fabricated Phoenix result on this page. Our published work includes a managed IT provider in Ireland with measured Search Console results and an Arizona window company whose ground-up website is live while its SEO phase is only beginning. Both are documented on the <a href=\"/case-studies/\">case studies page</a>, and unfinished SEO work is labeled as unfinished.</p>\n\n"
			. "<p>What it demonstrates is method rather than local proof: finding that a site was competing against itself across duplicate location pages, cutting them down, and moving the equity rather than discarding it. That diagnosis is the same one we would run here. Judge it on whether the reasoning matches your situation, not on whether the client shares your zip code.</p>\n\n",
	),
	'arizona-seo-services'   => array(
		'anchor' => '#<h2[^>]*>\s*Ready for a More Methodical SEO Strategy\?\s*</h2>#i',
		// Deliberately written to match THIS page's voice, which is more formal
		// than the Gilbert and Phoenix pages and was rewritten on the site.
		'html'   => "\n<h2>Results You Can Verify</h2>\n\n"
			. "<p>Published client work, with the measurements attached, is on our <a href=\"/case-studies/\">case studies page</a>. We only put an engagement there when the client has agreed to be named and there is a measured before-and-after to show — which is why the list is short, and why every figure on it can be checked.</p>\n\n"
			. "<p>The current entry covers a location-page consolidation for a managed IT provider: clicks to the affected pages rose 32 percent and two of the four pages gained more than ten positions. The write-up also records what did not go to plan, because that is the part that tells you how an agency reports when the numbers are mixed.</p>\n\n",
	),
);

$link_marker = 'href="/case-studies/"';

foreach ( $targets as $slug => $spec ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	if ( ! $page ) {
		WP_CLI::warning( "{$slug}: not found" );
		continue;
	}

	$content = $page->post_content;

	if ( false !== strpos( $content, $link_marker ) ) {
		WP_CLI::line( sprintf( '%-26s already links to /case-studies/ — skipped', $slug ) );
		continue;
	}

	if ( ! preg_match( $spec['anchor'], $content, $m ) ) {
		WP_CLI::warning( "{$slug}: anchor heading not found — NOT edited. The live page "
			. 'has changed; re-read it and update the anchor rather than guessing.' );
		continue;
	}

	$updated = preg_replace( $spec['anchor'], $spec['html'] . $m[0], $content, 1 );

	WP_CLI::line( sprintf( '%-26s ID %-6d %d -> %d bytes, inserting before: %s',
		$slug, $page->ID, strlen( $content ), strlen( $updated ), trim( wp_strip_all_tags( $m[0] ) ) ) );

	if ( ! $apply ) {
		continue;
	}

	if ( ! get_post_meta( $page->ID, BACKUP_META, true ) ) {
		update_post_meta( $page->ID, BACKUP_META, $content );
	}

	kses_remove_filters();
	$res = wp_update_post( array( 'ID' => $page->ID, 'post_content' => $updated ), true );
	kses_init_filters();

	if ( is_wp_error( $res ) ) {
		WP_CLI::warning( "{$slug}: " . $res->get_error_message() );
		continue;
	}
	WP_CLI::success( "{$slug} updated (original saved to postmeta " . BACKUP_META . ')' );
}

if ( $apply ) {
	wp_cache_flush();
	WP_CLI::line( '' );
	WP_CLI::line( 'Purge Cloudflare before checking the live URLs.' );
} else {
	WP_CLI::line( '' );
	WP_CLI::line( "DRY RUN — nothing written. Re-run with 'apply'." );
}
