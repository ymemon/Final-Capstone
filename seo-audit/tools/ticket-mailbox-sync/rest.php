<?php
/**
 * Real-time intake: log the message, find or open a ticket, notify.
 *
 * Parsing here mirrors client-watch/rest.php on purpose (same two inbound
 * formats, same "secret in the URL path" reasoning) — this is a separate
 * WordPress plugin from Client Watch, matching how the two real plugins are
 * apparently separate too, so it is not sharing a file with it.
 *
 * @package AZWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'azwc/v1',
			'/webhook/ticket-mailbox/(?P<secret>[A-Za-z0-9]{32,80})',
			array(
				'methods'             => 'POST',
				'permission_callback' => 'azwc_tms_webhook_auth',
				'callback'            => 'azwc_tms_webhook_receive',
				'args'                => array(
					'secret' => array( 'required' => true ),
				),
			)
		);
	}
);

/**
 * define( 'AZWC_TMS_WEBHOOK_SECRET', '...' ); in wp-config.php.
 * Deliberately a different constant from Client Watch's secret — the two
 * plugins are independent and one being compromised shouldn't hand over
 * the other's endpoint too.
 */
function azwc_tms_webhook_auth( WP_REST_Request $r ) {
	if ( ! defined( 'AZWC_TMS_WEBHOOK_SECRET' ) || '' === AZWC_TMS_WEBHOOK_SECRET ) {
		return false;
	}
	return hash_equals( AZWC_TMS_WEBHOOK_SECRET, (string) $r->get_param( 'secret' ) );
}

function azwc_tms_webhook_receive( WP_REST_Request $r ) {
	$parsed = azwc_tms_parse_inbound( $r );
	if ( is_wp_error( $parsed ) ) {
		return new WP_REST_Response( array( 'error' => $parsed->get_error_message() ), 400 );
	}

	$ticket    = azwc_tms_find_or_open_ticket( $parsed );
	$is_new    = $ticket['is_new'];
	$ticket_id = $ticket['id'];

	global $wpdb;
	$wpdb->insert(
		azwc_tms_messages_table(),
		array(
			'ticket_id'   => $ticket_id,
			'created_gmt' => gmdate( 'Y-m-d H:i:s' ),
			'from_email'  => $parsed['from_email'],
			'subject'     => $parsed['subject'],
			'excerpt'     => $parsed['excerpt'],
			'source'      => $parsed['source'],
			'ip'          => azwc_tms_ip(),
		)
	);

	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . azwc_tms_tickets_table() . ' WHERE id = %d', $ticket_id ) );

	azwc_tms_notify_team( $row, $parsed, $is_new );
	if ( $is_new ) {
		azwc_tms_acknowledge_client( $row );
	}
	do_action( 'azwc_tms_message_received', $row, $parsed, $is_new );

	return new WP_REST_Response(
		array(
			'ok'     => true,
			'ticket' => azwc_tms_format_number( $ticket_id ),
			'new'    => $is_new,
		),
		200
	);
}

function azwc_tms_parse_inbound( WP_REST_Request $r ) {
	if ( null !== $r->get_param( 'body-plain' ) || null !== $r->get_param( 'sender' ) ) {
		return azwc_tms_from_mailgun( $r );
	}
	if ( null !== $r->get_param( 'envelope' ) || ( null !== $r->get_param( 'from' ) && null !== $r->get_param( 'text' ) ) ) {
		return azwc_tms_from_sendgrid( $r );
	}
	if ( null !== $r->get_param( 'from' ) ) {
		return azwc_tms_from_generic( $r );
	}
	return new WP_Error( 'azwc_tms_unrecognised', 'Could not recognise this webhook payload as an email.' );
}

function azwc_tms_extract_address( $raw ) {
	if ( preg_match( '/<([^>]+)>/', (string) $raw, $m ) ) {
		return sanitize_email( $m[1] );
	}
	return sanitize_email( trim( (string) $raw ) );
}

function azwc_tms_extract_name( $raw, $fallback_email ) {
	$raw = (string) $raw;
	if ( preg_match( '/^\s*"?([^"<]+?)"?\s*<[^>]+>\s*$/', $raw, $m ) ) {
		return sanitize_text_field( trim( $m[1] ) );
	}
	return sanitize_text_field( explode( '@', $fallback_email )[0] );
}

function azwc_tms_from_mailgun( WP_REST_Request $r ) {
	$from  = (string) ( $r->get_param( 'from' ) ?: $r->get_param( 'sender' ) );
	$email = azwc_tms_extract_address( $from ) ?: sanitize_email( (string) $r->get_param( 'sender' ) );

	if ( ! is_email( $email ) ) {
		return new WP_Error( 'azwc_tms_bad_sender', 'Mailgun payload had no valid sender address.' );
	}

	$body = (string) ( $r->get_param( 'stripped-text' ) ?: $r->get_param( 'body-plain' ) ?: '' );

	return array(
		'source'     => 'mailgun',
		'from_email' => $email,
		'from_name'  => azwc_tms_extract_name( $from, $email ),
		'subject'    => sanitize_text_field( (string) $r->get_param( 'subject' ) ),
		'excerpt'    => mb_substr( wp_strip_all_tags( $body ), 0, 500 ),
	);
}

function azwc_tms_from_sendgrid( WP_REST_Request $r ) {
	$from  = (string) $r->get_param( 'from' );
	$email = azwc_tms_extract_address( $from );

	if ( ! is_email( $email ) ) {
		return new WP_Error( 'azwc_tms_bad_sender', 'SendGrid payload had no valid sender address.' );
	}

	return array(
		'source'     => 'sendgrid',
		'from_email' => $email,
		'from_name'  => azwc_tms_extract_name( $from, $email ),
		'subject'    => sanitize_text_field( (string) $r->get_param( 'subject' ) ),
		'excerpt'    => mb_substr( wp_strip_all_tags( (string) $r->get_param( 'text' ) ), 0, 500 ),
	);
}

function azwc_tms_from_generic( WP_REST_Request $r ) {
	$from  = (string) $r->get_param( 'from' );
	$email = is_email( $from ) ? sanitize_email( $from ) : azwc_tms_extract_address( $from );

	if ( ! is_email( $email ) ) {
		return new WP_Error( 'azwc_tms_bad_sender', 'No valid "from" address in payload.' );
	}

	return array(
		'source'     => 'generic',
		'from_email' => $email,
		'from_name'  => azwc_tms_extract_name( (string) $r->get_param( 'name' ) ?: $from, $email ),
		'subject'    => sanitize_text_field( (string) $r->get_param( 'subject' ) ),
		'excerpt'    => mb_substr( wp_strip_all_tags( (string) $r->get_param( 'body' ) ), 0, 500 ),
	);
}

/**
 * Match a reply to its ticket, or open a new one.
 *
 * Two signals, in order: an explicit "[Ticket #AZW-nnnnn]" tag from a
 * previous outbound acknowledgment/nudge (most reliable — survives a
 * changed subject), then a loose subject match against the same sender
 * within the last 45 days (catches a client who deleted the tag but kept
 * "Re:"). Anything else opens a new ticket — filing wrong is worse than
 * filing new.
 */
function azwc_tms_find_or_open_ticket( $parsed ) {
	global $wpdb;
	$table = azwc_tms_tickets_table();

	if ( preg_match( '/\[Ticket #AZW-(\d+)\]/i', $parsed['subject'], $m ) ) {
		$ticket_id = (int) $m[1];
		$row       = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND client_email = %s", $ticket_id, $parsed['from_email'] )
		);
		if ( $row ) {
			azwc_tms_touch_ticket( $row );
			return array( 'id' => (int) $row->id, 'is_new' => false );
		}
	}

	$key = azwc_tms_subject_key( $parsed['subject'] );
	if ( '' !== $key ) {
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				  WHERE client_email = %s AND subject_key = %s AND created_gmt > %s
				  ORDER BY created_gmt DESC LIMIT 1",
				$parsed['from_email'],
				$key,
				gmdate( 'Y-m-d H:i:s', time() - ( 45 * DAY_IN_SECONDS ) )
			)
		);
		if ( $row ) {
			azwc_tms_touch_ticket( $row );
			return array( 'id' => (int) $row->id, 'is_new' => false );
		}
	}

	$wpdb->insert(
		$table,
		array(
			'created_gmt'     => gmdate( 'Y-m-d H:i:s' ),
			'subject_key'     => $key,
			'client_email'    => $parsed['from_email'],
			'client_name'     => $parsed['from_name'],
			'status'          => 'open',
			'last_client_gmt' => gmdate( 'Y-m-d H:i:s' ),
		)
	);

	return array( 'id' => (int) $wpdb->insert_id, 'is_new' => true );
}

/** Reopens a resolved/closed ticket on new client contact, resets the nudge clock. */
function azwc_tms_touch_ticket( $row ) {
	global $wpdb;
	$wpdb->update(
		azwc_tms_tickets_table(),
		array(
			'status'          => in_array( $row->status, array( 'resolved', 'closed' ), true ) ? 'open' : $row->status,
			'last_client_gmt' => gmdate( 'Y-m-d H:i:s' ),
			'nudged_gmt'      => null,
			'nudge_count'     => 0,
		),
		array( 'id' => $row->id )
	);
}

function azwc_tms_html_wrap( $body ) {
	return '<div style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#1c2129;">'
		. $body . '</div>';
}

function azwc_tms_send( $to, $subject, $body ) {
	$type = function () {
		return 'text/html';
	};
	add_filter( 'wp_mail_content_type', $type );
	$sent = wp_mail( $to, $subject, azwc_tms_html_wrap( $body ) );
	remove_filter( 'wp_mail_content_type', $type );
	return $sent;
}

/** Team notification — fires on every message, new ticket or reply alike. */
function azwc_tms_notify_team( $ticket, $parsed, $is_new ) {
	$number = azwc_tms_format_number( $ticket->id );
	$title  = $is_new ? "New ticket {$number}" : "Reply on {$number}";

	$body = '<p style="font-size:17px;font-weight:800;margin:0 0 10px;">' . esc_html( $title ) . '</p>'
		. '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:10px 0;">'
		. '<tr><td style="padding:4px 14px 4px 0;color:#6b7480;font-size:13px;">From</td>'
		. '<td style="padding:4px 0;font-size:14px;font-weight:600;">' . esc_html( $parsed['from_name'] ) . ' &lt;' . esc_html( $parsed['from_email'] ) . '&gt;</td></tr>'
		. '<tr><td style="padding:4px 14px 4px 0;color:#6b7480;font-size:13px;">Subject</td>'
		. '<td style="padding:4px 0;font-size:14px;font-weight:600;">' . esc_html( $parsed['subject'] ) . '</td></tr>'
		. '</table>'
		. ( $parsed['excerpt'] ? '<p style="background:#f6f7f9;border-left:3px solid #e6b84d;padding:10px 14px;font-size:13.5px;white-space:pre-wrap;">' . esc_html( $parsed['excerpt'] ) . '</p>' : '' );

	azwc_tms_send( azwc_tms_notify_email(), "[{$number}] " . ( $is_new ? 'New ticket' : 'Reply' ) . ' — ' . $parsed['from_email'], $body );
}

/**
 * Auto-acknowledgment to the client, sent only on a brand-new ticket, not
 * on every reply (an ack on every message would read as a form-letter bot).
 * Carries the ticket tag so their next reply threads correctly.
 */
function azwc_tms_acknowledge_client( $ticket ) {
	$number = azwc_tms_format_number( $ticket->id );
	$first  = trim( strtok( trim( $ticket->client_name ), ' ' ) );

	$body = '<p>' . ( $first ? 'Hi ' . esc_html( $first ) . ',' : 'Hi,' ) . '</p>'
		. '<p>Thanks for reaching out — this is an automatic confirmation that your message arrived. '
		. 'Your reference number is <b>' . esc_html( $number ) . '</b>. A person will follow up shortly; '
		. 'replying to this email keeps it attached to the same ticket.</p>'
		. '<p style="font-size:13px;color:#6b7480;">AZ Web Corp &middot; 480-818-5761 &middot; info@azwebcorp.com</p>';

	azwc_tms_send( $ticket->client_email, "We got your message [Ticket #{$number}]", $body );
}
