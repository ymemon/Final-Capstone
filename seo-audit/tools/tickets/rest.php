<?php
/**
 * The nudge approve/skip landing page.
 *
 * Same GET-renders/POST-acts split as the follow-up plugin's confirm/cancel
 * links, for the same reason: this host caches responses regardless of
 * nocache_headers(), and mail scanners fetch every link in an email before a
 * person ever sees it. A GET that sent the email would mean the scanner
 * sends it, not the reviewer's click.
 *
 * @package AZWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'azwc_tk_handle_action', 20 );

function azwc_tk_handle_action() {
	if ( is_admin() || wp_doing_ajax() || ! isset( $_GET['azwc_tk'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}

	$action = sanitize_key( wp_unslash( $_GET['azwc_tk'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! in_array( $action, array( 'send', 'skip' ), true ) ) {
		return;
	}

	$row = azwc_tk_find_by_token( isset( $_GET['token'] ) ? wp_unslash( $_GET['token'] ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification

	if ( ! $row ) {
		azwc_tk_page( 'Link not recognised', '<p>That link has expired or was already used.</p>' );
	}

	$method    = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
	$submitted = ( 'POST' === $method )
		&& isset( $_POST['azwc_tk_do'] ) // phpcs:ignore WordPress.Security.NonceVerification
		&& $action === sanitize_key( wp_unslash( $_POST['azwc_tk_do'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

	if ( ! $submitted ) {
		azwc_tk_gate( $row, $action );
	}

	azwc_tk_do_action( $row, $action );
}

function azwc_tk_gate( $row, $action ) {
	if ( 'pending_approval' !== $row->nudge_status ) {
		azwc_tk_page(
			'Already handled',
			'<p>This nudge for <b>' . esc_html( $row->ticket_no ) . '</b> was already ' . esc_html( str_replace( '_', ' ', $row->nudge_status ) ) . '.</p>'
		);
	}

	$sending = ( 'send' === $action );
	$body    = '<p style="font-size:18px;margin:0 0 6px;"><b>' . esc_html( $row->ticket_no ) . '</b></p>'
		. '<p style="color:#6b7480;margin:0 0 18px;">' . esc_html( $row->subject ) . ' &middot; ' . esc_html( $row->counterparty_email ) . '</p>'
		. ( $sending
			? '<div style="background:rgba(255,255,255,.05);border:1px solid rgba(230,184,77,.24);border-radius:8px;padding:14px 16px;white-space:pre-wrap;margin-bottom:18px;">' . esc_html( $row->nudge_text ) . '</div><p>Send this to the client now?</p>'
			: '<p>Skip this nudge? The ticket stays open and you can send one manually any time.</p>' )
		. '<form method="post" action="' . esc_url( azwc_tk_action_url( $action, $row->nudge_token ) ) . '">'
		. '<input type="hidden" name="azwc_tk_do" value="' . esc_attr( $action ) . '">'
		. '<button type="submit" class="btn">' . ( $sending ? 'Send it' : 'Skip it' ) . '</button>'
		. '</form>';

	azwc_tk_page( $sending ? 'Send this nudge?' : 'Skip this nudge?', $body, false );
}

/**
 * Claim-then-act, not act-then-record.
 *
 * A GET response on this host gets cached regardless of nocache_headers()
 * (see the follow-up plugin's own notes on the same gotcha), so a reviewer
 * re-opening an old email link can be served a stale "pending" gate page
 * after the real action already happened. Without this guard, submitting
 * that stale form would re-send the nudge to the client a second time. The
 * UPDATE below only succeeds if nudge_status is still 'pending_approval' at
 * the moment it runs — whichever request wins that race is the only one
 * that acts, and every other request (stale, replayed, or double-clicked)
 * finds nothing left to claim and reports "already handled".
 */
function azwc_tk_do_action( $row, $action ) {
	global $wpdb;
	$table = azwc_tk_table();

	$claimed = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$table} SET nudge_status = 'claimed' WHERE ticket_no = %s AND nudge_status = 'pending_approval'",
			$row->ticket_no
		)
	);

	if ( ! $claimed ) {
		azwc_tk_page(
			'Already handled',
			'<p>This nudge for <b>' . esc_html( $row->ticket_no ) . '</b> was already acted on — check the Tickets screen for its current status.</p>'
		);
	}

	if ( 'send' === $action ) {
		$ok = azwc_tk_send_nudge_to_client( $row );
		$wpdb->update(
			$table,
			array(
				'nudge_status'   => $ok ? 'sent' : 'none',
				'nudge_sent_gmt' => $ok ? gmdate( 'Y-m-d H:i:s' ) : null,
			),
			array( 'ticket_no' => $row->ticket_no )
		);
		azwc_tk_page(
			$ok ? 'Sent' : 'Could not send',
			$ok ? '<p>The follow-up for <b>' . esc_html( $row->ticket_no ) . '</b> is on its way.</p>'
				: '<p>Something went wrong sending it. Try again from the Tickets admin screen, or reply to the client directly.</p>'
		);
	}

	$wpdb->update(
		$table,
		array( 'nudge_status' => 'skipped' ),
		array( 'ticket_no' => $row->ticket_no )
	);
	azwc_tk_page( 'Skipped', '<p><b>' . esc_html( $row->ticket_no ) . '</b> stays open with no automatic follow-up sent.</p>' );
}

/** Same visual shell as the follow-up plugin's landing pages. */
function azwc_tk_page( $title, $body, $link = '' ) {
	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );

	$button = ( false === $link )
		? ''
		: '<a class="btn" href="' . esc_url( $link ? $link : home_url( '/wp-admin/admin.php?page=azwc-tickets' ) ) . '">Back to Tickets</a>';

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
		. '.btn:hover{background:#f5d47d}form{margin:0}</style></head><body>'
		. '<div class="card"><div class="mark">AZ WEB CORP &middot; TICKETS</div>'
		. '<h1>' . esc_html( $title ) . '</h1>'
		. $body . $button . '</div></body></html>';
	exit;
}
