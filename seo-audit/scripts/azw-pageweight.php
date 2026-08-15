<?php
/**
 * Break down what a page's HTML is actually made of.
 *
 *     wp --path=/html eval-file azw-pageweight.php https://azwebcorp.com/
 *
 * Read-only.
 *
 * The audit tool reports that the home page is 2 MB of HTML against a norm of
 * about 150 KB, which is a real performance problem but not an actionable one:
 * "the document is too big" does not say what to delete. This attributes the
 * bytes, so the fix is obvious rather than exploratory.
 *
 * Inline CSS is the usual culprit on an Elementor site. The builder emits a
 * style block per widget, and on a long page that can outweigh the content by
 * an order of magnitude. Base64 data URIs are the second: an image inlined into
 * the HTML cannot be cached separately and is re-downloaded on every page view.
 */

$url = $args[0] ?? home_url( '/' );

$response = wp_remote_get( $url, array(
	'timeout'             => 30,
	'limit_response_size' => 12582912,
	'user-agent'          => 'AZWebCorpPageWeight/1.0',
	// Ask for uncompressed bytes: the point is the size the browser parses,
	// not the size that crossed the wire.
	'headers'             => array( 'Accept-Encoding' => 'identity' ),
) );

if ( is_wp_error( $response ) ) {
	WP_CLI::error( $response->get_error_message() );
}

$html  = (string) wp_remote_retrieve_body( $response );
$total = strlen( $html );
if ( ! $total ) {
	WP_CLI::error( 'Empty response.' );
}

/** Total bytes enclosed by a tag, and how many of them there are. */
function azw_pw_blocks( $html, $tag ) {
	$bytes  = 0;
	$count  = 0;
	$offset = 0;
	while ( false !== ( $start = stripos( $html, '<' . $tag, $offset ) ) ) {
		$close = stripos( $html, '</' . $tag, $start );
		if ( false === $close ) {
			break;
		}
		$end    = strpos( $html, '>', $close );
		$end    = false === $end ? strlen( $html ) : $end + 1;
		$bytes += $end - $start;
		$count++;
		$offset = $end;
	}
	return array( $count, $bytes );
}

list( $style_n, $style_b )   = azw_pw_blocks( $html, 'style' );
list( $script_n, $script_b ) = azw_pw_blocks( $html, 'script' );

// Base64 payloads inlined into the document.
preg_match_all( '~data:[a-z0-9.+/-]+;base64,[A-Za-z0-9+/=]+~i', $html, $data_uris );
$data_bytes = 0;
foreach ( $data_uris[0] as $uri ) {
	$data_bytes += strlen( $uri );
}

// HTML comments, which ship to every visitor and help none of them.
preg_match_all( '~<!--.*?-->~s', $html, $comments );
$comment_bytes = 0;
foreach ( $comments[0] as $c ) {
	$comment_bytes += strlen( $c );
}

// Inline style="" attributes, separate from <style> blocks.
preg_match_all( '~\sstyle\s*=\s*"[^"]*"~i', $html, $attrs );
$attr_bytes = 0;
foreach ( $attrs[0] as $a ) {
	$attr_bytes += strlen( $a );
}

$accounted = $style_b + $script_b + $data_bytes + $comment_bytes + $attr_bytes;

$rows = array(
	array( 'Inline <style> blocks', $style_b, $style_n . ' blocks' ),
	array( 'Inline <script> blocks', $script_b, $script_n . ' blocks' ),
	array( 'Base64 data: URIs', $data_bytes, count( $data_uris[0] ) . ' inlined files' ),
	array( 'style="" attributes', $attr_bytes, count( $attrs[0] ) . ' attributes' ),
	array( 'HTML comments', $comment_bytes, count( $comments[0] ) . ' comments' ),
	array( 'Markup and text', $total - $accounted, '' ),
);
usort( $rows, function ( $a, $b ) { return $b[1] <=> $a[1]; } );

WP_CLI::line( '' );
WP_CLI::line( $url );
WP_CLI::line( 'Total HTML: ' . size_format( $total, 1 ) . ' uncompressed' );
WP_CLI::line( str_repeat( '-', 62 ) );

foreach ( $rows as $row ) {
	if ( $row[1] <= 0 ) {
		continue;
	}
	WP_CLI::line( sprintf(
		'%-24s %10s  %5.1f%%  %s',
		$row[0],
		size_format( $row[1], 1 ),
		$row[1] / $total * 100,
		$row[2]
	) );
}

WP_CLI::line( str_repeat( '-', 62 ) );

// The largest single inline style block, which is usually the one worth moving.
$biggest = 0;
$offset  = 0;
while ( false !== ( $start = stripos( $html, '<style', $offset ) ) ) {
	$close = stripos( $html, '</style', $start );
	if ( false === $close ) {
		break;
	}
	$biggest = max( $biggest, $close - $start );
	$offset  = $close + 1;
}
if ( $biggest ) {
	WP_CLI::line( 'Largest single <style> block: ' . size_format( $biggest, 1 ) );
}

if ( $style_b > $total * 0.3 ) {
	WP_CLI::line( '' );
	WP_CLI::warning( 'Inline CSS is over 30% of the document. Moving it to cached external files is the single largest available saving here.' );
}
if ( $data_bytes > 102400 ) {
	WP_CLI::line( '' );
	WP_CLI::warning( 'Over 100 KB of base64 images are inlined. Those cannot be cached separately and are re-downloaded on every page view.' );
}
