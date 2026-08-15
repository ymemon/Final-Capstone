<?php
/**
 * Confirm the page still renders normally after disabling Autoptimize's
 * CSS defer/critical-CSS fallback: HTTP 200, and a normal cached
 * stylesheet <link> in place of the removed inline dump.
 *
 *     wp --path=/html eval-file azw-verify-fix.php https://azwebcorp.com/
 *
 * Read-only.
 */

$url = $args[0] ?? home_url( '/' );

$response = wp_remote_get( $url, array(
	'timeout'    => 30,
	'user-agent' => 'AZWebCorpVerifyFix/1.0',
) );

if ( is_wp_error( $response ) ) {
	WP_CLI::error( $response->get_error_message() );
}

WP_CLI::line( $url );
WP_CLI::line( 'HTTP status: ' . wp_remote_retrieve_response_code( $response ) );

$body = (string) wp_remote_retrieve_body( $response );
preg_match_all( '~<link[^>]*stylesheet[^>]*>~i', $body, $links );
WP_CLI::line( 'Stylesheet <link> tags found: ' . count( $links[0] ) );
foreach ( array_slice( $links[0], 0, 5 ) as $tag ) {
	WP_CLI::line( '  ' . $tag );
}
