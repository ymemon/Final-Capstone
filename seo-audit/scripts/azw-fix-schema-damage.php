<?php
/**
 * Repair the damage done by editing schema-carrying pages in the WordPress
 * editor.
 *
 *     wp --path=/html eval-file azw-fix-schema-damage.php          # DRY RUN
 *     wp --path=/html eval-file azw-fix-schema-damage.php apply    # write
 *
 * WHAT HAPPENED
 * The editor strips <script> tags but keeps their contents, so three JSON-LD
 * blocks on /seo-company-phoenix-az/ survived as bare text at the top of
 * post_content and are rendered to visitors as a wall of JSON, with the quotes
 * curled by wptexturize. The page also lost the schema itself.
 *
 * WHAT THIS DOES
 * 1. seo-company-phoenix-az: removes the leaked JSON prefix and restores
 *    BreadcrumbList + ProfessionalService as real <script> blocks. It does NOT
 *    restore FAQPage: Rank Math already emits one for this page from the
 *    visible Q&A, and a second would be duplicate schema.
 * 2. arizona-seo-services: removes a cross-link section added to post_content
 *    that never renders. That page is built in Elementor, so post_content is
 *    not its display source - the section has to be added in Elementor to be
 *    seen, and leaving invisible copy in the database only misleads the next
 *    person to read it.
 *
 * SAFETY
 * The prefix is only removed if every byte of it parses as JSON. If anything
 * there is real copy, the page is skipped untouched. Original post_content is
 * copied to postmeta before the first write.
 */

$apply = (bool) array_intersect( array( 'apply', '--apply' ), (array) $args );

const BACKUP_META = '_azw_content_backup_schema_repair';

/** Restore only what Rank Math is not already emitting on this page. */
function azw_phoenix_schema() {
	$breadcrumb = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => array(
			array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://azwebcorp.com/' ),
			array( '@type' => 'ListItem', 'position' => 2, 'name' => 'Arizona SEO Services', 'item' => 'https://azwebcorp.com/arizona-seo-services/' ),
			array( '@type' => 'ListItem', 'position' => 3, 'name' => 'SEO Company Serving Phoenix', 'item' => 'https://azwebcorp.com/seo-company-phoenix-az/' ),
		),
	);
	$service = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'ProfessionalService',
		'name'        => 'AZWebCorp',
		'description' => "Search engine optimization for Phoenix-area businesses, delivered from AZWebCorp's office in Gilbert, Arizona.",
		'url'         => 'https://azwebcorp.com/seo-company-phoenix-az/',
		'telephone'   => '+1-480-818-5761',
		'email'       => 'info@azwebcorp.com',
		'address'     => array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => '4690 E Laurel Ave',
			'addressLocality' => 'Gilbert',
			'addressRegion'   => 'AZ',
			'postalCode'      => '85234',
			'addressCountry'  => 'US',
		),
		'areaServed'  => array(
			'@type'            => 'City',
			'name'             => 'Phoenix',
			'containedInPlace' => array( '@type' => 'State', 'name' => 'Arizona' ),
		),
	);

	$out = '';
	foreach ( array( $breadcrumb, $service ) as $block ) {
		$out .= '<script type="application/ld+json">' . "\n"
			. wp_json_encode( $block, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			. "\n" . '</script>' . "\n\n";
	}
	return $out;
}

/* ── 1. Phoenix: strip the leaked JSON, restore the two missing blocks ───── */

$page = get_page_by_path( 'seo-company-phoenix-az', OBJECT, 'page' );
if ( ! $page ) {
	WP_CLI::warning( 'seo-company-phoenix-az: not found' );
} else {
	$content = $page->post_content;
	$first   = strpos( $content, '<' );

	if ( false === $first || 0 === $first ) {
		WP_CLI::line( 'seo-company-phoenix-az: no leaked prefix — nothing to strip' );
	} else {
		$prefix = trim( substr( $content, 0, $first ) );

		// Only strip it if the whole prefix really is JSON. Splitting on a blank
		// line between the concatenated objects is enough to check each one.
		$looks_like_json = '' !== $prefix;
		foreach ( preg_split( '/\n\s*\n/', $prefix ) as $chunk ) {
			$chunk = trim( $chunk );
			if ( '' === $chunk ) {
				continue;
			}
			if ( null === json_decode( $chunk, true ) ) {
				$looks_like_json = false;
				break;
			}
		}

		if ( ! $looks_like_json ) {
			WP_CLI::warning( 'seo-company-phoenix-az: prefix is not pure JSON — SKIPPED, inspect by hand' );
		} else {
			$rebuilt = azw_phoenix_schema() . substr( $content, $first );

			WP_CLI::line( sprintf(
				'seo-company-phoenix-az  strip %d bytes of leaked JSON, add 2 schema blocks: %d -> %d bytes',
				$first, strlen( $content ), strlen( $rebuilt )
			) );

			if ( $apply ) {
				if ( ! get_post_meta( $page->ID, BACKUP_META, true ) ) {
					update_post_meta( $page->ID, BACKUP_META, $content );
				}
				kses_remove_filters();
				$r = wp_update_post( array( 'ID' => $page->ID, 'post_content' => $rebuilt ), true );
				kses_init_filters();
				if ( is_wp_error( $r ) ) {
					WP_CLI::warning( 'seo-company-phoenix-az: ' . $r->get_error_message() );
				} else {
					WP_CLI::success( 'seo-company-phoenix-az repaired' );
				}
			}
		}
	}
}

/* ── 2. Arizona: remove the invisible cross-link section ─────────────────── */

$page = get_page_by_path( 'arizona-seo-services', OBJECT, 'page' );
if ( ! $page ) {
	WP_CLI::warning( 'arizona-seo-services: not found' );
} else {
	$content = $page->post_content;
	$pattern = '#\n?<h2>Results You Can Verify</h2>.*?(?=<h2[^>]*>\s*Ready for a More Methodical SEO Strategy\?)#s';

	if ( ! preg_match( $pattern, $content ) ) {
		WP_CLI::line( 'arizona-seo-services: no invisible cross-link section found — nothing to remove' );
	} else {
		$rebuilt = preg_replace( $pattern, '', $content, 1 );
		WP_CLI::line( sprintf(
			'arizona-seo-services    remove non-rendering cross-link section: %d -> %d bytes',
			strlen( $content ), strlen( $rebuilt )
		) );

		if ( $apply ) {
			if ( ! get_post_meta( $page->ID, BACKUP_META, true ) ) {
				update_post_meta( $page->ID, BACKUP_META, $content );
			}
			kses_remove_filters();
			$r = wp_update_post( array( 'ID' => $page->ID, 'post_content' => $rebuilt ), true );
			kses_init_filters();
			if ( is_wp_error( $r ) ) {
				WP_CLI::warning( 'arizona-seo-services: ' . $r->get_error_message() );
			} else {
				WP_CLI::success( 'arizona-seo-services cleaned' );
			}
		}
	}
}

if ( $apply ) {
	wp_cache_flush();
	WP_CLI::line( '' );
	WP_CLI::line( 'Done. Flush the CDN and re-check the live pages.' );
} else {
	WP_CLI::line( '' );
	WP_CLI::line( "DRY RUN — nothing written. Re-run with 'apply'." );
}
