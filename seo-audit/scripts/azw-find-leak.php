<?php
/**
 * Find whatever URL is publishing raw keyword-research spreadsheet rows
 * (literal text like "wordpress auto internal links,10.00,high,76,approved")
 * that Google has indexed as if they were page content.
 *
 *     wp --path=/html eval-file azw-find-leak.php
 *
 * Read-only.
 */
global $wpdb;

$rows = $wpdb->get_results(
	"SELECT ID, post_type, post_status, post_title FROM {$wpdb->posts}
	 WHERE post_content LIKE '%,approved%' AND post_status != 'trash'"
);
WP_CLI::line( '-- post_content --' );
foreach ( $rows as $r ) {
	WP_CLI::line( sprintf( '#%d %s (%s) %s', $r->ID, $r->post_title, $r->post_type, $r->post_status ) );
}

$meta = $wpdb->get_results(
	"SELECT post_id, meta_key FROM {$wpdb->postmeta} WHERE meta_value LIKE '%,approved%' LIMIT 20"
);
WP_CLI::line( '-- postmeta --' );
foreach ( $meta as $m ) {
	WP_CLI::line( sprintf( '#%d %s', $m->post_id, $m->meta_key ) );
}
