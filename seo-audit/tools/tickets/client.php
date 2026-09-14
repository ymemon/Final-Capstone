<?php
/**
 * The two client-facing buttons that ship in every outbound email:
 * "All sorted, thanks" (closes the ticket) and "I'll reply" (stops the
 * reminders without closing anything).
 *
 * WHY THIS EXISTS
 * Before this, a thread waiting on a client got nudged on a timer whether or
 * not they had already dealt with it, and the only way to stop that was for
 * them to write a reply they did not otherwise need to write. Two buttons let
 * them close the loop in one click, and let us stop chasing people who are
 * simply busy rather than unresponsive.
 *
 * GET RENDERS, POST ACTS - THIS IS NOT OPTIONAL
 * Mail scanners and link-preview bots fetch every URL in an email before a
 * human ever sees it. If a GET closed the ticket, tickets would close
 * themselves the moment the mail was delivered. Same split the nudge landing
 * page uses, for the same reason - see rest.php.
 *
 * @package AZWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'azwc_tk_handle_client_action', 21 );

function azwc_tk_handle_client_action() {
	if ( is_admin() || wp_doing_ajax() || ! isset( $_GET['azwc_tkc'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}

	$action = sanitize_key( wp_unslash( $_GET['azwc_tkc'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! in_array( $action, array( 'resolved', 'replying' ), true ) ) {
		return;
	}

	$row = azwc_tk_find_by_client_token( isset( $_GET['token'] ) ? wp_unslash( $_GET['token'] ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! $row ) {
		azwc_tk_page(
			'Link not recognised',
			'<p>We could not match that link to a conversation. It may have been replaced by a newer email.</p>'
			. '<p>Replying to the email directly always works.</p>',
			false
		);
	}

	$method    = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
	$submitted = ( 'POST' === $method )
		&& isset( $_POST['azwc_tkc_do'] ) // phpcs:ignore WordPress.Security.NonceVerification
		&& $action === sanitize_key( wp_unslash( $_POST['azwc_tkc_do'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

	if ( ! $submitted ) {
		azwc_tk_client_gate( $row, $action );
	}

	azwc_tk_client_do( $row, $action );
}

/** The confirmation screen a real person sees after clicking the email button. */
function azwc_tk_client_gate( $row, $action ) {
	$subject = '<p style="color:#9aa3ad;margin:0 0 20px;font-size:14.5px;">'
		. esc_html( $row->subject ) . ' &middot; <b>' . esc_html( $row->ticket_no ) . '</b></p>';

	if ( 'resolved' === $row->status ) {
		azwc_tk_page(
			'Already closed',
			$subject . '<p>This one is already marked as sorted. Nothing further needed, and you will not hear from us about it again.</p>',
			false
		);
	}

	if ( 'resolved' === $action ) {
		$body = $subject
			. '<p>Close this off and stop any further reminders about it?</p>'
			. '<p style="color:#9aa3ad;font-size:14px;">If something comes up later, just reply to the email and it reopens.</p>';
		$label = 'Yes, all sorted';
		$title = 'Mark this as sorted?';
	} else {
		$body = $subject
			. '<p>We will hold off on reminders for the next ' . (int) AZWC_TK_REPLY_GRACE_DAYS
			. ' days so you can get to it in your own time.</p>'
			. '<p style="color:#9aa3ad;font-size:14px;">The conversation stays open - nothing is closed or lost.</p>';
		$label = 'Yes, I will reply';
		$title = 'Pause the reminders?';
	}

	$body .= '<form method="post" action="' . esc_url( azwc_tk_client_url( $action, $row->client_token ) ) . '">'
		. '<input type="hidden" name="azwc_tkc_do" value="' . esc_attr( $action ) . '">'
		. '<button type="submit" class="btn">' . esc_html( $label ) . '</button>'
		. '</form>';

	azwc_tk_page( $title, $body, false );
}

/**
 * Claim-then-act, matching the nudge flow.
 *
 * This host serves cached GET responses regardless of nocache_headers(), so a
 * client re-opening an old email can be handed a stale gate page after the
 * action already ran. The conditional UPDATE means only the first request
 * actually changes anything; replays land on "already closed".
 */
function azwc_tk_client_do( $row, $action ) {
	global $wpdb;
	$table = azwc_tk_table();
	$now   = gmdate( 'Y-m-d H:i:s' );

	if ( 'resolved' === $action ) {
		$claimed = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				    SET status = 'resolved', closed_by = 'client', closed_gmt = %s, nudge_status = 'none'
				  WHERE ticket_no = %s AND status <> 'resolved'",
				$now,
				$row->ticket_no
			)
		);

		if ( ! $claimed ) {
			azwc_tk_page(
				'Already closed',
				'<p>This was already marked as sorted - nothing more to do.</p>',
				false
			);
		}

		azwc_tk_notify_internal( $row, 'resolved' );
		azwc_tk_page(
			'Thank you',
			'<p>Closed off, and we have stopped any further reminders on it.</p>'
			. '<p style="color:#9aa3ad;font-size:14px;">If anything else comes up, replying to the email brings it straight back to us.</p>',
			false
		);
	}

	// "I'll reply" - the ticket stays open, reminders pause.
	$wpdb->update(
		$table,
		array(
			'reply_promised_gmt' => $now,
			'nudge_status'       => 'none',
		),
		array( 'ticket_no' => $row->ticket_no )
	);

	azwc_tk_notify_internal( $row, 'replying' );
	azwc_tk_page(
		'Noted, thank you',
		'<p>We will leave it with you and hold the reminders for the next '
		. (int) AZWC_TK_REPLY_GRACE_DAYS . ' days.</p>'
		. '<p style="color:#9aa3ad;font-size:14px;">No rush, and nothing is closed - the conversation is still open.</p>',
		false
	);
}

/** Tell the team a client acted, so a human knows without watching the table. */
function azwc_tk_notify_internal( $row, $action ) {
	$to = azwc_tk_notify_email();
	if ( ! $to ) {
		return;
	}

	$who = $row->counterparty_name ? $row->counterparty_name : $row->counterparty_email;

	if ( 'resolved' === $action ) {
		$subject = sprintf( '[%s] Client closed this: %s', $row->ticket_no, $row->subject );
		$body    = sprintf(
			"%s (%s) pressed \"All sorted\" on %s.\n\nSubject: %s\n\nThe ticket is closed and no further reminders will go out.\n",
			$who,
			$row->counterparty_email,
			$row->ticket_no,
			$row->subject
		);
	} else {
		$subject = sprintf( '[%s] Client says they will reply: %s', $row->ticket_no, $row->subject );
		$body    = sprintf(
			"%s (%s) pressed \"I'll reply\" on %s.\n\nSubject: %s\n\nThe ticket stays open. Reminders are paused for %d days.\n",
			$who,
			$row->counterparty_email,
			$row->ticket_no,
			$row->subject,
			AZWC_TK_REPLY_GRACE_DAYS
		);
	}

	wp_mail( $to, $subject, $body );
}

/**
 * The HTML block dropped into outbound client email.
 *
 * Returned as a string rather than echoed so the Python mailer can fetch it
 * over the API and paste it into whichever template it is building.
 */
function azwc_tk_client_buttons_html( $ticket_no ) {
	$token = azwc_tk_client_token( $ticket_no );
	if ( ! $token ) {
		return '';
	}

	$resolved = azwc_tk_client_url( 'resolved', $token );
	$replying = azwc_tk_client_url( 'replying', $token );

	$btn  = 'display:inline-block;padding:11px 20px;border-radius:6px;font-size:14.5px;'
		. 'font-weight:600;text-decoration:none;font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;';
	$gold = $btn . 'background:#d19434;color:#ffffff;';
	$ghost = $btn . 'background:#ffffff;color:#111820;border:1px solid #cfd4d9;';

	return '<table cellpadding="0" cellspacing="0" style="margin:26px 0 6px;"><tr>'
		. '<td style="padding-right:10px;"><a href="' . esc_url( $resolved ) . '" style="' . $gold . '">All sorted, thanks</a></td>'
		. '<td><a href="' . esc_url( $replying ) . '" style="' . $ghost . '">I&rsquo;ll reply</a></td>'
		. '</tr></table>'
		. '<p style="font-size:12.5px;color:#8a939c;margin:6px 0 0;'
		. 'font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;">'
		. 'One click either way - it just tells us whether to stop reminding you.</p>';
}
