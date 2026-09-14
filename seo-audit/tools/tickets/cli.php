<?php
/**
 * `wp azwc-tickets ...` — the only interface the scheduled mailbox-checking
 * job uses. It has no direct database access (and no business having any);
 * every read and write goes through one of these commands so the state
 * machine in core.php stays the single source of truth.
 *
 * Every command prints one JSON object/array and nothing else, so the caller
 * (a script, not a person) can parse stdout directly.
 *
 * @package AZWC
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

WP_CLI::add_command(
	'azwc-tickets checkpoint-get',
	function () {
		WP_CLI::line( wp_json_encode( azwc_tk_get_checkpoint() ) );
	}
);

WP_CLI::add_command(
	'azwc-tickets checkpoint-set',
	function ( $args, $assoc ) {
		azwc_tk_set_checkpoint( $assoc['uid'] ?? 0, $assoc['date'] ?? '' );
		WP_CLI::line( wp_json_encode( array( 'ok' => true ) ) );
	}
);

WP_CLI::add_command(
	'azwc-tickets find',
	function ( $args, $assoc ) {
		$email   = $assoc['email'] ?? '';
		$subject = $assoc['subject'] ?? '';
		$mids    = ! empty( $assoc['message-ids'] ) ? array_filter( array_map( 'trim', explode( ',', $assoc['message-ids'] ) ) ) : array();

		$row = azwc_tk_find_thread( $email, $subject, $mids );
		WP_CLI::line( wp_json_encode( $row ? $row : array( 'found' => false ) ) );
	}
);

WP_CLI::add_command(
	'azwc-tickets create',
	function ( $args, $assoc ) {
		$email     = $assoc['email'] ?? '';
		$name      = $assoc['name'] ?? '';
		$subject   = $assoc['subject'] ?? '(no subject)';
		$direction = ( 'outbound' === ( $assoc['direction'] ?? '' ) ) ? 'outbound' : 'inbound';
		$mid       = $assoc['message-id'] ?? '';

		if ( ! is_email( $email ) ) {
			WP_CLI::line( wp_json_encode( array( 'error' => 'invalid email' ) ) );
			return;
		}

		$ticket_no = azwc_tk_create( $email, $name, $subject, $direction, $mid );
		WP_CLI::line( wp_json_encode( array( 'ticket_no' => $ticket_no ) ) );
	}
);

WP_CLI::add_command(
	'azwc-tickets activity',
	function ( $args, $assoc ) {
		$ticket    = $assoc['ticket'] ?? '';
		$direction = ( 'us' === ( $assoc['direction'] ?? '' ) ) ? 'us' : 'them';
		$mid       = $assoc['message-id'] ?? '';
		$note      = $assoc['note'] ?? '';

		$ok = azwc_tk_log_activity( $ticket, $direction, $mid, $note );
		WP_CLI::line( wp_json_encode( array( 'ok' => $ok ) ) );
	}
);

WP_CLI::add_command(
	'azwc-tickets resolve',
	function ( $args, $assoc ) {
		$ok = azwc_tk_resolve( $assoc['ticket'] ?? '' );
		WP_CLI::line( wp_json_encode( array( 'ok' => $ok ) ) );
	}
);

WP_CLI::add_command(
	'azwc-tickets get',
	function ( $args, $assoc ) {
		$row = azwc_tk_get( $assoc['ticket'] ?? '' );
		WP_CLI::line( wp_json_encode( $row ? $row : null ) );
	}
);

WP_CLI::add_command(
	'azwc-tickets list',
	function ( $args, $assoc ) {
		global $wpdb;
		$table = azwc_tk_table();

		if ( ! empty( $assoc['status'] ) ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY last_activity_gmt DESC", $assoc['status'] )
			);
		} else {
			$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY last_activity_gmt DESC" );
		}

		WP_CLI::line( wp_json_encode( $rows ) );
	}
);

WP_CLI::add_command(
	'azwc-tickets stale',
	function ( $args, $assoc ) {
		$hours = isset( $assoc['hours'] ) ? (int) $assoc['hours'] : AZWC_TK_STALE_HOURS;
		WP_CLI::line( wp_json_encode( azwc_tk_stale( $hours ) ) );
	}
);

WP_CLI::add_command(
	'azwc-tickets nudge-draft',
	function ( $args, $assoc ) {
		global $wpdb;
		$ticket = $assoc['ticket'] ?? '';
		$text   = $assoc['text'] ?? '';
		$row    = azwc_tk_get( $ticket );

		if ( ! $row ) {
			WP_CLI::line( wp_json_encode( array( 'error' => 'no such ticket' ) ) );
			return;
		}

		$token = azwc_tk_new_token();
		$wpdb->update(
			azwc_tk_table(),
			array(
				'nudge_status'      => 'pending_approval',
				'nudge_text'        => $text,
				'nudge_token'       => $token,
				'nudge_drafted_gmt' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'ticket_no' => $ticket )
		);

		WP_CLI::line(
			wp_json_encode(
				array(
					'approve_url' => azwc_tk_action_url( 'send', $token ),
					'skip_url'    => azwc_tk_action_url( 'skip', $token ),
				)
			)
		);
	}
);

WP_CLI::add_command(
	'azwc-tickets notify-pending',
	function () {
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT * FROM " . azwc_tk_table() . " WHERE nudge_status = 'pending_approval' AND nudge_drafted_gmt > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)"
		);
		$sent = 0;
		foreach ( $rows as $row ) {
			if ( function_exists( 'azwc_tk_send_nudge_approval_email' ) && azwc_tk_send_nudge_approval_email( $row ) ) {
				$sent++;
			}
		}
		WP_CLI::line( wp_json_encode( array( 'notified' => $sent ) ) );
	}
);

WP_CLI::add_command(
	'azwc-tickets notify-new',
	function ( $args, $assoc ) {
		$numbers = ! empty( $assoc['tickets'] ) ? array_filter( array_map( 'trim', explode( ',', $assoc['tickets'] ) ) ) : array();
		$rows    = array();
		foreach ( $numbers as $no ) {
			$row = azwc_tk_get( $no );
			if ( $row ) {
				$rows[] = array(
					'ticket_no'          => $row->ticket_no,
					'subject'            => $row->subject,
					'counterparty_name'  => $row->counterparty_name,
					'counterparty_email' => $row->counterparty_email,
				);
			}
		}
		$ok = function_exists( 'azwc_tk_send_new_tickets_email' ) && azwc_tk_send_new_tickets_email( $rows );
		WP_CLI::line( wp_json_encode( array( 'notified' => $ok ? count( $rows ) : 0 ) ) );
	}
);
