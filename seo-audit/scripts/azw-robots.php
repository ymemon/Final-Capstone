<?php
/**
 * Set the Rank Math robots meta on a list of pages.
 *
 *     wp --path=/html eval-file azw-robots.php index    2094 2095 2099
 *     wp --path=/html eval-file azw-robots.php noindex  2100 2101 2103
 *     wp --path=/html eval-file azw-robots.php show     2094 2095
 *
 * `show` is read-only. The other two write immediately — this is a one-line
 * change per page and reversible by running the opposite command, so there is
 * no dry-run mode to forget to turn off.
 *
 * Why a file rather than a one-liner: rank_math_robots is an array, so setting
 * it from the shell means passing JSON, and that JSON has to survive PowerShell,
 * plink and bash in turn. PowerShell strips the inner quotes somewhere in that
 * chain and WP-CLI receives [index,follow], which is not valid JSON. Four
 * attempts at escaping it failed. A file has no quoting to lose.
 *
 * "follow" is deliberate on the noindex path: it keeps a hidden page passing
 * link equity onward to the pages it points at, which nofollow would strand.
 */

$argv0 = (array) $args;
$mode  = array_shift( $argv0 );
$ids   = array_values( array_filter( array_map( 'intval', $argv0 ) ) );

if ( ! in_array( $mode, array( 'index', 'noindex', 'show' ), true ) || ! $ids ) {
	WP_CLI::error( 'Usage: eval-file azw-robots.php <index|noindex|show> <id> [id...]' );
}

$value = 'index' === $mode ? array( 'index', 'follow' ) : array( 'noindex', 'follow' );

foreach ( $ids as $id ) {
	$post = get_post( $id );
	if ( ! $post ) {
		WP_CLI::warning( "{$id}: not found" );
		continue;
	}

	if ( 'show' === $mode ) {
		$current = get_post_meta( $id, 'rank_math_robots', true );
		WP_CLI::line( sprintf(
			'%-6d %-30s %s',
			$id,
			$post->post_name,
			is_array( $current ) ? implode( ',', $current ) : '(unset — theme default)'
		) );
		continue;
	}

	update_post_meta( $id, 'rank_math_robots', $value );
	WP_CLI::line( sprintf( '%-6d %-30s -> %s', $id, $post->post_name, implode( ',', $value ) ) );
}

if ( 'show' !== $mode ) {
	wp_cache_flush();
	if ( class_exists( '\RankMath\Sitemap\Cache' ) ) {
		\RankMath\Sitemap\Cache::invalidate_storage();
	}
	WP_CLI::line( '' );
	WP_CLI::success( count( $ids ) . ' page(s) set to ' . implode( ',', $value ) . '. Purge Cloudflare.' );
}
