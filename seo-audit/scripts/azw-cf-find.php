<?php
/**
 * Find where the Cloudflare plugin stores its API credentials / zone id,
 * so the edge cache for a stale page can be purged.
 *
 *     wp --path=/html eval-file azw-cf-find.php
 *
 * Read-only.
 */
global $wpdb;

$rows = $wpdb->get_results(
	"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%cloudflare%' OR option_name LIKE '%cf\\_%' ESCAPE '\\\\'"
);

foreach ( $rows as $row ) {
	WP_CLI::line( $row->option_name );
}

WP_CLI::line( '-- constants --' );
foreach ( array( 'CLOUDFLARE_API_KEY', 'CLOUDFLARE_EMAIL', 'CLOUDFLARE_API_TOKEN', 'CLOUDFLARE_ZONE_ID' ) as $const ) {
	WP_CLI::line( $const . ': ' . ( defined( $const ) ? 'defined' : 'not defined' ) );
}
