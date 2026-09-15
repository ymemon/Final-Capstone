<?php
/**
 * The real-time intake endpoint.
 *
 * Mailgun's "Store and notify" routes and SendGrid's Inbound Parse both POST
 * a received message as multipart/form-data (not JSON) to a plain URL —
 * neither lets you attach a custom Authorization header from their own
 * dashboard without extra work, so the shared secret here travels in the
 * URL path instead. Treat that URL itself as the credential: anyone who has
 * it can post fake "email received" events.
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
			'/webhook/client-watch/(?P<secret>[A-Za-z0-9]{32,80})',
			array(
				'methods'             => 'POST',
				'permission_callback' => 'azwc_cw_webhook_auth',
				'callback'            => 'azwc_cw_webhook_receive',
				'args'                => array(
					'secret' => array( 'required' => true ),
				),
			)
		);
	}
);

/**
 * The secret lives in wp-config, never in the repo:
 *   define( 'AZWC_CW_WEBHOOK_SECRET', '...64 hex chars...' );
 * hash_equals keeps the comparison constant-time.
 */
function azwc_cw_webhook_auth( WP_REST_Request $r ) {
	if ( ! defined( 'AZWC_CW_WEBHOOK_SECRET' ) || '' === AZWC_CW_WEBHOOK_SECRET ) {
		return false;
	}
	return hash_equals( AZWC_CW_WEBHOOK_SECRET, (string) $r->get_param( 'secret' ) );
}

/**
 * Accept a forwarded message from Mailgun, SendGrid, or a plain JSON poster,
 * log it immediately, and notify the team — no cron, no polling delay.
 */
function azwc_cw_webhook_receive( WP_REST_Request $r ) {
	$parsed = azwc_cw_parse_inbound( $r );
	if ( is_wp_error( $parsed ) ) {
		return new WP_REST_Response( array( 'error' => $parsed->get_error_message() ), 400 );
	}

	global $wpdb;
	$inserted = $wpdb->insert(
		azwc_cw_table(),
		array(
			'created_gmt' => gmdate( 'Y-m-d H:i:s' ),
			'from_email'  => $parsed['from_email'],
			'from_name'   => $parsed['from_name'],
			'subject'     => $parsed['subject'],
			'excerpt'     => $parsed['excerpt'],
			'source'      => $parsed['source'],
			'status'      => 'new',
			'ip'          => azwc_cw_ip(),
		)
	);

	if ( false === $inserted ) {
		return new WP_REST_Response( array( 'error' => 'Could not store the message.' ), 500 );
	}

	$id  = $wpdb->insert_id;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . azwc_cw_table() . " WHERE id = %d", $id ) );

	azwc_cw_notify_team( $row );
	do_action( 'azwc_cw_email_received', $row );

	return new WP_REST_Response( array( 'ok' => true, 'id' => $id ), 200 );
}

/**
 * Mailgun and SendGrid both post multipart/form-data, which WP_REST_Request
 * exposes through get_param() the same as JSON body params — so one accessor
 * covers all three shapes; only the field names differ per source.
 */
function azwc_cw_parse_inbound( WP_REST_Request $r ) {
	// Mailgun: 'sender' (bare address) + 'from' (display form), 'body-plain'.
	if ( null !== $r->get_param( 'body-plain' ) || null !== $r->get_param( 'sender' ) ) {
		return azwc_cw_from_mailgun( $r );
	}

	// SendGrid Inbound Parse: 'from', 'text', 'envelope'.
	if ( null !== $r->get_param( 'envelope' ) || ( null !== $r->get_param( 'from' ) && null !== $r->get_param( 'text' ) ) ) {
		return azwc_cw_from_sendgrid( $r );
	}

	// Fallback: plain JSON { from, subject, body }.
	if ( null !== $r->get_param( 'from' ) ) {
		return azwc_cw_from_generic( $r );
	}

	return new WP_Error( 'azwc_cw_unrecognised', 'Could not recognise this webhook payload as an email.' );
}

/** Pull a bare address out of "Name <addr@host>" or a plain address. */
function azwc_cw_extract_address( $raw ) {
	if ( preg_match( '/<([^>]+)>/', (string) $raw, $m ) ) {
		return sanitize_email( $m[1] );
	}
	return sanitize_email( trim( (string) $raw ) );
}

function azwc_cw_extract_name( $raw, $fallback_email ) {
	$raw = (string) $raw;
	if ( preg_match( '/^\s*"?([^"<]+?)"?\s*<[^>]+>\s*$/', $raw, $m ) ) {
		return sanitize_text_field( trim( $m[1] ) );
	}
	return sanitize_text_field( explode( '@', $fallback_email )[0] );
}

function azwc_cw_from_mailgun( WP_REST_Request $r ) {
	$from  = (string) ( $r->get_param( 'from' ) ?: $r->get_param( 'sender' ) );
	$email = azwc_cw_extract_address( $from ) ?: sanitize_email( (string) $r->get_param( 'sender' ) );

	if ( ! is_email( $email ) ) {
		return new WP_Error( 'azwc_cw_bad_sender', 'Mailgun payload had no valid sender address.' );
	}

	$body = (string) ( $r->get_param( 'stripped-text' ) ?: $r->get_param( 'body-plain' ) ?: '' );

	return array(
		'source'     => 'mailgun',
		'from_email' => $email,
		'from_name'  => azwc_cw_extract_name( $from, $email ),
		'subject'    => sanitize_text_field( (string) $r->get_param( 'subject' ) ),
		'excerpt'    => mb_substr( wp_strip_all_tags( $body ), 0, 500 ),
	);
}

function azwc_cw_from_sendgrid( WP_REST_Request $r ) {
	$from  = (string) $r->get_param( 'from' );
	$email = azwc_cw_extract_address( $from );

	if ( ! is_email( $email ) ) {
		return new WP_Error( 'azwc_cw_bad_sender', 'SendGrid payload had no valid sender address.' );
	}

	return array(
		'source'     => 'sendgrid',
		'from_email' => $email,
		'from_name'  => azwc_cw_extract_name( $from, $email ),
		'subject'    => sanitize_text_field( (string) $r->get_param( 'subject' ) ),
		'excerpt'    => mb_substr( wp_strip_all_tags( (string) $r->get_param( 'text' ) ), 0, 500 ),
	);
}

function azwc_cw_from_generic( WP_REST_Request $r ) {
	$from  = (string) $r->get_param( 'from' );
	$email = is_email( $from ) ? sanitize_email( $from ) : azwc_cw_extract_address( $from );

	if ( ! is_email( $email ) ) {
		return new WP_Error( 'azwc_cw_bad_sender', 'No valid "from" address in payload.' );
	}

	return array(
		'source'     => 'generic',
		'from_email' => $email,
		'from_name'  => azwc_cw_extract_name( (string) $r->get_param( 'name' ) ?: $from, $email ),
		'subject'    => sanitize_text_field( (string) $r->get_param( 'subject' ) ),
		'excerpt'    => mb_substr( wp_strip_all_tags( (string) $r->get_param( 'body' ) ), 0, 500 ),
	);
}

/** Fire the "a client just emailed" notice the moment it's logged. */
function azwc_cw_notify_team( $row ) {
	$subject = sprintf( '[Client Watch] New email from %s', $row->from_email );

	$body = '<p style="font-size:17px;font-weight:800;margin:0 0 10px;">Email received just now</p>'
		. '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:10px 0;">'
		. '<tr><td style="padding:4px 14px 4px 0;color:#6b7480;font-size:13px;">From</td>'
		. '<td style="padding:4px 0;font-size:14px;font-weight:600;">' . esc_html( $row->from_name ) . ' &lt;' . esc_html( $row->from_email ) . '&gt;</td></tr>'
		. '<tr><td style="padding:4px 14px 4px 0;color:#6b7480;font-size:13px;">Subject</td>'
		. '<td style="padding:4px 0;font-size:14px;font-weight:600;">' . esc_html( $row->subject ) . '</td></tr>'
		. '</table>'
		. ( $row->excerpt ? '<p style="background:#f6f7f9;border-left:3px solid #e6b84d;padding:10px 14px;font-size:13.5px;white-space:pre-wrap;">' . esc_html( $row->excerpt ) . '</p>' : '' )
		. '<p style="font-size:12px;color:#9aa3ad;">Client Watch #' . (int) $row->id . ' &middot; via ' . esc_html( $row->source ) . '</p>';

	$type = function () {
		return 'text/html';
	};
	add_filter( 'wp_mail_content_type', $type );
	wp_mail( azwc_cw_notify_email(), $subject, $body );
	remove_filter( 'wp_mail_content_type', $type );

	global $wpdb;
	$wpdb->update(
		azwc_cw_table(),
		array(
			'status'       => 'notified',
			'notified_gmt' => gmdate( 'Y-m-d H:i:s' ),
		),
		array( 'id' => $row->id )
	);
}
