<?php
/**
 * Plugin Name: AZWebCorp Product Schema Image
 * Description: Adds the required image field to Product JSON-LD that lacks one, using an image that actually exists on this site.
 * Version: 1.0.0
 * Author: AZWebCorp
 *
 * ---------------------------------------------------------------------------
 * Search Console reports "Missing field image" on eleven Product items across
 * /web-hosting/, /web-hosting-plus/ and /wordpress-hosting/. Google parses the
 * offers and prices, finds no image, and discards the lot — the markup is doing
 * nothing.
 *
 * This works on the rendered page rather than on whichever generator produced
 * the markup. Two different things emit Product schema here: hand-written
 * JSON-LD in the seo-audit landing pages, and the Reseller Store plugin, whose
 * output cannot be edited directly. Filtering the finished HTML fixes both, and
 * keeps working if a third joins them.
 *
 * IT NEVER INVENTS A URL. The image is resolved from things that exist — the
 * page's featured image, then the site logo, then the site icon — and if none
 * of them do, the markup is left alone. A fabricated image URL produces exactly
 * the error we are fixing, only harder to find next time.
 *
 * Worth a decision separately: those hosting products are GoDaddy's, resold
 * through the storefront. Claiming Product rich results for inventory you do
 * not hold is defensible for a reseller but not obviously correct. Setting the
 * azwc_product_schema_enabled filter to false suppresses the markup question
 * entirely by leaving it invalid, which is the honest alternative to fixing it.
 * ---------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AZWC_PSCHEMA_VERSION', '1.0.0' );

/**
 * The image to attach, or '' if nothing suitable exists.
 *
 * Order matters: the most specific real image wins. Everything here is checked
 * for existence rather than assumed, because an image URL that 404s fails
 * validation the same way a missing one does.
 */
function azwc_pschema_image() {
	$cached = wp_cache_get( 'azwc_pschema_image', 'azwc' );
	if ( false !== $cached ) {
		return $cached;
	}

	$url = '';

	// An explicit override, for when a proper product image is uploaded later.
	$option = trim( (string) get_option( 'azwc_pschema_image_url', '' ) );
	if ( $option ) {
		$url = $option;
	}

	// The page's own featured image is the most specific thing available.
	if ( ! $url && is_singular() ) {
		$thumb = get_post_thumbnail_id( get_queried_object_id() );
		if ( $thumb ) {
			$src = wp_get_attachment_image_src( $thumb, 'full' );
			if ( ! empty( $src[0] ) ) {
				$url = $src[0];
			}
		}
	}

	// The site logo represents the brand selling the product. Honest, if generic.
	if ( ! $url ) {
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$src = wp_get_attachment_image_src( $logo_id, 'full' );
			if ( ! empty( $src[0] ) ) {
				$url = $src[0];
			}
		}
	}

	if ( ! $url ) {
		$icon = get_site_icon_url( 512 );
		if ( $icon ) {
			$url = $icon;
		}
	}

	/**
	 * Filter the image used for Product schema.
	 *
	 * Return '' to leave the markup untouched rather than attaching something
	 * unrepresentative.
	 */
	$url = (string) apply_filters( 'azwc_product_schema_image', $url );

	wp_cache_set( 'azwc_pschema_image', $url, 'azwc', HOUR_IN_SECONDS );
	return $url;
}

/** Add image to every Product node that lacks one. Returns true if changed. */
function azwc_pschema_walk( &$node, $image ) {
	if ( ! is_array( $node ) ) {
		return false;
	}

	$changed = false;

	if ( isset( $node['@type'] ) ) {
		$types = (array) $node['@type'];
		if ( in_array( 'Product', $types, true ) && empty( $node['image'] ) ) {
			$node['image'] = $image;
			$changed       = true;
		}
	}

	foreach ( $node as $key => &$child ) {
		if ( is_array( $child ) && azwc_pschema_walk( $child, $image ) ) {
			$changed = true;
		}
	}
	unset( $child );

	return $changed;
}

/** Rewrite the JSON-LD blocks in a finished document. */
function azwc_pschema_filter_html( $html ) {
	// Cheap guard first: this runs on every page, and most have no Product
	// markup at all. Decoding JSON on all of them to discover that would be
	// work done for nothing.
	if ( false === stripos( $html, 'Product' ) || false === stripos( $html, 'ld+json' ) ) {
		return $html;
	}

	$image = azwc_pschema_image();
	if ( ! $image ) {
		return $html;
	}

	return preg_replace_callback(
		'~(<script[^>]+application/ld\+json[^>]*>)(.*?)(</script>)~is',
		function ( $m ) use ( $image ) {
			$data = json_decode( trim( $m[2] ), true );
			if ( ! is_array( $data ) ) {
				return $m[0];
			}
			if ( ! azwc_pschema_walk( $data, $image ) ) {
				return $m[0];
			}
			$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			return false === $json ? $m[0] : $m[1] . $json . $m[3];
		},
		$html
	);
}

/**
 * Buffer the page so the JSON-LD can be rewritten after every generator has
 * had its say.
 *
 * Started late on template_redirect and closed on shutdown, which is the only
 * point at which plugins printing schema in the footer have finished.
 */
function azwc_pschema_start_buffer() {
	if ( is_admin() || is_feed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	if ( ! apply_filters( 'azwc_product_schema_enabled', true ) ) {
		return;
	}
	ob_start( 'azwc_pschema_filter_html' );
}
add_action( 'template_redirect', 'azwc_pschema_start_buffer', 1 );

/**
 * WP-CLI check, so the result can be verified without opening a browser.
 *
 *     wp --path=/html azwc-product-schema https://azwebcorp.com/web-hosting/
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'azwc-product-schema', function ( $args ) {
		$urls = $args ? $args : array( home_url( '/web-hosting/' ) );
		foreach ( $urls as $url ) {
			$r = wp_remote_get( $url, array( 'timeout' => 30 ) );
			if ( is_wp_error( $r ) ) {
				WP_CLI::warning( $url . ': ' . $r->get_error_message() );
				continue;
			}
			$html = (string) wp_remote_retrieve_body( $r );
			preg_match_all( '~<script[^>]+application/ld\+json[^>]*>(.*?)</script>~is', $html, $m );

			$total   = 0;
			$missing = 0;
			foreach ( $m[1] as $raw ) {
				$data = json_decode( trim( $raw ), true );
				if ( ! is_array( $data ) ) {
					continue;
				}
				array_walk_recursive( $data, function () {} );
				$stack = array( $data );
				while ( $stack ) {
					$node = array_pop( $stack );
					if ( ! is_array( $node ) ) {
						continue;
					}
					if ( isset( $node['@type'] ) && in_array( 'Product', (array) $node['@type'], true ) ) {
						$total++;
						if ( empty( $node['image'] ) ) {
							$missing++;
						}
					}
					foreach ( $node as $child ) {
						if ( is_array( $child ) ) {
							$stack[] = $child;
						}
					}
				}
			}

			WP_CLI::line( sprintf(
				'%-52s %d Product node(s), %d without image',
				$url,
				$total,
				$missing
			) );
		}
		WP_CLI::line( '' );
		WP_CLI::line( 'Image in use: ' . ( azwc_pschema_image() ?: '(none found — markup left untouched)' ) );
	} );
}
