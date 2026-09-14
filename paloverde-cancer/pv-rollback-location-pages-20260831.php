<?php
/** Emergency rollback of the four location pages to the pre-change backup. */

$backup = '/home/client_b9c1bb2d60_875051/pv-michael-corrections-20260831-183700';
$ids    = array( 533, 461, 501, 544 );

foreach ( $ids as $id ) {
	$content_file   = $backup . '/' . $id . '-post_content.html';
	$elementor_file = $backup . '/' . $id . '-elementor.json';
	if ( ! is_readable( $content_file ) || ! is_readable( $elementor_file ) ) {
		WP_CLI::error( "Missing rollback backup for post $id." );
	}
	$content   = file_get_contents( $content_file );
	$elementor = file_get_contents( $elementor_file );
	$result    = wp_update_post( array( 'ID' => $id, 'post_content' => wp_slash( $content ) ), true );
	if ( is_wp_error( $result ) ) {
		WP_CLI::error( $result->get_error_message() );
	}
	update_post_meta( $id, '_elementor_data', wp_slash( $elementor ) );
	clean_post_cache( $id );
	WP_CLI::line( "Restored $id" );
}

if ( class_exists( '\Elementor\Plugin' ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}
wp_cache_flush();
WP_CLI::success( 'Four location pages restored from pre-change backup.' );
