<?php
$r = wp_remote_get( 'https://azwebcorp.com/best-internal-linking-plugins-wordpress/?azwcbust=v2', array( 'timeout' => 30 ) );
$body = (string) wp_remote_retrieve_body( $r );
preg_match( '~<meta name="robots"[^>]*>~i', $body, $m );
WP_CLI::line( wp_remote_retrieve_response_code( $r ) . ' ' . ( $m[0] ?? '(none found)' ) );
