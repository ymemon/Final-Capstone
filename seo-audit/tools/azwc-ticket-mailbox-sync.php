<?php
/**
 * AZ Web Corp — Ticket Mailbox Sync (real-time rebuild).
 *
 * SAME CAVEAT AS azwc-client-watch.php
 * This does not reverse-engineer the original "Azwebcorp ticket mailbox
 * sync" plugin — its source lives only on the production server and was
 * never accessible while writing this (no login, no network path to the
 * site). This is a clean replacement aimed at the same job as described:
 * catch client emails in real time, assign ticket numbers, thread replies
 * to the right ticket, and nudge clients when a ticket has gone quiet.
 * Review it against the real plugin before retiring that plugin.
 *
 * ONE HONEST LIMIT, STATED PLAINLY: this system has no visibility into
 * replies your team sends from their own inbox — it only sees what arrives
 * through the webhook below. So "nudge" here can only mean "the client
 * emailed and nothing further has come in since" — not "the team hasn't
 * replied yet." A ticket the team answered by email directly will still
 * look quiet to this plugin unless someone marks it resolved in
 * wp-admin. See ticket-mailbox-sync/README.md.
 *
 * Loader-only, same reasoning as azwc-followup.php and azwc-client-watch.php.
 *
 * @package AZWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AZWC_TMS_VERSION', '1.0.0' );
define( 'AZWC_TMS_DB_VERSION', '1' );
define( 'AZWC_TMS_DIR', __DIR__ . '/ticket-mailbox-sync' );

/** Days of no further client contact before a first nudge goes out. */
define( 'AZWC_TMS_NUDGE_DAYS', 3 );
/** Stop nudging after this many, so an abandoned ticket doesn't nudge forever. */
define( 'AZWC_TMS_NUDGE_MAX', 3 );

foreach ( array( 'core', 'rest', 'nudges', 'admin' ) as $azwc_tms_part ) {
	$azwc_tms_file = AZWC_TMS_DIR . '/' . $azwc_tms_part . '.php';
	if ( is_readable( $azwc_tms_file ) ) {
		require_once $azwc_tms_file;
	}
}
unset( $azwc_tms_part, $azwc_tms_file );
