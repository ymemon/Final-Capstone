<?php
/**
 * Storage and the small helpers every other file in this plugin shares.
 *
 * @package AZWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function azwc_tk_table() {
	global $wpdb;
	return $wpdb->prefix . 'azwc_tickets';
}

function azwc_tk_install() {
	if ( AZWC_TK_DB_VERSION === get_option( 'azwc_tk_db_version' ) ) {
		return;
	}

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = azwc_tk_table();
	$collate = $wpdb->get_charset_collate();

	dbDelta(
		"CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_no VARCHAR(20) NOT NULL DEFAULT '',
			subject VARCHAR(500) NOT NULL DEFAULT '',
			counterparty_email VARCHAR(190) NOT NULL DEFAULT '',
			counterparty_name VARCHAR(190) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'waiting_us',
			last_direction VARCHAR(10) NOT NULL DEFAULT 'them',
			created_gmt DATETIME NOT NULL,
			last_activity_gmt DATETIME NOT NULL,
			thread_ids LONGTEXT NULL,
			nudge_status VARCHAR(20) NOT NULL DEFAULT 'none',
			nudge_text LONGTEXT NULL,
			nudge_token CHAR(32) NULL,
			nudge_drafted_gmt DATETIME NULL,
			nudge_sent_gmt DATETIME NULL,
			client_token CHAR(32) NULL,
			reply_promised_gmt DATETIME NULL,
			closed_by VARCHAR(20) NULL,
			closed_gmt DATETIME NULL,
			notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ticket_no_idx (ticket_no),
			KEY status_idx (status, last_activity_gmt),
			KEY email_idx (counterparty_email),
			UNIQUE KEY nudge_token_idx (nudge_token),
			UNIQUE KEY client_token_idx (client_token)
		) {$collate};"
	);

	// Existing rows predate client_token and would otherwise never get one,
	// so their emails could carry no client action buttons at all.
	$missing = $wpdb->get_col( 'SELECT ticket_no FROM ' . $table . ' WHERE client_token IS NULL' );
	foreach ( (array) $missing as $ticket_no ) {
		$wpdb->update(
			$table,
			array( 'client_token' => azwc_tk_new_token() ),
			array( 'ticket_no' => $ticket_no )
		);
	}

	update_option( 'azwc_tk_db_version', AZWC_TK_DB_VERSION, false );
}
add_action( 'init', 'azwc_tk_install', 1 );

/**
 * Where the "a nudge is ready to review" email goes.
 *
 * Deliberately NOT info@azwebcorp.com: that is the mailbox being polled for
 * tickets, so a notification landing back in it would be read as a new
 * client email next run. An option, not a constant, so it can change without
 * a deploy.
 */
function azwc_tk_notify_email() {
	$to = get_option( 'azwc_tk_notify_email', 'ymemon@asu.edu' );
	return is_email( $to ) ? $to : 'ymemon@asu.edu';
}

/** Next sequential ticket number, e.g. "AZW-1001". Derived from the table each
 *  time rather than stored separately, so there is nothing to get out of sync. */
function azwc_tk_next_number() {
	global $wpdb;
	$table = azwc_tk_table();

	$max = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT MAX(CAST(SUBSTRING(ticket_no, %d) AS UNSIGNED)) FROM {$table} WHERE ticket_no LIKE %s",
			strlen( AZWC_TK_PREFIX ) + 2,
			$wpdb->esc_like( AZWC_TK_PREFIX . '-' ) . '%'
		)
	);

	$next = $max ? ( (int) $max + 1 ) : AZWC_TK_START_SEQ;
	return AZWC_TK_PREFIX . '-' . $next;
}

function azwc_tk_new_token() {
	return substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 32 );
}

function azwc_tk_get( $ticket_no ) {
	global $wpdb;
	return $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . azwc_tk_table() . ' WHERE ticket_no = %s', $ticket_no )
	);
}

function azwc_tk_find_by_token( $token ) {
	global $wpdb;
	$token = preg_replace( '/[^a-f0-9]/i', '', (string) $token );
	if ( 32 !== strlen( $token ) ) {
		return null;
	}
	return $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . azwc_tk_table() . ' WHERE nudge_token = %s', $token )
	);
}

/** Strip Re:/Fwd:/[AZW-####] noise so the same conversation compares equal. */
function azwc_tk_normalize_subject( $subject ) {
	$s = (string) $subject;
	$s = preg_replace( '/^\s*(re|fwd?|aw)\s*:\s*/i', '', $s );
	$s = preg_replace( '/^\s*\[' . preg_quote( AZWC_TK_PREFIX, '/' ) . '-\d+\]\s*/i', '', $s );
	return trim( wp_strip_all_tags( $s ) );
}

/**
 * Locate an existing ticket for an incoming/outgoing message.
 *
 * Message-ID overlap (References/In-Reply-To against what we have on file)
 * is tried first since it is unambiguous even if a client renames the
 * subject line. Falls back to (counterparty email + normalized subject) for
 * mail clients that break threading headers.
 */
function azwc_tk_find_thread( $email, $subject, $message_ids = array() ) {
	global $wpdb;
	$table = azwc_tk_table();

	if ( $message_ids ) {
		$like_clauses = array();
		$params       = array();
		foreach ( $message_ids as $mid ) {
			$mid = trim( (string) $mid );
			if ( '' === $mid ) {
				continue;
			}
			$like_clauses[] = 'thread_ids LIKE %s';
			$params[]       = '%' . $wpdb->esc_like( $mid ) . '%';
		}
		if ( $like_clauses ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE (" . implode( ' OR ', $like_clauses ) . ') ORDER BY last_activity_gmt DESC LIMIT 1',
					$params
				)
			);
			if ( $row ) {
				return $row;
			}
		}
	}

	$norm = azwc_tk_normalize_subject( $subject );
	if ( '' === $norm || '' === $email ) {
		return null;
	}

	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE counterparty_email = %s AND subject = %s ORDER BY last_activity_gmt DESC LIMIT 1",
			$email,
			$norm
		)
	);
}

function azwc_tk_create( $email, $name, $subject, $direction, $message_id = '' ) {
	global $wpdb;

	$ticket_no = azwc_tk_next_number();
	$now       = gmdate( 'Y-m-d H:i:s' );
	$norm      = azwc_tk_normalize_subject( $subject );
	$ids       = $message_id ? array( $message_id ) : array();

	// direction is who sent the FIRST message: 'inbound' (client wrote first,
	// we owe a reply) or 'outbound' (we wrote first, waiting on them).
	$status = ( 'outbound' === $direction ) ? 'waiting_client' : 'waiting_us';
	$last   = ( 'outbound' === $direction ) ? 'us' : 'them';

	$wpdb->insert(
		azwc_tk_table(),
		array(
			'ticket_no'          => $ticket_no,
			'subject'            => $norm,
			'counterparty_email' => sanitize_email( $email ),
			'counterparty_name'  => sanitize_text_field( $name ),
			'status'             => $status,
			'last_direction'     => $last,
			'created_gmt'        => $now,
			'last_activity_gmt'  => $now,
			'thread_ids'         => wp_json_encode( $ids ),
			'client_token'       => azwc_tk_new_token(),
		),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);

	return $ticket_no;
}

/**
 * The token behind the client-facing "Resolved" / "I'll reply" buttons.
 *
 * Deliberately separate from nudge_token. That one drives Yasir's internal
 * approve/skip links; if a client's email carried the same token, anyone with
 * that email could also trigger the internal nudge actions. Different audience,
 * different secret.
 *
 * Lazily created so tickets that predate this feature still get buttons the
 * first time an email goes out on them.
 */
function azwc_tk_client_token( $ticket_no ) {
	global $wpdb;

	$row = azwc_tk_get( $ticket_no );
	if ( ! $row ) {
		return '';
	}
	if ( ! empty( $row->client_token ) ) {
		return $row->client_token;
	}

	$token = azwc_tk_new_token();
	$wpdb->update( azwc_tk_table(), array( 'client_token' => $token ), array( 'ticket_no' => $ticket_no ) );

	return $token;
}

function azwc_tk_find_by_client_token( $token ) {
	global $wpdb;
	$token = preg_replace( '/[^a-f0-9]/i', '', (string) $token );
	if ( 32 !== strlen( $token ) ) {
		return null;
	}

	return $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . azwc_tk_table() . ' WHERE client_token = %s', $token )
	);
}

/** Public URL for a client-facing action ("resolved" or "replying"). */
function azwc_tk_client_url( $action, $token ) {
	return add_query_arg(
		array(
			'azwc_tkc' => $action,
			'token'    => $token,
		),
		home_url( '/' )
	);
}

/**
 * Log a new message on an existing ticket and flip status accordingly.
 *
 * A message from 'them' means we now owe a reply (waiting_us); a message
 * from 'us' means we are waiting on the client (waiting_client). Resolved
 * tickets are reopened by new activity from either side — a closed
 * conversation the client comes back to is not a closed conversation.
 */
function azwc_tk_log_activity( $ticket_no, $direction, $message_id = '', $note = '' ) {
	global $wpdb;
	$row = azwc_tk_get( $ticket_no );
	if ( ! $row ) {
		return false;
	}

	$ids = json_decode( (string) $row->thread_ids, true );
	if ( ! is_array( $ids ) ) {
		$ids = array();
	}
	if ( $message_id && ! in_array( $message_id, $ids, true ) ) {
		$ids[] = $message_id;
	}

	$status = ( 'them' === $direction ) ? 'waiting_us' : 'waiting_client';

	$notes = $row->notes;
	if ( $note ) {
		$stamp = gmdate( 'Y-m-d H:i' ) . ' UTC';
		$notes = trim( ( $notes ? $notes . "\n" : '' ) . "[{$stamp}] {$note}" );
	}

	$update = array(
		'status'             => $status,
		'last_direction'     => $direction,
		'last_activity_gmt'  => gmdate( 'Y-m-d H:i:s' ),
		'thread_ids'         => wp_json_encode( $ids ),
		'notes'              => $notes,
	);
	// New activity re-opens a resolved ticket and clears any stale nudge.
	if ( 'waiting_client' !== $status ) {
		$update['nudge_status'] = 'none';
	}

	$wpdb->update( azwc_tk_table(), $update, array( 'ticket_no' => $ticket_no ) );
	return true;
}

/**
 * Close a ticket.
 *
 * $closed_by records who ended it - 'client' when they pressed the Resolved
 * button in an email, 'us' otherwise. Worth keeping: "closed because the client
 * said so" and "closed because we decided it was done" are different facts, and
 * only the first is evidence the work actually landed.
 */
function azwc_tk_resolve( $ticket_no, $closed_by = 'us' ) {
	global $wpdb;
	return (bool) $wpdb->update(
		azwc_tk_table(),
		array(
			'status'       => 'resolved',
			'closed_by'    => ( 'client' === $closed_by ) ? 'client' : 'us',
			'closed_gmt'   => gmdate( 'Y-m-d H:i:s' ),
			'nudge_status' => 'none',
		),
		array( 'ticket_no' => $ticket_no )
	);
}

/**
 * How long a client's "I'll reply" promise buys them.
 *
 * The whole point of the button is that someone who has told us they are coming
 * back should stop receiving reminders. Seven days is long enough to be
 * genuinely useful and short enough that a forgotten thread still resurfaces.
 */
const AZWC_TK_REPLY_GRACE_DAYS = 7;

/** Tickets waiting on the client, silent past the stale threshold, not yet nudged. */
function azwc_tk_stale( $hours ) {
	global $wpdb;
	$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( (int) $hours * HOUR_IN_SECONDS ) );
	$grace  = gmdate( 'Y-m-d H:i:s', time() - ( AZWC_TK_REPLY_GRACE_DAYS * DAY_IN_SECONDS ) );

	// A client who clicked "I'll reply" is excluded until the grace window
	// lapses - chasing someone who already answered is exactly the behaviour
	// this feature exists to stop.
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM " . azwc_tk_table() . "
			  WHERE status = 'waiting_client'
			    AND nudge_status = 'none'
			    AND last_activity_gmt < %s
			    AND ( reply_promised_gmt IS NULL OR reply_promised_gmt < %s )
			  ORDER BY last_activity_gmt ASC",
			$cutoff,
			$grace
		)
	);
}

/** IMAP polling checkpoint, so the stateless scheduled job knows where it left off. */
function azwc_tk_get_checkpoint() {
	$cp = get_option( 'azwc_tk_imap_checkpoint', array() );
	return is_array( $cp ) ? $cp : array();
}

function azwc_tk_set_checkpoint( $uid, $date ) {
	update_option(
		'azwc_tk_imap_checkpoint',
		array(
			'uid'  => (int) $uid,
			'date' => sanitize_text_field( $date ),
		),
		false
	);
}

/** Public URL for the nudge approve / skip landing. */
function azwc_tk_action_url( $action, $token ) {
	return add_query_arg(
		array(
			'azwc_tk'   => $action,
			'token'     => $token,
		),
		home_url( '/' )
	);
}
