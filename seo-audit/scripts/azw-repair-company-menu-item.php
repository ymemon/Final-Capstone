<?php
/**
 * Repair the "Company" menu item (2490), blanked while adding Case Studies.
 *
 *     wp --path=/html eval-file azw-repair-company-menu-item.php          # DRY RUN
 *     wp --path=/html eval-file azw-repair-company-menu-item.php apply    # write
 *
 * WHAT WENT WRONG
 * azw-add-casestudies-menu-item.php shifted the items below the insertion point
 * down by calling wp_update_nav_menu_item() with ONLY 'menu-item-position'.
 * That function treats every omitted key as an empty value, so for a *custom*
 * item - whose title and URL live on the menu item itself rather than on a
 * linked post - it wiped both. Post-type items (About Us, Contact Us and the
 * rest) survived because their title and URL are derived from the linked page,
 * so only "Company" was damaged.
 *
 * The lesson, now fixed in the adder: never reposition a nav item by passing a
 * partial argument array. Either pass the item's full existing state, or move
 * it by updating menu_order on the post directly, which is what the adder does
 * now.
 */

$apply = (bool) array_intersect( array( 'apply', '--apply' ), (array) $args );

const ITEM_ID = 2490;
const TITLE   = 'Company';
const URL     = '#';

$item = get_post( ITEM_ID );
if ( ! $item || 'nav_menu_item' !== $item->post_type ) {
	WP_CLI::error( 'item ' . ITEM_ID . ' is not a nav menu item' );
}

$title = $item->post_title;
$url   = get_post_meta( ITEM_ID, '_menu_item_url', true );
$type  = get_post_meta( ITEM_ID, '_menu_item_type', true );

WP_CLI::log( sprintf( 'current : title=%s url=%s type=%s',
	'' === $title ? '(empty)' : $title,
	'' === $url ? '(empty)' : $url,
	$type ) );

if ( TITLE === $title && URL === $url ) {
	WP_CLI::success( 'already correct - nothing to do' );
	return;
}
WP_CLI::log( sprintf( 'restore : title=%s url=%s', TITLE, URL ) );

if ( ! $apply ) {
	WP_CLI::warning( 'DRY RUN - re-run with "apply" to write' );
	return;
}

// Write the two damaged fields directly. Deliberately NOT via
// wp_update_nav_menu_item(), which is what caused the damage in the first place.
wp_update_post( array( 'ID' => ITEM_ID, 'post_title' => TITLE ) );
update_post_meta( ITEM_ID, '_menu_item_url', URL );
update_post_meta( ITEM_ID, '_menu_item_type', 'custom' );

wp_cache_flush();

$check_title = get_post( ITEM_ID )->post_title;
$check_url   = get_post_meta( ITEM_ID, '_menu_item_url', true );
if ( TITLE === $check_title && URL === $check_url ) {
	WP_CLI::success( sprintf( 'restored: title=%s url=%s', $check_title, $check_url ) );
} else {
	WP_CLI::error( sprintf( 'verify FAILED: title=%s url=%s', $check_title, $check_url ) );
}
