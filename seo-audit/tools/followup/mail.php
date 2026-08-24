<?php
/**
 * Every message the follow-up flow sends.
 *
 * @package AZWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Send one HTML mail.
 *
 * The content-type filter is added and removed around the single send rather
 * than left on: this file is loaded on every request, and a permanently HTML
 * wp_mail would silently reformat mail from every other plugin on the site.
 */
function azwc_fu_send( $to, $subject, $html, $attachments = array(), $reply_to = '' ) {
	$type = function () {
		return 'text/html';
	};
	add_filter( 'wp_mail_content_type', $type );

	$headers = array();
	if ( $reply_to && is_email( $reply_to ) ) {
		$headers[] = 'Reply-To: ' . $reply_to;
	}

	$sent = wp_mail( $to, $subject, azwc_fu_wrap( $subject, $html ), $headers, $attachments );

	remove_filter( 'wp_mail_content_type', $type );

	return $sent;
}

/** Shared shell. Tables and inline styles, because email clients demand it. */
function azwc_fu_wrap( $title, $body ) {
	$year = gmdate( 'Y' );

	return '<!DOCTYPE html><html><head><meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width,initial-scale=1">'
		. '<title>' . esc_html( $title ) . '</title></head>'
		. '<body style="margin:0;padding:0;background:#eef0f3;">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef0f3;padding:24px 12px;">'
		. '<tr><td align="center">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">'

		. '<tr><td style="background:#0d1117;padding:22px 28px;">'
		. '<div style="font-size:15px;letter-spacing:.18em;font-weight:800;color:#e6b84d;">AZ WEB CORP</div>'
		. '<div style="font-size:11px;color:#9aa3ad;margin-top:2px;">Web Development, SEO &amp; Digital Marketing</div>'
		. '</td></tr>'

		. '<tr><td style="padding:28px;color:#1c2129;font-size:15px;line-height:1.62;">' . $body . '</td></tr>'

		. '<tr><td style="background:#f6f7f9;padding:18px 28px;color:#6b7480;font-size:12px;line-height:1.6;border-top:1px solid #e2e5ea;">'
		. 'AZ Web Corp &nbsp;·&nbsp; <a href="tel:+14808185761" style="color:#9b711b;text-decoration:none;">480-818-5761</a>'
		. ' &nbsp;·&nbsp; <a href="mailto:info@azwebcorp.com" style="color:#9b711b;text-decoration:none;">info@azwebcorp.com</a><br>'
		. 'Monday–Thursday, 9:00am–6:00pm PST &nbsp;·&nbsp; <a href="https://azwebcorp.com" style="color:#9b711b;text-decoration:none;">azwebcorp.com</a><br>'
		. '<span style="color:#9aa3ad;">&copy; ' . $year . ' AZ Web Corp</span>'
		. '</td></tr>'

		. '</table></td></tr></table></body></html>';
}

/** Big gold action button. */
function azwc_fu_button( $url, $label ) {
	return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0;">'
		. '<tr><td style="background:#e6b84d;border-radius:9px;">'
		. '<a href="' . esc_url( $url ) . '" style="display:inline-block;padding:13px 26px;font-size:15px;'
		. 'font-weight:800;color:#161208;text-decoration:none;">' . esc_html( $label ) . '</a>'
		. '</td></tr></table>';
}

function azwc_fu_greeting( $name ) {
	$first = trim( strtok( trim( $name ), ' ' ) );
	return $first ? 'Hi ' . esc_html( $first ) . ',' : 'Hi,';
}


/* -------------------------------------------------------------------------
 * Report delivery
 * ---------------------------------------------------------------------- */

function azwc_fu_mail_report( $row, $report, $attachments, $is_pdf ) {
	$host  = wp_parse_url( $report['url'], PHP_URL_HOST );
	$score = isset( $report['site']['score']['overall'] ) ? $report['site']['score']['overall'] : null;

	$fails = 0;
	$warns = 0;
	foreach ( $report['site']['checks'] as $c ) {
		if ( 'fail' === $c['status'] ) {
			++$fails;
		} elseif ( 'warn' === $c['status'] ) {
			++$warns;
		}
	}

	$summary = sprintf(
		'We checked %d things on <b>%s</b>. %s',
		count( $report['site']['checks'] ),
		esc_html( $host ),
		$fails || $warns
			? sprintf(
				'%d need%s fixing and %d %s worth improving.',
				$fails,
				1 === $fails ? 's' : '',
				$warns,
				1 === $warns ? 'is' : 'are'
			)
			: 'Everything passed.'
	);

	$body = '<p>' . azwc_fu_greeting( $row->name ) . '</p>'
		. '<p>Your SEO report is attached'
		. ( $is_pdf ? ' as a PDF' : ' (as a web page — open it in any browser and print to PDF if you need one)' )
		. '.</p>'
		. '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" '
		. 'style="background:#f6f7f9;border:1px solid #e2e5ea;border-radius:9px;margin:18px 0;">'
		. '<tr><td style="padding:16px 18px;">'
		. ( null === $score
			? ''
			: '<div style="font-size:30px;font-weight:800;line-height:1;color:#1c2129;">' . (int) $score
				. '<span style="font-size:14px;color:#6b7480;font-weight:600;">/100</span></div>' )
		. '<div style="font-size:14px;color:#444c57;margin-top:6px;">' . $summary . '</div>'
		. '</td></tr></table>'
		. '<p>Each finding in the report has a plain-English explanation underneath it, so you can hand it '
		. 'to whoever looks after your site without translating anything first.</p>'
		. '<p style="margin-top:22px;"><b>One thing worth saying plainly:</b> this report is automated. '
		. 'It reads what your page sends to a browser, which catches a great deal — but it cannot tell you '
		. 'whether you are targeting the right search terms, or why a competitor outranks you on the ones '
		. 'that matter. That part takes a person.</p>'
		. '<p>If you would like us to do that, we will run a hand audit and walk you through it on a call. '
		. 'It is free and it takes half an hour.</p>'
		. azwc_fu_button( home_url( '/free-seo-audit/?azwc_call=book' ), 'Book a free 30-minute call' )
		. '<p style="font-size:13px;color:#6b7480;">Prefer to talk now? Call <b>480-818-5761</b>.</p>';

	return azwc_fu_send(
		$row->email,
		sprintf( 'Your SEO report for %s', $host ),
		$body,
		$attachments,
		azwc_fu_notify_email()
	);
}


/* -------------------------------------------------------------------------
 * Booking
 * ---------------------------------------------------------------------- */

/**
 * Step one of the double opt-in.
 *
 * Nothing is on our calendar until this link is clicked. That is the entire
 * point: the hand audit before the call is real work, and doing it for an
 * address somebody mistyped — or never owned — is the cost we are avoiding.
 */
function azwc_fu_mail_confirm_request( $row ) {
	$body = '<p>' . azwc_fu_greeting( $row->name ) . '</p>'
		. '<p>You asked us to call you about <b>' . esc_html( $row->domain ) . '</b>. '
		. 'One click and it is booked:</p>'
		. '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" '
		. 'style="background:#faf6ec;border:1px solid #e6d3a3;border-radius:9px;margin:16px 0;">'
		. '<tr><td style="padding:15px 18px;font-size:15px;color:#4a3d18;">'
		. '<b>' . esc_html( azwc_fu_pretty( $row->slot_start_gmt ) ) . '</b><br>'
		. '<span style="font-size:13px;color:#6b6046;">30 minutes, by phone — we call you.</span>'
		. '</td></tr></table>'
		. azwc_fu_button( azwc_fu_action_url( 'confirm', $row->token ), 'Confirm this call' )
		. '<p style="font-size:13.5px;color:#6b7480;">We ask you to confirm because we audit your site by hand '
		. 'before we ring — going through it properly rather than reading you the automated report back. '
		. 'That only makes sense if we know someone is expecting the call.</p>'
		. '<p style="font-size:13.5px;color:#6b7480;">This slot is held for '
		. (int) AZWC_FU_HOLD_H . ' hours. If you did not request this, ignore this email and nothing happens.</p>';

	return azwc_fu_send(
		$row->email,
		'Please confirm your free SEO call',
		$body,
		array(),
		azwc_fu_notify_email()
	);
}

/** Step two: they clicked. Now it is real, so send the calendar invite. */
function azwc_fu_mail_confirmed( $row, $attachments = array() ) {
	$body = '<p>' . azwc_fu_greeting( $row->name ) . '</p>'
		. '<p>You are booked. We will call you on the number below.</p>'
		. '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" '
		. 'style="background:#0d1117;border-radius:9px;margin:16px 0;">'
		. '<tr><td style="padding:17px 19px;color:#ffffff;">'
		. '<div style="font-size:16px;font-weight:800;color:#e6b84d;">'
		. esc_html( azwc_fu_pretty( $row->slot_start_gmt ) ) . '</div>'
		. '<div style="font-size:13.5px;color:#d6dce4;margin-top:7px;">'
		. 'Site: ' . esc_html( $row->domain ) . '<br>'
		. ( $row->phone ? 'We will call: ' . esc_html( $row->phone ) : 'We will call the number you gave us' )
		. '</div></td></tr></table>'
		. '<p>A calendar invite is attached. Before we speak we will go through your site by hand — '
		. 'the parts an automated crawler cannot judge — so the half hour is spent on what to do, '
		. 'not on reading findings out.</p>'
		. '<p style="font-size:13px;color:#6b7480;">Something come up? '
		. '<a href="' . esc_url( azwc_fu_action_url( 'cancel', $row->token ) ) . '" style="color:#9b711b;">Cancel this call</a> '
		. 'and the slot goes back to someone else.</p>';

	return azwc_fu_send(
		$row->email,
		'Confirmed: your SEO call, ' . azwc_fu_local( $row->slot_start_gmt )->format( 'D j M \a\t g:ia' ),
		$body,
		$attachments,
		azwc_fu_notify_email()
	);
}

/** One hour out. */
function azwc_fu_mail_reminder( $row ) {
	$body = '<p>' . azwc_fu_greeting( $row->name ) . '</p>'
		. '<p>Just a reminder that we are calling you in about an hour, at '
		. '<b>' . esc_html( azwc_fu_local( $row->slot_start_gmt )->format( 'g:ia' ) ) . ' Arizona time</b>'
		. ', about <b>' . esc_html( $row->domain ) . '</b>.</p>'
		. ( $row->phone
			? '<p>We will ring <b>' . esc_html( $row->phone ) . '</b>.</p>'
			: '' )
		. '<p>Your audit is done and we have notes ready. Half an hour, no pitch deck.</p>'
		. '<p style="font-size:13px;color:#6b7480;">Cannot make it after all? '
		. '<a href="' . esc_url( azwc_fu_action_url( 'cancel', $row->token ) ) . '" style="color:#9b711b;">Let us know here</a> '
		. 'so we can give the slot away.</p>';

	return azwc_fu_send(
		$row->email,
		'Your SEO call is in an hour',
		$body,
		array(),
		azwc_fu_notify_email()
	);
}


/* -------------------------------------------------------------------------
 * Internal notifications
 * ---------------------------------------------------------------------- */

/**
 * What info@ gets. Written to be readable on a phone lock screen — the useful
 * facts first, the admin detail after.
 */
function azwc_fu_mail_internal( $row, $event ) {
	$titles = array(
		'report'    => 'Report downloaded',
		'requested' => 'CALL REQUESTED — awaiting their confirmation',
		'confirmed' => 'CALL CONFIRMED — audit this before the call',
		'cancelled' => 'Call cancelled',
	);
	$title = isset( $titles[ $event ] ) ? $titles[ $event ] : $event;

	$rows = array(
		'Name'   => $row->name,
		'Email'  => $row->email,
		'Phone'  => $row->phone ? $row->phone : '—',
		'Site'   => $row->domain,
		'Score'  => null === $row->score ? '—' : $row->score . '/100',
		'When'   => $row->slot_start_gmt ? azwc_fu_pretty( $row->slot_start_gmt ) : '—',
		'Source' => 'Free SEO Check tool',
	);

	$table = '';
	foreach ( $rows as $k => $v ) {
		$table .= '<tr><td style="padding:5px 14px 5px 0;color:#6b7480;font-size:13px;white-space:nowrap;">'
			. esc_html( $k ) . '</td><td style="padding:5px 0;font-size:14px;font-weight:600;">'
			. esc_html( $v ) . '</td></tr>';
	}

	$body = '<p style="font-size:17px;font-weight:800;margin:0 0 4px;">' . esc_html( $title ) . '</p>'
		. '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:14px 0;">' . $table . '</table>'
		. ( 'confirmed' === $event
			? '<p style="background:#faf6ec;border-left:3px solid #e6b84d;padding:11px 14px;font-size:14px;">'
				. 'They confirmed, so the slot is live. Run the hand audit before the call.</p>'
			: '' )
		. ( 'requested' === $event
			? '<p style="font-size:13.5px;color:#6b7480;">Held for ' . (int) AZWC_FU_HOLD_H
				. ' hours. Do no work yet — the slot is released automatically if they never confirm.</p>'
			: '' )
		. '<p style="font-size:12.5px;color:#9aa3ad;">Lead #' . (int) $row->id
		. ' &nbsp;·&nbsp; IP ' . esc_html( $row->ip ) . '</p>';

	return azwc_fu_send(
		azwc_fu_notify_email(),
		sprintf( '[%s] %s — %s', 'SEO tool', $title, $row->domain ),
		$body,
		array(),
		$row->email
	);
}
