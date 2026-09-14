<?php
/**
 * REST wrapper around the same functions cli.php exposes to WP-CLI —
 * so the scheduled mailbox-checking job (a cloud routine with no SSH access
 * to this server) can drive tickets over plain HTTPS instead. Every route
 * calls straight into core.php; nothing here duplicates ticket logic.
 *
 * Auth is a single shared secret compared with hash_equals(), not WordPress
 * application passwords or OAuth — this is a narrow, single-purpose key with
 * no access to anything else on the site, generated once and handed to the
 * routine's own config. Rotate it by changing the `azwc_tk_api_key` option;
 * anything using the old value stops working immediately.
 *
 * @package AZWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'azwc_tk_register_routes' );

function azwc_tk_register_routes() {
	$auth = 'azwc_tk_check_key';

	register_rest_route(
		'azwc-tickets/v1',
		'/checkpoint',
		array(
			array(
				'methods'             => 'GET',
				'permission_callback' => $auth,
				'callback'            => function () {
					return azwc_tk_get_checkpoint();
				},
			),
			array(
				'methods'             => 'POST',
				'permission_callback' => $auth,
				'callback'            => function ( $req ) {
					azwc_tk_set_checkpoint( $req->get_param( 'uid' ), $req->get_param( 'date' ) );
					return array( 'ok' => true );
				},
			),
		)
	);

	register_rest_route(
		'azwc-tickets/v1',
		'/find',
		array(
			'methods'             => 'POST',
			'permission_callback' => $auth,
			'callback'            => function ( $req ) {
				$mids = (array) $req->get_param( 'message_ids' );
				$row  = azwc_tk_find_thread( $req->get_param( 'email' ), $req->get_param( 'subject' ), $mids );
				return $row ? $row : array( 'found' => false );
			},
		)
	);

	register_rest_route(
		'azwc-tickets/v1',
		'/create',
		array(
			'methods'             => 'POST',
			'permission_callback' => $auth,
			'callback'            => function ( $req ) {
				$email = $req->get_param( 'email' );
				if ( ! is_email( $email ) ) {
					return new WP_Error( 'bad_email', 'invalid email', array( 'status' => 400 ) );
				}
				$ticket_no = azwc_tk_create(
					$email,
					(string) $req->get_param( 'name' ),
					(string) $req->get_param( 'subject' ),
					( 'outbound' === $req->get_param( 'direction' ) ) ? 'outbound' : 'inbound',
					(string) $req->get_param( 'message_id' )
				);
				// Hand back the client buttons with the ticket so the mailer can
				// drop them straight into the email it is already building,
				// without a second round trip.
				return array(
					'ticket_no'     => $ticket_no,
					'client_token'  => azwc_tk_client_token( $ticket_no ),
					'buttons_html'  => azwc_tk_client_buttons_html( $ticket_no ),
				);
			},
		)
	);

	/**
	 * The "All sorted" / "I'll reply" block for an existing ticket.
	 *
	 * Separate route because most outbound mail replies on a ticket that already
	 * exists, so /create is not involved.
	 */
	register_rest_route(
		'azwc-tickets/v1',
		'/client-buttons',
		array(
			'methods'             => 'GET',
			'permission_callback' => $auth,
			'callback'            => function ( $req ) {
				$ticket_no = (string) $req->get_param( 'ticket' );
				$row       = azwc_tk_get( $ticket_no );
				if ( ! $row ) {
					return new WP_Error( 'not_found', 'unknown ticket', array( 'status' => 404 ) );
				}
				$token = azwc_tk_client_token( $ticket_no );
				return array(
					'ticket_no'    => $ticket_no,
					'client_token' => $token,
					'resolved_url' => azwc_tk_client_url( 'resolved', $token ),
					'replying_url' => azwc_tk_client_url( 'replying', $token ),
					'buttons_html' => azwc_tk_client_buttons_html( $ticket_no ),
				);
			},
		)
	);

	register_rest_route(
		'azwc-tickets/v1',
		'/activity',
		array(
			'methods'             => 'POST',
			'permission_callback' => $auth,
			'callback'            => function ( $req ) {
				$ok = azwc_tk_log_activity(
					$req->get_param( 'ticket' ),
					( 'us' === $req->get_param( 'direction' ) ) ? 'us' : 'them',
					(string) $req->get_param( 'message_id' ),
					(string) $req->get_param( 'note' )
				);
				return array( 'ok' => $ok );
			},
		)
	);

	register_rest_route(
		'azwc-tickets/v1',
		'/resolve',
		array(
			'methods'             => 'POST',
			'permission_callback' => $auth,
			'callback'            => function ( $req ) {
				return array( 'ok' => azwc_tk_resolve( $req->get_param( 'ticket' ) ) );
			},
		)
	);

	register_rest_route(
		'azwc-tickets/v1',
		'/get',
		array(
			'methods'             => 'GET',
			'permission_callback' => $auth,
			'callback'            => function ( $req ) {
				$row = azwc_tk_get( $req->get_param( 'ticket' ) );
				return $row ? $row : null;
			},
		)
	);

	register_rest_route(
		'azwc-tickets/v1',
		'/list',
		array(
			'methods'             => 'GET',
			'permission_callback' => $auth,
			'callback'            => function ( $req ) {
				global $wpdb;
				$table  = azwc_tk_table();
				$status = $req->get_param( 'status' );
				if ( $status ) {
					return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY last_activity_gmt DESC", $status ) );
				}
				return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY last_activity_gmt DESC" );
			},
		)
	);

	register_rest_route(
		'azwc-tickets/v1',
		'/stale',
		array(
			'methods'             => 'GET',
			'permission_callback' => $auth,
			'callback'            => function ( $req ) {
				$hours = $req->get_param( 'hours' ) ? (int) $req->get_param( 'hours' ) : AZWC_TK_STALE_HOURS;
				return azwc_tk_stale( $hours );
			},
		)
	);

	register_rest_route(
		'azwc-tickets/v1',
		'/nudge-draft',
		array(
			'methods'             => 'POST',
			'permission_callback' => $auth,
			'callback'            => function ( $req ) {
				global $wpdb;
				$ticket = $req->get_param( 'ticket' );
				$row    = azwc_tk_get( $ticket );
				if ( ! $row ) {
					return new WP_Error( 'no_ticket', 'no such ticket', array( 'status' => 404 ) );
				}

				$token = azwc_tk_new_token();
				$wpdb->update(
					azwc_tk_table(),
					array(
						'nudge_status'      => 'pending_approval',
						'nudge_text'        => (string) $req->get_param( 'text' ),
						'nudge_token'       => $token,
						'nudge_drafted_gmt' => gmdate( 'Y-m-d H:i:s' ),
					),
					array( 'ticket_no' => $ticket )
				);

				return array(
					'approve_url' => azwc_tk_action_url( 'send', $token ),
					'skip_url'    => azwc_tk_action_url( 'skip', $token ),
				);
			},
		)
	);

	register_rest_route(
		'azwc-tickets/v1',
		'/notify-pending',
		array(
			'methods'             => 'POST',
			'permission_callback' => $auth,
			'callback'            => function () {
				global $wpdb;
				$rows = $wpdb->get_results(
					"SELECT * FROM " . azwc_tk_table() . " WHERE nudge_status = 'pending_approval' AND nudge_drafted_gmt > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)"
				);
				$sent = 0;
				foreach ( $rows as $row ) {
					if ( azwc_tk_send_nudge_approval_email( $row ) ) {
						$sent++;
					}
				}
				return array( 'notified' => $sent );
			},
		)
	);

	register_rest_route(
		'azwc-tickets/v1',
		'/notify-new-tickets',
		array(
			'methods'             => 'POST',
			'permission_callback' => $auth,
			'callback'            => function ( $req ) {
				// Ticket numbers only, not caller-supplied details — the server
				// looks up each row itself so the email can't say anything the
				// database doesn't actually agree with.
				$numbers = (array) $req->get_param( 'tickets' );
				$rows    = array();
				foreach ( $numbers as $no ) {
					$row = azwc_tk_get( (string) $no );
					if ( $row ) {
						$rows[] = array(
							'ticket_no'          => $row->ticket_no,
							'subject'            => $row->subject,
							'counterparty_name'  => $row->counterparty_name,
							'counterparty_email' => $row->counterparty_email,
						);
					}
				}
				return array( 'notified' => azwc_tk_send_new_tickets_email( $rows ) ? count( $rows ) : 0 );
			},
		)
	);
}

function azwc_tk_check_key( $req ) {
	$expected = get_option( 'azwc_tk_api_key', '' );
	if ( ! $expected ) {
		return false;
	}
	$given = $req->get_header( 'x-azwc-key' );
	return is_string( $given ) && hash_equals( $expected, $given );
}
