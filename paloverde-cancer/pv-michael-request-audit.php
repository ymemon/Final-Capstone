<?php
/** Read-only WP-CLI audit for Michael Bustard's 31 Aug correction list. */

global $wpdb;

foreach ( array( 73, 2159, 2162, 961, 533, 461, 501, 544 ) as $id ) {
	$post = get_post( $id );
	$data = (string) get_post_meta( $id, '_elementor_data', true );
	$content = (string) $post->post_content;
	preg_match_all( '~https?://[^"\'\\s]+?\.(?:jpe?g|png|webp)~i', $data . "\n" . $content, $matches );
	$images = array_values( array_unique( array_map( 'html_entity_decode', $matches[0] ) ) );
	WP_CLI::line( wp_json_encode( array(
		'id' => $id,
		'title' => $post->post_title,
		'slug' => $post->post_name,
		'template' => get_post_meta( $id, '_wp_page_template', true ),
		'content_bytes' => strlen( $content ),
		'elementor_bytes' => strlen( $data ),
		'images' => $images,
		'has_map' => (bool) preg_match( '~google\.(?:com|[a-z.]+)/maps|maps\.google~i', $data . $content ),
		'headings' => preg_match_all( '~<h[1-3][^>]*>(.*?)</h[1-3]>~is', $content, $heading_matches )
			? array_map( static fn( $v ) => trim( wp_strip_all_tags( $v ) ), $heading_matches[1] ) : array(),
	), JSON_UNESCAPED_SLASHES ) );
}

$media = $wpdb->get_results(
	"SELECT p.ID, p.post_title, pm.meta_value AS file, meta.meta_value AS dimensions
	 FROM {$wpdb->posts} p
	 JOIN {$wpdb->postmeta} pm ON pm.post_id=p.ID AND pm.meta_key='_wp_attached_file'
	 LEFT JOIN {$wpdb->postmeta} meta ON meta.post_id=p.ID AND meta.meta_key='_wp_attachment_metadata'
	 WHERE p.post_type='attachment'
	 AND (p.post_title REGEXP 'Gilbert|East Valley|Estrella|Glendale|Scottsdale|PET|Mamani'
	      OR pm.meta_value REGEXP 'Gilbert|East.Valley|Estrella|Glendale|Scottsdale|PET|Mamani')
	 ORDER BY p.ID"
);
foreach ( $media as $item ) {
	$metadata = maybe_unserialize( $item->dimensions );
	WP_CLI::line( wp_json_encode( array(
		'media_id' => (int) $item->ID,
		'title' => $item->post_title,
		'file' => $item->file,
		'width' => isset( $metadata['width'] ) ? (int) $metadata['width'] : null,
		'height' => isset( $metadata['height'] ) ? (int) $metadata['height'] : null,
	), JSON_UNESCAPED_SLASHES ) );
}
