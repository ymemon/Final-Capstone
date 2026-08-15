<?php
/**
 * Find who owns the 1.3 MB inline <style> block on the home page by
 * searching the whole document for plugin signatures, not just the
 * 300 bytes immediately surrounding the tag.
 *
 *     wp --path=/html eval-file azw-css-source.php https://azwebcorp.com/
 *
 * Read-only.
 */

$url = $args[0] ?? home_url( '/' );

$response = wp_remote_get( $url, array(
	'timeout'             => 30,
	'limit_response_size' => 12582912,
	'user-agent'          => 'AZWebCorpCssSource/1.0',
	'headers'             => array( 'Accept-Encoding' => 'identity' ),
) );

if ( is_wp_error( $response ) ) {
	WP_CLI::error( $response->get_error_message() );
}

$html = (string) wp_remote_retrieve_body( $response );

$needles = array(
	'wpacu',
	'asset-cleanup',
	'Asset CleanUp',
	'autoptimize',
	'Autoptimize',
	'Combined CSS',
	'combined by',
	'flying-pages',
	'Flying Pages',
	'flying-scripts',
	'critical css',
	'Critical CSS',
	'wp-asset-clean-up',
);

foreach ( $needles as $needle ) {
	$offset = 0;
	$hits   = 0;
	while ( false !== ( $pos = stripos( $html, $needle, $offset ) ) && $hits < 3 ) {
		$context = substr( $html, max( 0, $pos - 80 ), 200 );
		WP_CLI::line( sprintf( '[%s] @%d: %s', $needle, $pos, trim( preg_replace( '/\s+/', ' ', $context ) ) ) );
		$offset = $pos + strlen( $needle );
		$hits++;
	}
}

// Also print every <style ...> and <link ...stylesheet...> opening tag in
// document order, with byte offset, so we can see what wraps the big block.
WP_CLI::line( '' );
WP_CLI::line( '-- style/link tags in order --' );
preg_match_all( '~<(style|link)\b[^>]*>~i', $html, $m, PREG_OFFSET_CAPTURE );
foreach ( $m[0] as $tag ) {
	list( $text, $pos ) = $tag;
	if ( stripos( $text, 'stylesheet' ) === false && stripos( $text, '<style' ) === false ) {
		continue;
	}
	WP_CLI::line( sprintf( '@%d: %s', $pos, substr( $text, 0, 200 ) ) );
}
