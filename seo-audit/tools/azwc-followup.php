<?php
/**
 * AZ Web Corp — SEO audit follow-up.
 *
 * Turns a finished audit into a conversation: email the report as a PDF, or
 * book a 30-minute call where we run a hand audit and talk it through.
 *
 * WHY THIS IS A LOADER AND NOTHING ELSE
 * WordPress `include`s every top-level .php file in mu-plugins on every single
 * request — that is what "must-use" means. A file in this directory that does
 * real work at load time runs on admin screens, on WP-CLI bootstrap, and on
 * REST calls that have nothing to do with it. So everything here lives in the
 * azwc-followup/ subdirectory (WordPress never auto-loads subfolders) and this
 * file only wires it up. A previous incident on this site took the whole thing
 * down by putting a template in the mu-plugins root; do not repeat it.
 *
 * @package AZWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AZWC_FU_VERSION', '1.0.0' );
define( 'AZWC_FU_DB_VERSION', '3' );
define( 'AZWC_FU_DIR', __DIR__ . '/azwc-followup' );

/**
 * Booking window.
 *
 * Deliberately NOT taken from WordPress's own timezone setting: this install
 * has `timezone_string` empty and `gmt_offset` 0, so WP believes it is in UTC.
 * Everything is stored in UTC and converted for display through this constant
 * only. Arizona does not observe daylight saving, so America/Phoenix is stable
 * year-round — but the conversion still goes through PHP's tz database rather
 * than a hardcoded offset, so it stays correct if that ever changes.
 */
define( 'AZWC_FU_TZ', 'America/Phoenix' );
define( 'AZWC_FU_OPEN_HOUR', 9 );    // 9am, first bookable start.
define( 'AZWC_FU_CLOSE_HOUR', 21 );  // 9pm, by which a call must have ENDED.
define( 'AZWC_FU_GRID_MIN', 15 );    // Slots are offered on a 15-minute grid...
define( 'AZWC_FU_CALL_MIN', 30 );    // ...but each call occupies 30 minutes.

define( 'AZWC_FU_MIN_NOTICE_H', 2 );  // No same-minute bookings.
define( 'AZWC_FU_HORIZON_D', 21 );    // How far ahead the calendar goes.
define( 'AZWC_FU_HOLD_H', 24 );       // How long an unconfirmed slot is held.
define( 'AZWC_FU_REMIND_MIN', 60 );   // Reminder lead time.

/** Anti-abuse. These are per IP, per hour. */
define( 'AZWC_FU_MAX_EMAILS_H', 5 );
define( 'AZWC_FU_MAX_BOOKINGS_H', 3 );

foreach ( array( 'core', 'pdf', 'mail', 'rest', 'admin', 'ui' ) as $azwc_fu_part ) {
	$azwc_fu_file = AZWC_FU_DIR . '/' . $azwc_fu_part . '.php';
	if ( is_readable( $azwc_fu_file ) ) {
		require_once $azwc_fu_file;
	}
}
unset( $azwc_fu_part, $azwc_fu_file );
