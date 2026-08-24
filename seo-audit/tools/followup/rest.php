<?php
/**
 * Endpoints, the confirm/cancel landing, and the scheduled tick.
 *
 * @package AZWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Shared input handling
 * ---------------------------------------------------------------------- */

/**
 * Validate the parts every form here shares.
 *
 * `hp` is a honeypot input hidden with CSS — a human never sees it, so
 * anything in it came from something filling every field it found. `elapsed`
 * is how long the form was on screen; a genuine person cannot type their name
 * and email in under two seconds. Neither is a wall on its own, but together
 * they stop the volume traffic without putting a CAPTCHA in front of a lead.
 */
function azwc_fu_validate( WP_REST_Request $r ) {
	if ( '' !== trim( (string) $r->get_param( 'hp' ) ) ) {
		return new WP_Error( 'azwc_fu_bot', 'Something went wrong. Please try again.' );
	}
	if ( (int) $r->get_param( 'elapsed' ) < 2500 ) {
		return new WP_Error( 'azwc_fu_fast', 'Something went wrong. Please try again.' );
	}

	$name = sanitize_text_field( (string) $r->get_param( 'name' ) );
	$name = trim( preg_replace( '/\s+/', ' ', $name ) );
	if ( strlen( $name ) < 2 ) {
		return new WP_Error( 'azwc_fu_name', 'Please tell us what to call you.' );
	}

	$email = sanitize_email( (string) $r->get_param( 'email' ) );
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'azwc_fu_email', 'That email address does not look right.' );
	}

	return array(
		'name'  => mb_substr( $name, 0, 120 ),
		'email' => $email,
		'phone' => mb_substr( sanitize_text_field( (string) $r->get_param( 'phone' ) ), 0, 40 ),
	);
}

function azwc_fu_err( $error, $status = 400 ) {
	return new WP_REST_Response( array( 'error' => $error->get_error_message() ), $status );
}

/** Insert a lead and hand back the stored row. */
function azwc_fu_insert( $data ) {
	global $wpdb;

	$data = wp_parse_args(
		$data,
		array(
			'created_gmt' => gmdate( 'Y-m-d H:i:s' ),
			'token'       => azwc_fu_new_token(),
			'ip'          => azwc_fu_ip(),
			'status'      => 'pending',
		)
	);

	if ( false === $wpdb->insert( azwc_fu_table(), $data ) ) {
		return new WP_Error( 'azwc_fu_db', 'We could not save that. Please try again.' );
	}

	$table = azwc_fu_table();
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $wpdb->insert_id ) );
}


/* -------------------------------------------------------------------------
 * Routes
 * ---------------------------------------------------------------------- */

add_action(
	'rest_api_init',
	function () {
		$public = '__return_true';

		register_rest_route(
			'azwc/v1',
			'/report',
			array(
				'methods'             => 'POST',
				'permission_callback' => $public,
				'callback'            => 'azwc_fu_rest_report',
			)
		);

		register_rest_route(
			'azwc/v1',
			'/slots',
			array(
				'methods'             => 'GET',
				'permission_callback' => $public,
				'callback'            => 'azwc_fu_rest_slots',
			)
		);

		register_rest_route(
			'azwc/v1',
			'/booking',
			array(
				'methods'             => 'POST',
				'permission_callback' => $public,
				'callback'            => 'azwc_fu_rest_booking',
			)
		);
	}
);

/** Email the report. */
function azwc_fu_rest_report( WP_REST_Request $r ) {
	$fields = azwc_fu_validate( $r );
	if ( is_wp_error( $fields ) ) {
		return azwc_fu_err( $fields );
	}

	if ( ! azwc_fu_rate_ok( 'report', AZWC_FU_MAX_EMAILS_H ) ) {
		return azwc_fu_err(
			new WP_Error( 'azwc_fu_rate', 'That is a lot of reports from one place. Try again in an hour, or call us on 623-670-1611.' ),
			429
		);
	}

	$report = azwc_fu_report( (string) $r->get_param( 'domain' ) );
	if ( is_wp_error( $report ) ) {
		return azwc_fu_err( $report, 422 );
	}

	$row = azwc_fu_insert(
		array(
			'kind'   => 'report',
			'name'   => $fields['name'],
			'email'  => $fields['email'],
			'domain' => wp_parse_url( $report['url'], PHP_URL_HOST ),
			'score'  => isset( $report['site']['score']['overall'] ) ? (int) $report['site']['score']['overall'] : null,
			'status' => 'sent',
		)
	);
	if ( is_wp_error( $row ) ) {
		return azwc_fu_err( $row, 500 );
	}

	// PDF if the renderer is there, a self-contained HTML page if it is not.
	// A missing library downgrades the attachment; it never loses the lead.
	$slug   = sanitize_file_name( str_replace( '.', '-', $row->domain ) );
	$is_pdf = false;
	$pdf    = azwc_fu_report_pdf( $report, $row->name );

	if ( is_wp_error( $pdf ) ) {
		$file = azwc_fu_tempfile(
			'<!DOCTYPE html><html><head><meta charset="utf-8"><title>SEO report</title></head><body>'
				. azwc_fu_report_html( $report, $row->name ) . '</body></html>',
			'seo-report-' . $slug . '.html'
		);
	} else {
		$is_pdf = true;
		$file   = azwc_fu_tempfile( $pdf, 'seo-report-' . $slug . '.pdf' );
	}

	if ( is_wp_error( $file ) ) {
		return azwc_fu_err( $file, 500 );
	}

	$sent = azwc_fu_mail_report( $row, $report, array( $file ), $is_pdf );
	wp_delete_file( $file );

	if ( ! $sent ) {
		return azwc_fu_err(
			new WP_Error( 'azwc_fu_mail', 'We built your report but could not email it. Please call us on 623-670-1611 and we will send it over.' ),
			500
		);
	}

	azwc_fu_mail_internal( $row, 'report' );

	return new WP_REST_Response(
		array(
			'ok'      => true,
			'format'  => $is_pdf ? 'pdf' : 'html',
			'message' => sprintf( 'Sent. Check %s in a minute or two.', $row->email ),
		),
		200
	);
}

/** The calendar. */
function azwc_fu_rest_slots() {
	return new WP_REST_Response(
		array(
			'timezone' => AZWC_FU_TZ,
			'tzlabel'  => 'Arizona time (MST)',
			'duration' => AZWC_FU_CALL_MIN,
			'days'     => azwc_fu_availability(),
		),
		200
	);
}

/** Request a call. Holds the slot; confirmation is a separate click. */
function azwc_fu_rest_booking( WP_REST_Request $r ) {
	$fields = azwc_fu_validate( $r );
	if ( is_wp_error( $fields ) ) {
		return azwc_fu_err( $fields );
	}

	if ( ! azwc_fu_rate_ok( 'call', AZWC_FU_MAX_BOOKINGS_H ) ) {
		return azwc_fu_err(
			new WP_Error( 'azwc_fu_rate', 'You already have a booking request in. Give us a call on 623-670-1611 if you need another time.' ),
			429
		);
	}

	$slot = sanitize_text_field( (string) $r->get_param( 'slot' ) );
	$why  = '';
	if ( ! azwc_fu_slot_valid( $slot, $why ) ) {
		return azwc_fu_err( new WP_Error( 'azwc_fu_slot', $why ) );
	}
	if ( ! azwc_fu_slot_free( $slot ) ) {
		return azwc_fu_err(
			new WP_Error( 'azwc_fu_taken', 'Someone just took that time. Please pick another.' ),
			409
		);
	}

	$domain = (string) $r->get_param( 'domain' );
	if ( function_exists( 'azwc_audit_normalize' ) ) {
		$norm = azwc_audit_normalize( $domain );
		if ( ! is_wp_error( $norm ) ) {
			$domain = wp_parse_url( $norm, PHP_URL_HOST );
		}
	}

	$row = azwc_fu_insert(
		array(
			'kind'           => 'call',
			'name'           => $fields['name'],
			'email'          => $fields['email'],
			'phone'          => $fields['phone'],
			'domain'         => mb_substr( sanitize_text_field( $domain ), 0, 190 ),
			'slot_start_gmt' => $slot,
			'status'         => 'pending',
		)
	);
	if ( is_wp_error( $row ) ) {
		return azwc_fu_err( $row, 500 );
	}

	if ( ! azwc_fu_mail_confirm_request( $row ) ) {
		return azwc_fu_err(
			new WP_Error( 'azwc_fu_mail', 'We could not send the confirmation email. Please call us on 623-670-1611.' ),
			500
		);
	}

	azwc_fu_mail_internal( $row, 'requested' );

	return new WP_REST_Response(
		array(
			'ok'      => true,
			'slot'    => azwc_fu_pretty( $slot ),
			'message' => sprintf(
				'Almost done — we have emailed %s. Click the confirm link in it and the call is booked.',
				$row->email
			),
		),
		200
	);
}


/* -------------------------------------------------------------------------
 * Confirm / cancel landing
 *
 * Handled on a query parameter rather than a rewrite rule. mu-plugins have no
 * activation hook to flush rewrites on, and this site's page builder already
 * competes for template_include — a parameter needs neither and cannot be
 * broken by either.
 * ---------------------------------------------------------------------- */

add_action( 'init', 'azwc_fu_handle_action', 20 );

function azwc_fu_handle_action() {
	if ( is_admin() || wp_doing_ajax() || ! isset( $_GET['azwc_call'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}

	$action = sanitize_key( wp_unslash( $_GET['azwc_call'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! in_array( $action, array( 'confirm', 'cancel' ), true ) ) {
		return; // 'book' just deep-links the UI; the front end handles it.
	}

	$row = azwc_fu_find_by_token( isset( $_GET['token'] ) ? wp_unslash( $_GET['token'] ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification

	if ( ! $row ) {
		azwc_fu_page(
			'Link not recognised',
			'<p>That link has expired or was already used. If you were booking a call, '
			. 'start again from the audit page or just ring us on <b>623-670-1611</b>.</p>',
			home_url( '/free-seo-audit/' ),
			'Back to the SEO check'
		);
	}

	/**
	 * Nothing changes on a GET.
	 *
	 * Two reasons, and the second is the important one.
	 *
	 * 1. This host rewrites our response headers to `public, max-age=2678400`
	 *    no matter what nocache_headers() asks for, so a GET that acted would
	 *    be a state change sitting in a CDN for 31 days.
	 * 2. Corporate mail filters and link scanners fetch every URL in an email
	 *    before the recipient ever sees it. A GET that confirmed would be
	 *    auto-confirmed by the scanner — which defeats the entire purpose of
	 *    asking, since the whole point is knowing a person is expecting the
	 *    call before we spend an hour auditing their site.
	 *
	 * So the link renders a button, and the button POSTs. Scanners do not POST,
	 * and no cache stores one.
	 */
	$method    = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
	$submitted = ( 'POST' === $method )
		&& isset( $_POST['azwc_do'] ) // phpcs:ignore WordPress.Security.NonceVerification
		&& $action === sanitize_key( wp_unslash( $_POST['azwc_do'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

	if ( ! $submitted ) {
		azwc_fu_gate( $row, $action );
	}

	if ( 'cancel' === $action ) {
		azwc_fu_do_cancel( $row );
	}
	azwc_fu_do_confirm( $row );
}

/**
 * The "press the button" step.
 *
 * The token in the URL is the only secret involved, and it is already in the
 * hands of whoever opened the email, so this form carries no nonce — a nonce
 * would add nothing an attacker who already had the token could not satisfy,
 * and WordPress nonces are tied to a session these visitors do not have.
 */
function azwc_fu_gate( $row, $action ) {
	$slot = $row->slot_start_gmt ? azwc_fu_pretty( $row->slot_start_gmt ) : '';

	if ( 'confirm' === $action && 'confirmed' === $row->status ) {
		azwc_fu_page(
			'Already confirmed',
			'<p>You are booked for <b>' . esc_html( $slot ) . '</b>. Nothing else to do — we will call you.</p>'
		);
	}
	if ( 'cancel' === $action && 'cancelled' === $row->status ) {
		azwc_fu_page(
			'Already cancelled',
			'<p>That call is cancelled and the slot has gone back into the calendar.</p>',
			home_url( '/free-seo-audit/?azwc_call=book' ),
			'Pick a new time'
		);
	}

	$confirming = ( 'confirm' === $action );

	$body = '<p style="font-size:19px;margin-bottom:6px;"><b>' . esc_html( $slot ) . '</b></p>'
		. '<p style="color:#aab2bd;font-size:14px;">'
		. esc_html( $row->domain ) . ' &nbsp;·&nbsp; 30 minutes, by phone</p>'
		. ( $confirming
			? '<p>One tap and it is in the diary. We will audit your site by hand before we ring.</p>'
			: '<p>Cancelling frees this slot for somebody else. No hard feelings.</p>' )
		. '<form method="post" action="' . esc_url( azwc_fu_action_url( $action, $row->token ) ) . '">'
		. '<input type="hidden" name="azwc_do" value="' . esc_attr( $action ) . '">'
		. '<button class="btn" type="submit">'
		. ( $confirming ? 'Yes, confirm this call' : 'Yes, cancel it' )
		. '</button></form>';

	azwc_fu_page(
		$confirming ? 'Confirm your call' : 'Cancel this call?',
		$body,
		false
	);
}

function azwc_fu_do_confirm( $row ) {
	global $wpdb;

	if ( 'confirmed' === $row->status ) {
		azwc_fu_page(
			'Already confirmed',
			'<p>You are booked for <b>' . esc_html( azwc_fu_pretty( $row->slot_start_gmt ) ) . '</b>. '
			. 'Nothing else to do — we will call you.</p>'
		);
	}

	if ( 'cancelled' === $row->status ) {
		azwc_fu_page(
			'That call was cancelled',
			'<p>This booking was cancelled, so we have not held the time. '
			. 'You are very welcome to pick a new slot.</p>',
			home_url( '/free-seo-audit/?azwc_call=book' ),
			'Pick a new time'
		);
	}

	if ( strtotime( $row->slot_start_gmt . ' UTC' ) < time() ) {
		azwc_fu_page(
			'That time has passed',
			'<p>This link was for <b>' . esc_html( azwc_fu_pretty( $row->slot_start_gmt ) ) . '</b>, '
			. 'which has already gone by. Pick another and we will be there.</p>',
			home_url( '/free-seo-audit/?azwc_call=book' ),
			'Pick a new time'
		);
	}

	// Somebody else may have confirmed an overlapping slot while this one sat
	// unconfirmed in an inbox. Exclude this row from its own conflict check.
	$table = azwc_fu_table();
	$clash = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table}
			  WHERE kind = 'call' AND status = 'confirmed' AND id <> %d
			    AND slot_start_gmt > %s AND slot_start_gmt < %s",
			$row->id,
			gmdate( 'Y-m-d H:i:s', strtotime( $row->slot_start_gmt . ' UTC' ) - ( AZWC_FU_CALL_MIN * 60 ) ),
			gmdate( 'Y-m-d H:i:s', strtotime( $row->slot_start_gmt . ' UTC' ) + ( AZWC_FU_CALL_MIN * 60 ) )
		)
	);

	if ( $clash ) {
		azwc_fu_page(
			'That slot went while you were deciding',
			'<p>Someone confirmed <b>' . esc_html( azwc_fu_pretty( $row->slot_start_gmt ) ) . '</b> before you did. '
			. 'Sorry about that — pick another and it is yours.</p>',
			home_url( '/free-seo-audit/?azwc_call=book' ),
			'Pick a new time'
		);
	}

	$wpdb->update(
		$table,
		array(
			'status'        => 'confirmed',
			'confirmed_gmt' => gmdate( 'Y-m-d H:i:s' ),
		),
		array( 'id' => $row->id )
	);
	$row->status = 'confirmed';

	$ics  = azwc_fu_tempfile( azwc_fu_ics( $row ), 'seo-call.ics' );
	$atts = is_wp_error( $ics ) ? array() : array( $ics );

	azwc_fu_mail_confirmed( $row, $atts );
	if ( ! is_wp_error( $ics ) ) {
		wp_delete_file( $ics );
	}
	azwc_fu_mail_internal( $row, 'confirmed' );

	azwc_fu_page(
		'You are booked',
		'<p style="font-size:19px;"><b>' . esc_html( azwc_fu_pretty( $row->slot_start_gmt ) ) . '</b></p>'
		. '<p>We have emailed you a calendar invite. Before we ring, we will go through '
		. '<b>' . esc_html( $row->domain ) . '</b> by hand — the parts an automated crawler cannot judge — '
		. 'so the half hour is spent on what to do about it.</p>'
		. '<p style="color:#9aa3ad;font-size:14px;">Need to change it? The cancel link is in your email.</p>'
	);
}

function azwc_fu_do_cancel( $row ) {
	global $wpdb;

	if ( 'cancelled' !== $row->status ) {
		$wpdb->update( azwc_fu_table(), array( 'status' => 'cancelled' ), array( 'id' => $row->id ) );
		$row->status = 'cancelled';
		azwc_fu_mail_internal( $row, 'cancelled' );
	}

	azwc_fu_page(
		'Call cancelled',
		'<p>That is cancelled and the slot is back in the calendar — thanks for telling us '
		. 'rather than leaving it.</p><p>Book again whenever suits you.</p>',
		home_url( '/free-seo-audit/?azwc_call=book' ),
		'Pick a new time'
	);
}

/**
 * A small standalone page for the confirm/cancel landings.
 *
 * Rendered directly instead of through the theme on purpose: this runs on
 * `init`, long before the template stack, and the one thing that must not
 * happen is a themed page failing to render after the booking has already
 * been written. Always exits.
 */
function azwc_fu_page( $title, $body, $link = '', $link_label = '' ) {
	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );

	// false suppresses the button entirely — used where the body supplies its
	// own form and a second call-to-action would compete with it.
	if ( false === $link ) {
		$button = '';
	} elseif ( $link ) {
		$button = '<a class="btn" href="' . esc_url( $link ) . '">' . esc_html( $link_label ) . '</a>';
	} else {
		$button = '<a class="btn" href="' . esc_url( home_url( '/' ) ) . '">Back to azwebcorp.com</a>';
	}

	echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width,initial-scale=1">'
		. '<meta name="robots" content="noindex">'
		. '<title>' . esc_html( $title ) . ' — AZ Web Corp</title><style>'
		. '*{box-sizing:border-box}body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
		. 'padding:24px;background:linear-gradient(135deg,#050608,#111823 60%,#30240a);'
		. 'font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#d6dce4}'
		. '.card{max-width:560px;width:100%;background:rgba(255,255,255,.04);border:1px solid rgba(230,184,77,.24);'
		. 'border-radius:16px;padding:38px 34px}'
		. '.mark{font-size:12px;letter-spacing:.19em;font-weight:800;color:#e6b84d;margin-bottom:18px}'
		. 'h1{margin:0 0 14px;font-size:27px;line-height:1.22;color:#fff}'
		. 'p{margin:0 0 13px;font-size:15.5px;line-height:1.62}'
		. '.btn{display:inline-block;margin-top:14px;padding:13px 24px;border-radius:10px;background:#e6b84d;'
		. 'color:#161208;font-weight:800;text-decoration:none;border:0;font-size:15.5px;font-family:inherit;cursor:pointer}'
		. '.btn:hover{background:#f5d47d}'
		. 'form{margin:0}'
		. '</style></head><body><div class="card"><div class="mark">AZ WEB CORP</div>'
		. '<h1>' . esc_html( $title ) . '</h1>' . $body . $button
		. '</div></body></html>';

	exit;
}


/* -------------------------------------------------------------------------
 * Scheduled work
 * ---------------------------------------------------------------------- */

add_filter(
	'cron_schedules', // phpcs:ignore WordPress.WP.CronInterval
	function ( $s ) {
		$s['azwc_fu_5min'] = array(
			'interval' => 300,
			'display'  => 'Every 5 minutes (AZWC follow-up)',
		);
		return $s;
	}
);

add_action( 'init', 'azwc_fu_schedule', 30 );

function azwc_fu_schedule() {
	if ( ! wp_next_scheduled( 'azwc_fu_tick' ) ) {
		wp_schedule_event( time() + 60, 'azwc_fu_5min', 'azwc_fu_tick' );
	}
}

add_action( 'azwc_fu_tick', 'azwc_fu_tick' );

/**
 * Reminders and housekeeping.
 *
 * The reminder window is deliberately wide (anything starting inside the next
 * 75 minutes). There is no system crontab on this host, so WP-Cron only fires
 * when somebody visits the site; a window narrow enough to be exact would drop
 * reminders during a quiet hour. Late by a few minutes beats never.
 */
function azwc_fu_tick() {
	global $wpdb;
	$table = azwc_fu_table();
	$now   = time();

	$due = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table}
			  WHERE kind = 'call' AND status = 'confirmed' AND reminded_gmt IS NULL
			    AND slot_start_gmt > %s AND slot_start_gmt <= %s",
			gmdate( 'Y-m-d H:i:s', $now ),
			gmdate( 'Y-m-d H:i:s', $now + ( ( AZWC_FU_REMIND_MIN + 15 ) * 60 ) )
		)
	);

	foreach ( $due as $row ) {
		// Stamped before sending, not after: a mail failure must not put this
		// row back in the queue to be retried every five minutes forever.
		$wpdb->update( $table, array( 'reminded_gmt' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => $row->id ) );
		azwc_fu_mail_reminder( $row );
	}

	// Release slots nobody confirmed.
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$table} SET status = 'expired'
			  WHERE kind = 'call' AND status = 'pending' AND created_gmt < %s",
			gmdate( 'Y-m-d H:i:s', $now - ( AZWC_FU_HOLD_H * HOUR_IN_SECONDS ) )
		)
	);
}

/**
 * Catch-up path.
 *
 * WP-Cron on this host needs a visitor to fire, and a reminder that arrives
 * after the call is worthless. Any request at all will run the tick if it is
 * overdue; the transient keeps that to one check per five minutes.
 */
add_action( 'init', 'azwc_fu_catch_up', 40 );

function azwc_fu_catch_up() {
	if ( wp_doing_cron() || get_transient( 'azwc_fu_ticked' ) ) {
		return;
	}
	set_transient( 'azwc_fu_ticked', 1, 300 );
	azwc_fu_tick();
}
