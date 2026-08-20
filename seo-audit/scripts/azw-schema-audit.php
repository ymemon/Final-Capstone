<?php
/**
 * Report the Product JSON-LD on given URLs, and what images are available.
 *
 *     wp --path=/html eval-file azw-schema-audit.php \
 *        https://azwebcorp.com/web-hosting/ https://azwebcorp.com/web-hosting-plus/
 *
 * Read-only.
 *
 * Search Console reports "Missing field image" on eleven Product items. Product
 * markup without an image is ineligible for rich results, so the offers and
 * prices are being parsed and then discarded.
 *
 * Two things need establishing before it can be fixed, and both are facts
 * rather than guesses. Which generator is emitting the markup — the pages in
 * this repo carry hand-written Product schema, and the Reseller Store plugin
 * emits its own — because fixing one does nothing if the other is responsible.
 * And which image URL to use, since schema.org image must point at something
 * that actually exists and returns an image.
 */

$urls = $args;
if ( ! $urls ) {
	$urls = array( home_url( '/web-hosting/' ) );
}

/** Every JSON-LD block on a page, decoded. */
function azw_sa_blocks( $html ) {
	preg_match_all( '~<script[^>]+application/ld\+json[^>]*>(.*?)</script>~is', $html, $m );
	$out = array();
	foreach ( $m[1] as $raw ) {
		$data = json_decode( trim( $raw ), true );
		if ( is_array( $data ) ) {
			$out[] = $data;
		}
	}
	return $out;
}

/** Product nodes at any depth, including inside an @graph. */
function azw_sa_products( $node, &$found ) {
	if ( ! is_array( $node ) ) {
		return;
	}
	if ( isset( $node['@type'] ) ) {
		$types = (array) $node['@type'];
		if ( in_array( 'Product', $types, true ) ) {
			$found[] = $node;
		}
	}
	foreach ( $node as $child ) {
		if ( is_array( $child ) ) {
			azw_sa_products( $child, $found );
		}
	}
}

foreach ( $urls as $url ) {
	WP_CLI::line( '' );
	WP_CLI::line( str_repeat( '=', 70 ) );
	WP_CLI::line( $url );
	WP_CLI::line( str_repeat( '=', 70 ) );

	$r = wp_remote_get( $url, array( 'timeout' => 30, 'user-agent' => 'AZWebCorpSchemaAudit/1.0' ) );
	if ( is_wp_error( $r ) ) {
		WP_CLI::warning( $r->get_error_message() );
		continue;
	}
	$html = (string) wp_remote_retrieve_body( $r );

	$products = array();
	foreach ( azw_sa_blocks( $html ) as $block ) {
		azw_sa_products( $block, $products );
	}

	if ( ! $products ) {
		WP_CLI::line( 'No Product schema found in JSON-LD.' );
	}

	foreach ( $products as $i => $p ) {
		$offers = isset( $p['offers'] ) ? (array) $p['offers'] : array();
		$names  = array();
		foreach ( $offers as $o ) {
			if ( is_array( $o ) && isset( $o['name'] ) ) {
				$names[] = $o['name'];
			}
		}
		WP_CLI::line( sprintf( 'Product %d', $i + 1 ) );
		WP_CLI::line( '  name:   ' . ( $p['name'] ?? '(none)' ) );
		WP_CLI::line( '  image:  ' . ( isset( $p['image'] ) ? ( is_array( $p['image'] ) ? implode( ', ', $p['image'] ) : $p['image'] ) : 'MISSING <-- this is the error' ) );
		WP_CLI::line( '  brand:  ' . ( isset( $p['brand']['name'] ) ? $p['brand']['name'] : '(none)' ) );
		WP_CLI::line( '  offers: ' . ( $names ? implode( ' / ', $names ) : count( $offers ) . ' unnamed' ) );
	}

	// Microdata and RDFa are parsed by Google too, and a plugin emitting either
	// would not show up in the JSON-LD above.
	$micro = substr_count( $html, 'itemtype="http://schema.org/Product' )
		+ substr_count( $html, 'itemtype="https://schema.org/Product' );
	if ( $micro ) {
		WP_CLI::line( sprintf( '  NOTE: %d Product microdata block(s) also present — a plugin, not JSON-LD.', $micro ) );
	}
}

WP_CLI::line( '' );
WP_CLI::line( str_repeat( '=', 70 ) );
WP_CLI::line( 'Images available for the fix' );
WP_CLI::line( str_repeat( '=', 70 ) );

$logo_id = (int) get_theme_mod( 'custom_logo' );
if ( $logo_id ) {
	WP_CLI::line( 'Site logo:      ' . wp_get_attachment_url( $logo_id ) );
} else {
	WP_CLI::line( 'Site logo:      not set in the theme' );
}

$icon = get_site_icon_url( 512 );
WP_CLI::line( 'Site icon:      ' . ( $icon ? $icon : 'not set' ) );

// Google wants an image that represents the product. Recent uploads are the
// most likely place a suitable one already exists.
$recent = get_posts( array(
	'post_type'      => 'attachment',
	'post_mime_type' => 'image',
	'posts_per_page' => 8,
	'orderby'        => 'date',
	'order'          => 'DESC',
) );
if ( $recent ) {
	WP_CLI::line( '' );
	WP_CLI::line( 'Most recent image uploads:' );
	foreach ( $recent as $a ) {
		$meta = wp_get_attachment_metadata( $a->ID );
		WP_CLI::line( sprintf(
			'  %-5d %5sx%-5s %s',
			$a->ID,
			$meta['width'] ?? '?',
			$meta['height'] ?? '?',
			wp_get_attachment_url( $a->ID )
		) );
	}
}

WP_CLI::line( '' );
WP_CLI::line( 'Google requires image to be at least 1200px wide for best results,' );
WP_CLI::line( 'and it must be crawlable — not blocked by robots.txt.' );
