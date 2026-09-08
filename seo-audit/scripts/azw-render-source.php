<?php
/**
 * Which source actually renders a page: post_content, or Elementor's stored
 * data? Editing post_content on an Elementor-built page changes nothing that
 * a visitor sees, so this has to be known before any content fix is attempted.
 *
 *     wp --path=/html eval-file azw-render-source.php <post_id> [post_id...]
 *
 * Read-only.
 */
foreach ( (array) $args as $id ) {
	$id   = (int) $id;
	$post = get_post( $id );
	if ( ! $post ) {
		WP_CLI::warning( "{$id}: not found" );
		continue;
	}

	$el_data = get_post_meta( $id, '_elementor_data', true );
	$el_mode = get_post_meta( $id, '_elementor_edit_mode', true );
	$tmpl    = get_post_meta( $id, '_wp_page_template', true );

	WP_CLI::line( sprintf(
		'#%-6d %-26s post_content=%6d bytes  _elementor_data=%-8s edit_mode=%-8s template=%s',
		$id,
		$post->post_name,
		strlen( $post->post_content ),
		$el_data ? strlen( $el_data ) . 'b' : 'none',
		$el_mode ?: '-',
		$tmpl ?: 'default'
	) );
}
