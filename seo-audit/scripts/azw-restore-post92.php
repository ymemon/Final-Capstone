<?php
/**
 * Restore post #92 ("7 Best Internal Linking Plugins for WordPress
 * Websites") from revision #956. Its live post_content was wiped down to a
 * bare <h1> on 2026-07-26; the real ~21KB article body survives in the
 * 2024-06-14 revision, checked clean of the stray keyword-tool CSV text
 * that shows up in Search Console's historical impressions for this URL.
 *
 *     wp --path=/html eval-file azw-restore-post92.php          # DRY RUN
 *     wp --path=/html eval-file azw-restore-post92.php apply    # write
 */
$apply = (bool) array_intersect( array( 'apply', '--apply' ), (array) $args );

$post     = get_post( 92 );
$revision = get_post( 956 );

if ( ! $post || ! $revision ) {
	WP_CLI::error( 'post 92 or revision 956 not found' );
}

WP_CLI::line( 'Current post_content: ' . strlen( $post->post_content ) . ' bytes' );
WP_CLI::line( 'Revision content:     ' . strlen( $revision->post_content ) . ' bytes' );

if ( ! $apply ) {
	WP_CLI::line( "DRY RUN - nothing written. Re-run with 'apply'." );
	return;
}

$result = wp_update_post( array(
	'ID'           => 92,
	'post_content' => $revision->post_content,
), true );

if ( is_wp_error( $result ) ) {
	WP_CLI::error( $result->get_error_message() );
}

WP_CLI::success( 'post 92 content restored from revision 956.' );
