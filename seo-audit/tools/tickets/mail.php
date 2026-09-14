<?php
/**
 * Two emails only: the internal "a nudge is ready" approval request, and the
 * actual nudge to the client once approved. Both go through wp_mail() so
 * they use the same working delivery path as every other plugin on this
 * site (this server's PHP cannot open outbound SMTP sockets directly —
 * confirmed by test — so wp_mail's local-relay route is the only one that
 * works here; see azwc-tickets.php).
 *
 * @package AZWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Shared shell — same look as the follow-up plugin's emails, for one visual identity. */
function azwc_tk_wrap( $title, $body ) {
	$year = gmdate( 'Y' );

	return '<!DOCTYPE html><html><head><meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width,initial-scale=1">'
		. '<title>' . esc_html( $title ) . '</title></head>'
		. '<body style="margin:0;padding:0;background:#eef0f3;">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef0f3;padding:24px 12px;">'
		. '<tr><td align="center">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">'
		. '<tr><td style="background:#0d1117;padding:22px 28px;">'
		. '<span style="color:#e6b84d;font-weight:800;font-size:14px;letter-spacing:.06em;">AZ WEB CORP &middot; TICKETS</span>'
		. '</td></tr>'
		. '<tr><td style="padding:28px;color:#1c2430;font-size:15px;line-height:1.6;">' . $body . '</td></tr>'
		. '<tr><td style="background:#f6f7f9;padding:16px 28px;color:#6b7480;font-size:12px;border-top:1px solid #e2e5ea;">'
		. 'AZ Web Corp Tickets &copy; ' . esc_html( $year )
		. '</td></tr></table></td></tr></table></body></html>';
}

function azwc_tk_button( $url, $label, $bg = '#e6b84d', $color = '#161208' ) {
	return '<a href="' . esc_url( $url ) . '" style="display:inline-block;margin-top:14px;margin-right:10px;'
		. 'padding:12px 22px;border-radius:9px;background:' . esc_attr( $bg ) . ';color:' . esc_attr( $color ) . ';'
		. 'font-weight:800;text-decoration:none;font-size:14.5px;">' . esc_html( $label ) . '</a>';
}

/**
 * "A ticket has gone quiet — here's a suggested nudge" — sent to whoever
 * reviews tickets (azwc_tk_notify_email()), never to the client. The link
 * lands on a GET-renders/POST-acts page (rest.php) so a mail scanner opening
 * the link cannot accidentally send the email itself.
 */
function azwc_tk_send_nudge_approval_email( $row ) {
	$approve = azwc_tk_action_url( 'send', $row->nudge_token );
	$skip    = azwc_tk_action_url( 'skip', $row->nudge_token );
	$hours   = round( ( time() - strtotime( $row->last_activity_gmt . ' UTC' ) ) / HOUR_IN_SECONDS );

	$body = '<p style="font-size:18px;margin:0 0 6px;"><b>' . esc_html( $row->ticket_no ) . '</b> has been quiet for ' . (int) $hours . ' hours.</p>'
		. '<p style="color:#6b7480;margin:0 0 18px;">' . esc_html( $row->subject ) . ' &middot; ' . esc_html( $row->counterparty_name ? $row->counterparty_name . ' — ' : '' ) . esc_html( $row->counterparty_email ) . '</p>'
		. '<p style="margin:0 0 6px;font-weight:700;">Suggested follow-up:</p>'
		. '<div style="background:#f6f7f9;border-left:3px solid #e6b84d;border-radius:0 8px 8px 0;padding:14px 16px;white-space:pre-wrap;">'
		. esc_html( $row->nudge_text )
		. '</div>'
		. azwc_tk_button( $approve, 'Send this nudge' )
		. azwc_tk_button( $skip, 'Skip for now', '#e2e5ea', '#1c2430' );

	return azwc_tk_send(
		azwc_tk_notify_email(),
		'[' . $row->ticket_no . '] Follow-up ready to review',
		azwc_tk_wrap( 'Follow-up ready', $body )
	);
}

/**
 * "N new ticket(s) came in" — one email per sync run, only sent when the run
 * actually created at least one ticket, so a quiet run stays silent. This is
 * the notification path so a new client email doesn't sit unseen until
 * someone happens to open the Tickets admin screen.
 */
function azwc_tk_send_new_tickets_email( $tickets ) {
	if ( ! $tickets ) {
		return false;
	}

	$count = count( $tickets );
	$rows  = '';
	foreach ( $tickets as $t ) {
		$rows .= '<div style="padding:14px 0;border-bottom:1px solid #e2e5ea;">'
			. '<div style="font-size:15px;font-weight:800;">' . esc_html( $t['ticket_no'] ) . ' &middot; ' . esc_html( $t['subject'] ) . '</div>'
			. '<div style="color:#6b7480;font-size:13.5px;margin-top:2px;">'
			. esc_html( $t['counterparty_name'] ? $t['counterparty_name'] . ' — ' : '' ) . esc_html( $t['counterparty_email'] )
			. '</div></div>';
	}

	$body = '<p style="font-size:18px;margin:0 0 16px;">'
		. ( 1 === $count ? '1 new ticket came in.' : $count . ' new tickets came in.' )
		. '</p>'
		. $rows
		. azwc_tk_button( admin_url( 'admin.php?page=azwc-tickets' ), 'View in Tickets' );

	return azwc_tk_send(
		azwc_tk_notify_email(),
		( 1 === $count ? '1 new ticket' : $count . ' new tickets' ) . ' — ' . implode( ', ', wp_list_pluck( $tickets, 'ticket_no' ) ),
		azwc_tk_wrap( 'New tickets', $body )
	);
}

/** The actual nudge, once approved — goes to the client. */
function azwc_tk_send_nudge_to_client( $row ) {
	$subject = '[' . $row->ticket_no . '] ' . $row->subject;
	$body    = '<p>' . nl2br( esc_html( $row->nudge_text ) ) . '</p>';

	return azwc_tk_send(
		$row->counterparty_email,
		$subject,
		azwc_tk_wrap( $subject, $body ),
		'info@azwebcorp.com'
	);
}

function azwc_tk_send( $to, $subject, $html, $reply_to = '' ) {
	$type = function () {
		return 'text/html';
	};
	add_filter( 'wp_mail_content_type', $type );

	$headers = array();
	if ( $reply_to && is_email( $reply_to ) ) {
		$headers[] = 'Reply-To: ' . $reply_to;
	}

	$sent = wp_mail( $to, $subject, $html, $headers );
	remove_filter( 'wp_mail_content_type', $type );

	return $sent;
}
