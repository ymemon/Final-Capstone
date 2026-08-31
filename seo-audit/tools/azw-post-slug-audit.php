<?php
/** Read-only WP-CLI helper for auditing specific search-visible posts. */

$slugs = array(
	'how-much-does-seo-cost-in-arizona',
	'phoenix-seo-expert-local-search',
	'what-is-critical-error-on-wordpress',
);

foreach ( $slugs as $slug ) {
	$posts = get_posts(
		array(
			'name'           => $slug,
			'post_type'      => 'any',
			'post_status'    => 'any',
			'posts_per_page' => -1,
		)
	);
	foreach ( $posts as $post ) {
		WP_CLI::line(
			wp_json_encode(
				array(
					'ID'            => $post->ID,
					'post_type'     => $post->post_type,
					'post_status'   => $post->post_status,
					'post_date'     => $post->post_date,
					'post_modified' => $post->post_modified,
					'post_title'    => $post->post_title,
					'post_name'     => $post->post_name,
					'word_count'    => str_word_count( wp_strip_all_tags( $post->post_content ) ),
					'robots'        => get_post_meta( $post->ID, 'rank_math_robots', true ),
					'rank_title'    => get_post_meta( $post->ID, 'rank_math_title', true ),
					'description'   => get_post_meta( $post->ID, 'rank_math_description', true ),
				),
				JSON_UNESCAPED_SLASHES
			)
		);
	}
}
