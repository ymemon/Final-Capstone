<?php
/**
 * Verify and, if needed, re-fix the "Company" menu item, then clear the caches
 * that keep serving a stale nav.
 *
 *     wp --path=/html eval-file azw-verify-fix-company-menu.php          # DRY RUN
 *     wp --path=/html eval-file azw-verify-fix-company-menu.php apply    # write
 *
 * WHY A SECOND SCRIPT
 * The first repair reported success, but the front end still rendered
 * <a></a> for this item. During that run WP-CLI logged
 * "Failed to initialize object cache: Connection timed out" and fell back to an
 * in-memory cache - so the wp_cache_flush() at the end flushed a throwaway
 * object, not the real persistent cache the front end reads from.
 *
 * So this reads the true state straight from the database with $wpdb, which no
 * object cache can mask, and only then decides whether a write is needed. After
 * writing it clears the nav-menu caches explicitly rather than trusting a
 * single global flush.
 */

global $wpdb;

$apply = (bool) array_intersect( array( 'apply', '--apply' ), (array) $args );

const ITEM_ID = 2490;
const TITLE   = 'Company';
const URL     = '#';

// Straight from the tables - not get_post()/get_post_meta(), which read through
// the object cache and would show us whatever it is holding.
$db_title = $wpdb->get_var( $wpdb->prepare(
	"SELECT post_title FROM {$wpdb->posts} WHERE ID = %d", ITEM_ID ) );
$db_url = $wpdb->get_var( $wpdb->prepare(
	"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_menu_item_url'",
	ITEM_ID ) );

WP_CLI::log( sprintf( 'DB   title=%s url=%s',
	( null === $db_title || '' === $db_title ) ? '(empty)' : $db_title,
	( null === $db_url || '' === $db_url ) ? '(empty)' : $db_url ) );

$needs_write = ( TITLE !== $db_title || URL !== $db_url );
WP_CLI::log( $needs_write ? 'database is WRONG - will rewrite' : 'database is correct - cache only' );

if ( ! $apply ) {
	WP_CLI::warning( 'DRY RUN - re-run with "apply" to write' );
	return;
}

if ( $needs_write ) {
	$wpdb->update( $wpdb->posts, array( 'post_title' => TITLE ), array( 'ID' => ITEM_ID ) );
	$wpdb->query( $wpdb->prepare(
		"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES (%d, '_menu_item_url', %s)
		 ON DUPLICATE KEY UPDATE meta_value = %s", ITEM_ID, URL, URL ) );
	WP_CLI::log( 'rewrote title and url directly' );
}

// Clear what actually holds the rendered menu.
clean_post_cache( ITEM_ID );
wp_cache_delete( ITEM_ID, 'posts' );
wp_cache_delete( ITEM_ID, 'post_meta' );

foreach ( wp_get_nav_menus() as $menu ) {
	wp_cache_delete( 'wp_get_nav_menu_items_' . $menu->term_id, 'nav_menu_items' );
	clean_term_cache( $menu->term_id, 'nav_menu' );
	delete_transient( 'wp_get_nav_menu_items_' . $menu->term_id );
}
wp_cache_delete( 'last_changed', 'posts' );
wp_cache_delete( 'last_changed', 'terms' );
wp_cache_flush();

// Re-read from the DB to confirm, then show what the renderer will now build.
$after_title = $wpdb->get_var( $wpdb->prepare(
	"SELECT post_title FROM {$wpdb->posts} WHERE ID = %d", ITEM_ID ) );
$after_url = $wpdb->get_var( $wpdb->prepare(
	"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_menu_item_url'",
	ITEM_ID ) );

if ( TITLE !== $after_title || URL !== $after_url ) {
	WP_CLI::error( sprintf( 'verify FAILED: title=%s url=%s', $after_title, $after_url ) );
}
WP_CLI::success( sprintf( 'DB confirmed: title=%s url=%s', $after_title, $after_url ) );

foreach ( (array) wp_get_nav_menu_items( 30 ) as $item ) {
	if ( (int) $item->db_id === ITEM_ID ) {
		WP_CLI::log( sprintf( 'renderer sees: title=%s url=%s',
			'' === $item->title ? '(empty)' : $item->title,
			'' === $item->url ? '(empty)' : $item->url ) );
	}
}
