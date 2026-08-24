<?php
/**
 * Read and edit Elementor page content from WP-CLI.
 *
 *   wp --path=/html eval-file azw-elementor.php list 2292
 *   wp --path=/html eval-file azw-elementor.php dump 2292 a1b2c3d
 *   wp --path=/html eval-file azw-elementor.php remove 2292 a1b2c3d apply
 *   wp --path=/html eval-file azw-elementor.php replace 2292 a1b2c3d content/x.html apply
 *   wp --path=/html eval-file azw-elementor.php append 2292 content/x.html apply
 *   wp --path=/html eval-file azw-elementor.php restore 2292 apply
 *
 * Why this exists: Elementor renders from the _elementor_data meta and ignores
 * post_content entirely. Publishing to post_content on an Elementor page puts
 * the words in the database where nothing will ever read them. Clearing
 * _elementor_edit_mode makes post_content render but throws away the page
 * design, which is not a trade worth making on a page that already looks right.
 * So: edit the builder's own JSON, and leave the layout alone.
 *
 * Every write backs up _elementor_data to _azw_elementor_backup first. `restore`
 * puts it back. Only one backup is kept, so check the result before editing the
 * same page twice.
 *
 * Nothing is written without the trailing `apply`.
 */

$argv0 = (array) $args;
$cmd   = $argv0[0] ?? 'list';
$post  = isset( $argv0[1] ) ? (int) $argv0[1] : 0;
$apply = in_array( 'apply', $argv0, true );

if ( ! $post ) {
	WP_CLI::error( 'Usage: eval-file azw-elementor.php <list|dump|remove|replace|append|restore> <post_id> [args] [apply]' );
}

$raw = get_post_meta( $post, '_elementor_data', true );
if ( ! $raw && 'restore' !== $cmd ) {
	WP_CLI::error( "Post {$post} has no _elementor_data — use the post_content publisher instead." );
}

/**
 * Elementor writes this through update_post_meta, which unslashes before
 * storing, so what comes back is normally valid JSON already. Running
 * wp_unslash on it strips the backslashes that are part of the JSON escaping
 * (\", \/, \uXXXX) and breaks the decode. Try it straight first; only fall back
 * to unslashing for installs where something re-slashed the value on the way in.
 */
$data = array();
if ( $raw ) {
	$data = json_decode( $raw, true );
	if ( null === $data ) {
		$data = json_decode( wp_unslash( $raw ), true );
	}
	if ( null === $data ) {
		WP_CLI::error( sprintf(
			'_elementor_data is not valid JSON (%s) — refusing to touch it.',
			json_last_error_msg()
		) );
	}
}

/** Walk every element depth-first, passing each node by reference. */
function azw_walk( array &$nodes, callable $fn, $depth = 0 ) {
	foreach ( $nodes as $i => &$node ) {
		$fn( $node, $depth );
		if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
			azw_walk( $node['elements'], $fn, $depth + 1 );
		}
	}
	unset( $node );
}

/** Drop a node by id, wherever it sits. Returns true if something went. */
function azw_remove( array &$nodes, $id ) {
	foreach ( $nodes as $i => &$node ) {
		if ( ( $node['id'] ?? '' ) === $id ) {
			unset( $nodes[ $i ] );
			$nodes = array_values( $nodes );
			return true;
		}
		if ( ! empty( $node['elements'] ) && azw_remove( $node['elements'], $id ) ) {
			return true;
		}
	}
	unset( $node );
	return false;
}

/** Find a node by id. Returns a reference, or null. */
function &azw_find( array &$nodes, $id ) {
	$null = null;
	foreach ( $nodes as &$node ) {
		if ( ( $node['id'] ?? '' ) === $id ) {
			return $node;
		}
		if ( ! empty( $node['elements'] ) ) {
			$hit = &azw_find( $node['elements'], $id );
			if ( null !== $hit ) {
				return $hit;
			}
			unset( $hit );
		}
	}
	unset( $node );
	return $null;
}

/** The text a widget actually shows, for whichever setting key it uses. */
function azw_text( array $node ) {
	$s = $node['settings'] ?? array();
	foreach ( array( 'editor', 'title', 'html', 'shortcode', 'text', 'description_text' ) as $k ) {
		if ( ! empty( $s[ $k ] ) && is_string( $s[ $k ] ) ) {
			return $s[ $k ];
		}
	}
	return '';
}

/** Elementor ids are 7 hex chars and must not collide within a page. */
function azw_id() {
	return substr( md5( uniqid( '', true ) ), 0, 7 );
}

/** Wrap HTML in the section > column > text-editor shape Elementor expects. */
function azw_section( $html ) {
	return array(
		'id'       => azw_id(),
		'elType'   => 'section',
		'settings' => array(),
		'elements' => array(
			array(
				'id'       => azw_id(),
				'elType'   => 'column',
				'settings' => array( '_column_size' => 100, '_inline_size' => null ),
				'elements' => array(
					array(
						'id'         => azw_id(),
						'elType'     => 'widget',
						'widgetType' => 'html',
						'settings'   => array( 'html' => $html ),
					),
				),
			),
		),
	);
}

/**
 * One of our HTML deliverables, ready to drop into an Elementor HTML widget:
 * the JSON-LD blocks followed by the body, minus the <h1> the theme renders.
 *
 * The schema has to come along. It sits outside <body> in the source files, and
 * on an Elementor page nothing else will emit it — post_content is never read,
 * so schema left there would be invisible to crawlers.
 */
function azw_body( $file ) {
	if ( ! is_readable( $file ) ) {
		WP_CLI::error( "Cannot read {$file}" );
	}
	$src = file_get_contents( $file );
	if ( ! preg_match( '#<body>(.*?)</body>#s', $src, $m ) ) {
		WP_CLI::error( "No <body> block in {$file}" );
	}
	$body = trim( preg_replace( '#<h1>.*?</h1>#s', '', $m[1], 1 ) );

	preg_match_all( '#<script type="application/ld\+json">.*?</script>#s', $src, $schema );
	if ( $schema[0] ) {
		$body = implode( "\n", $schema[0] ) . "\n\n" . $body;
	}
	return $body;
}

function azw_save( $post, $data, $apply ) {
	if ( ! $apply ) {
		WP_CLI::line( '' );
		WP_CLI::line( 'DRY RUN — nothing written. Append `apply` to commit.' );
		return;
	}
	$current = get_post_meta( $post, '_elementor_data', true );
	update_post_meta( $post, '_azw_elementor_backup', $current );
	update_post_meta( $post, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );

	// Elementor caches generated CSS per post; stale CSS hides new widgets.
	delete_post_meta( $post, '_elementor_css' );
	if ( class_exists( '\Elementor\Plugin' ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
	wp_cache_flush();
	WP_CLI::success( "Post {$post} updated. Backup in _azw_elementor_backup." );
	WP_CLI::line( 'Purge Cloudflare before checking the live URL.' );
}

switch ( $cmd ) {

	case 'list':
		WP_CLI::line( "Post {$post}: " . get_the_title( $post ) );
		WP_CLI::line( '' );
		azw_walk( $data, function ( $node, $depth ) {
			$type = $node['elType'] ?? '?';
			if ( 'widget' === $type ) {
				$type = 'widget:' . ( $node['widgetType'] ?? '?' );
			}
			$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( azw_text( $node ) ) ) );
			WP_CLI::line( sprintf(
				'%s%-7s  %-28s %s',
				str_repeat( '  ', $depth ),
				$node['id'] ?? '???????',
				$type,
				mb_strimwidth( $text, 0, 70, '…' )
			) );
		} );
		break;

	case 'dump':
		$id   = $argv0[2] ?? '';
		$node = &azw_find( $data, $id );
		if ( null === $node ) {
			WP_CLI::error( "No element {$id} on post {$post}" );
		}
		WP_CLI::line( wp_json_encode( $node, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		break;

	case 'remove':
		$id   = $argv0[2] ?? '';
		$node = &azw_find( $data, $id );
		if ( null === $node ) {
			WP_CLI::error( "No element {$id} on post {$post}" );
		}
		WP_CLI::line( 'Removing:' );
		WP_CLI::line( '  ' . $id . '  ' . ( $node['widgetType'] ?? $node['elType'] ?? '?' ) );
		WP_CLI::line( '  ' . mb_strimwidth( trim( wp_strip_all_tags( azw_text( $node ) ) ), 0, 200, '…' ) );
		unset( $node );
		azw_remove( $data, $id );
		azw_save( $post, $data, $apply );
		break;

	case 'replace':
		$id   = $argv0[2] ?? '';
		$file = $argv0[3] ?? '';
		$node = &azw_find( $data, $id );
		if ( null === $node ) {
			WP_CLI::error( "No element {$id} on post {$post}" );
		}
		$html = azw_body( $file );
		WP_CLI::line( sprintf( 'Replacing %s (%s)', $id, $node['widgetType'] ?? $node['elType'] ) );
		WP_CLI::line( '  old: ' . mb_strimwidth( trim( wp_strip_all_tags( azw_text( $node ) ) ), 0, 90, '…' ) );
		WP_CLI::line( '  new: ' . mb_strimwidth( trim( wp_strip_all_tags( $html ) ), 0, 90, '…' ) );
		// An html widget renders its content verbatim. text-editor would put the
		// markup through wpautop and strip the JSON-LD, which is most of the point.
		$node['elType']     = 'widget';
		$node['widgetType'] = 'html';
		$node['settings']   = array( 'html' => $html );
		unset( $node['elements'] );
		unset( $node );
		azw_save( $post, $data, $apply );
		break;

	case 'append':
		$file = $argv0[2] ?? '';
		$html = azw_body( $file );
		WP_CLI::line( sprintf( 'Appending %d bytes as a new section', strlen( $html ) ) );
		WP_CLI::line( '  ' . mb_strimwidth( trim( wp_strip_all_tags( $html ) ), 0, 90, '…' ) );
		$data[] = azw_section( $html );
		azw_save( $post, $data, $apply );
		break;

	case 'restore':
		$backup = get_post_meta( $post, '_azw_elementor_backup', true );
		if ( ! $backup ) {
			WP_CLI::error( "No backup stored for post {$post}" );
		}
		WP_CLI::line( sprintf( 'Restoring %d bytes of _elementor_data', strlen( $backup ) ) );
		if ( $apply ) {
			update_post_meta( $post, '_elementor_data', $backup );
			delete_post_meta( $post, '_elementor_css' );
			if ( class_exists( '\Elementor\Plugin' ) ) {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			}
			wp_cache_flush();
			WP_CLI::success( "Post {$post} restored." );
		} else {
			WP_CLI::line( 'DRY RUN — nothing written. Append `apply` to commit.' );
		}
		break;

	default:
		WP_CLI::error( "Unknown command '{$cmd}'" );
}
