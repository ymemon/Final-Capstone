<?php
/**
 * Dump a page inventory as CSV, for azw-query-gap.py to join against.
 *
 *     wp --path=/html eval-file azw-inventory.php > pages.csv
 *
 * Read-only.
 *
 * The word count is the reason this exists rather than `wp post list`. On an
 * Elementor page post_content is close to empty while the real copy sits in
 * _elementor_data, so counting post_content alone reports full pages as thin —
 * which is exactly the mistake that nearly got a set of real pages retired.
 * Whichever source holds the content, that is what gets counted.
 */

$posts = get_posts( array(
	'post_type'   => 'page',
	'post_status' => 'any',
	'numberposts' => -1,
	'orderby'     => 'ID',
	'order'       => 'ASC',
) );

$out = fopen( 'php://output', 'w' );
fputcsv( $out, array( 'ID', 'post_name', 'post_title', 'post_status', 'words', 'noindex', 'source' ) );

foreach ( $posts as $p ) {
	$content = $p->post_content;
	$source  = 'post_content';

	$el = get_post_meta( $p->ID, '_elementor_data', true );
	if ( $el ) {
		$decoded = json_decode( $el, true );
		if ( null === $decoded ) {
			$decoded = json_decode( wp_unslash( $el ), true );
		}
		if ( is_array( $decoded ) ) {
			// Pull every string out of the widget tree; which setting key holds
			// the copy varies by widget type, so take the lot and count words.
			$text = '';
			array_walk_recursive( $decoded, function ( $v, $k ) use ( &$text ) {
				if ( is_string( $v ) && strlen( $v ) > 3 && ! preg_match( '/^[a-f0-9]{6,8}$/i', $v ) ) {
					$text .= ' ' . $v;
				}
			} );
			if ( str_word_count( wp_strip_all_tags( $text ) ) > str_word_count( wp_strip_all_tags( $content ) ) ) {
				$content = $text;
				$source  = 'elementor';
			}
		}
	}

	$robots  = get_post_meta( $p->ID, 'rank_math_robots', true );
	$noindex = is_array( $robots ) && in_array( 'noindex', $robots, true ) ? 1 : 0;

	fputcsv( $out, array(
		$p->ID,
		$p->post_name,
		$p->post_title,
		$p->post_status,
		str_word_count( wp_strip_all_tags( $content ) ),
		$noindex,
		$source,
	) );
}

fclose( $out );
