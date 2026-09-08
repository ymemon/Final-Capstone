<?php
/**
 * Verify the case-studies rollout on the live site: the page itself renders and
 * is indexable, and the three service pages link to it.
 *
 *     wp --path=/html eval-file azw-verify-casestudies.php
 *
 * Read-only. Cache-busted so this reads origin, not the Cloudflare edge copy.
 */
$checks = array(
	'https://azwebcorp.com/case-studies/',
	'https://azwebcorp.com/seo-services-gilbert-az/',
	'https://azwebcorp.com/seo-company-phoenix-az/',
	'https://azwebcorp.com/arizona-seo-services/',
);

foreach ( $checks as $url ) {
	$r = wp_remote_get( $url . '?azwcbust=cs1', array( 'timeout' => 30 ) );
	if ( is_wp_error( $r ) ) {
		WP_CLI::line( $url . '  ERROR ' . $r->get_error_message() );
		continue;
	}
	$body = (string) wp_remote_retrieve_body( $r );
	preg_match( '~<meta name="robots"[^>]*content="([^"]*)"~i', $body, $rob );

	WP_CLI::line( sprintf(
		'%-50s %s  robots=%-24s links-to-case-studies=%s  ld+json=%d',
		str_replace( 'https://azwebcorp.com', '', $url ),
		wp_remote_retrieve_response_code( $r ),
		$rob[1] ?? '(none)',
		substr_count( $body, 'href="/case-studies/"' ) || substr_count( $body, 'azwebcorp.com/case-studies/' ) ? 'yes' : 'no',
		substr_count( $body, 'application/ld+json' )
	) );
}
