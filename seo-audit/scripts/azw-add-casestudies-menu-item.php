<?php
/**
 * Add "Case Studies" to the primary navigation menu.
 *
 *     wp --path=/html eval-file azw-add-casestudies-menu-item.php          # DRY RUN
 *     wp --path=/html eval-file azw-add-casestudies-menu-item.php apply    # write
 *
 * WHY THIS EXISTS
 * The case studies page (ID 2104) has been published and linked from the body
 * of two service pages since the last session, but it was never added to the
 * navigation - so from the front of the site there was no way to find it, which
 * is exactly what Yasir reported. The page was finished; the menu item was the
 * missing piece.
 *
 * Placed at top level rather than under Company. It is proof-of-work aimed at
 * people deciding whether to hire us, not company boilerplate sitting next to
 * the privacy policy, and buried proof does not get read.
 *
 * Run through a file rather than `wp menu item add-post` because ssh_run.bat
 * mangles a --title containing a space ("Too many positional arguments").
 *
 * Idempotent: exits without writing if an item already points at the page.
 */

$apply = (bool) array_intersect( array( 'apply', '--apply' ), (array) $args );

const MENU_TERM_ID = 30;    // "primary menu", location menu-1
const PAGE_ID      = 2104;  // "Case Studies"
const TITLE        = 'Case Studies';
const AFTER_ITEM   = 2478;  // "Free SEO Check" - new item lands just after it

$menu = wp_get_nav_menu_object( MENU_TERM_ID );
if ( ! $menu ) {
	WP_CLI::error( 'menu ' . MENU_TERM_ID . ' not found' );
}

$page = get_post( PAGE_ID );
if ( ! $page || 'publish' !== $page->post_status ) {
	WP_CLI::error( 'page ' . PAGE_ID . ' missing or not published' );
}

$items = wp_get_nav_menu_items( MENU_TERM_ID );
foreach ( (array) $items as $item ) {
	if ( 'post_type' === $item->type && (int) $item->object_id === PAGE_ID ) {
		WP_CLI::success( 'already present as item ' . $item->db_id . ' - nothing to do' );
		return;
	}
}

// Sit immediately after Free SEO Check, shifting everything below it down so
// no two top-level items share a menu_order.
$position = 7;
foreach ( (array) $items as $item ) {
	if ( (int) $item->db_id === AFTER_ITEM ) {
		$position = (int) $item->menu_order + 1;
	}
}

WP_CLI::log( sprintf( 'menu   : %s (%d)', $menu->name, MENU_TERM_ID ) );
WP_CLI::log( sprintf( 'page   : %s (%d) %s', $page->post_title, PAGE_ID, get_permalink( PAGE_ID ) ) );
WP_CLI::log( sprintf( 'insert : top level, menu_order %d', $position ) );

if ( ! $apply ) {
	WP_CLI::warning( 'DRY RUN - re-run with "apply" to write' );
	return;
}

// Shift the items below the insertion point down by writing menu_order on the
// post directly.
//
// Do NOT use wp_update_nav_menu_item() with only 'menu-item-position' here. It
// treats every omitted key as empty, so on a *custom* item - whose title and
// URL are stored on the menu item itself rather than derived from a linked post
// - it silently wipes both. That is exactly what happened to "Company" (2490)
// the first time this ran: the item survived with its parent and children
// intact but rendered as an empty <a></a> in the live nav.
global $wpdb;
foreach ( (array) $items as $item ) {
	if ( (int) $item->menu_item_parent === 0 && (int) $item->menu_order >= $position ) {
		$wpdb->update(
			$wpdb->posts,
			array( 'menu_order' => (int) $item->menu_order + 1 ),
			array( 'ID' => (int) $item->db_id )
		);
		clean_post_cache( (int) $item->db_id );
	}
}

$new_id = wp_update_nav_menu_item( MENU_TERM_ID, 0, array(
	'menu-item-title'     => TITLE,
	'menu-item-object'    => 'page',
	'menu-item-object-id' => PAGE_ID,
	'menu-item-type'      => 'post_type',
	'menu-item-status'    => 'publish',
	'menu-item-parent-id' => 0,
	'menu-item-position'  => $position,
) );

if ( is_wp_error( $new_id ) ) {
	WP_CLI::error( $new_id->get_error_message() );
}

WP_CLI::success( 'added menu item ' . $new_id . ' -> ' . get_permalink( PAGE_ID ) );
WP_CLI::log( 'to undo: wp --path=/html menu item delete ' . $new_id );
