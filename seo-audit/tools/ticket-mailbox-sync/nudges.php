<?php
/**
 * Client nudges: "still working on it" reminders, not real-time by nature.
 *
 * This is the one part of the system that is deliberately still a periodic
 * check rather than instant — a nudge is inherently about elapsed time
 * ("it has been N days"), so there is nothing to make "real time" about it.
 * What changed from the old plugin is only how new mail gets in; the
 * follow-up timing was never the slow part.
 *
 * WHAT THIS CANNOT KNOW: whether your team already replied outside this
 * system (their own inbox, not through the webhook). It only knows "the
 * client emailed and nothing further came in here since." Mark a ticket
 * resolved in wp-admin once it's actually handled, or it will nudge the
 * client again.
 *
 * @package AZWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'azwc_tms_schedule_nudges', 30 );

function azwc_tms_schedule_nudges() {
	if ( ! wp_next_scheduled( 'azwc_tms_nudge_tick' ) ) {
		wp_schedule_event( time() + 300, 'daily', 'azwc_tms_nudge_tick' );
	}
}

add_action( 'azwc_tms_nudge_tick', 'azwc_tms_nudge_tick' );

function azwc_tms_nudge_tick() {
	global $wpdb;
	$table   = azwc_tms_tickets_table();
	$cutoff  = gmdate( 'Y-m-d H:i:s', time() - ( AZWC_TMS_NUDGE_DAYS * DAY_IN_SECONDS ) );

	$due = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table}
			  WHERE status = 'open'
			    AND nudge_count < %d
			    AND last_client_gmt <= %s
			    AND ( nudged_gmt IS NULL OR nudged_gmt <= %s )",
			AZWC_TMS_NUDGE_MAX,
			$cutoff,
			$cutoff
		)
	);

	foreach ( $due as $row ) {
		// Stamped before sending: a mail failure must not retry every tick.
		$wpdb->update(
			$table,
			array(
				'nudged_gmt'  => gmdate( 'Y-m-d H:i:s' ),
				'nudge_count' => (int) $row->nudge_count + 1,
			),
			array( 'id' => $row->id )
		);
		azwc_tms_send_nudge( $row );
	}
}

function azwc_tms_send_nudge( $row ) {
	$number = azwc_tms_format_number( $row->id );
	$first  = trim( strtok( trim( $row->client_name ), ' ' ) );

	$body = '<p>' . ( $first ? 'Hi ' . esc_html( $first ) . ',' : 'Hi,' ) . '</p>'
		. '<p>Just checking in on <b>' . esc_html( $number ) . '</b> — we wanted to make sure this hasn\'t '
		. 'fallen through the cracks. If you\'ve already heard back from us, no need to reply.</p>'
		. '<p style="font-size:13px;color:#6b7480;">AZ Web Corp &middot; 480-818-5761 &middot; info@azwebcorp.com</p>';

	azwc_tms_send( $row->client_email, "Checking in [Ticket #{$number}]", $body );
}
