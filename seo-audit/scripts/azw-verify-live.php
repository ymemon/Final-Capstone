<?php
/**
 * Confirm the noindex tag and the retired-page redirect actually render on
 * the live site (cache-busted, bypasses edge cache).
 *
 *     wp --path=/html eval-file azw-verify-live.php
 */
$checks = array(
	'https://azwebcorp.com/web-design-phoenix-az/?azwcbust=v1'  => 'noindex',
	'https://azwebcorp.com/web-design-gilbert-az/?azwcbust=v1'  => 'index',
	'https://azwebcorp.com/phoenix-seo-services/?azwcbust=v1'   => 'redirect',
);

foreach ( $checks as $url => $expect ) {
	$r = wp_remote_get( $url, array( 'timeout' => 30, 'redirection' => 0 ) );
	if ( is_wp_error( $r ) ) {
		WP_CLI::line( $url . ' ERROR ' . $r->get_error_message() );
		continue;
	}
	$code = wp_remote_retrieve_response_code( $r );
	if ( in_array( $code, array( 301, 302 ), true ) ) {
		WP_CLI::line( sprintf( '%-60s %s -> %s', $url, $code, wp_remote_retrieve_header( $r, 'location' ) ) );
		continue;
	}
	$body = (string) wp_remote_retrieve_body( $r );
	preg_match( '~<meta name="robots"[^>]*>~i', $body, $m );
	WP_CLI::line( sprintf( '%-60s %s %s', $url, $code, $m[0] ?? '(no robots meta tag found)' ) );
}
