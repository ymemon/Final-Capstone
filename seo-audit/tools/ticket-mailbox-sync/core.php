<?php
/**
 * Storage: one row per ticket, one row per inbound message.
 *
 * @package AZWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function azwc_tms_tickets_table() {
	global $wpdb;
	return $wpdb->prefix . 'azwc_tickets';
}

function azwc_tms_messages_table() {
	global $wpdb;
	return $wpdb->prefix . 'azwc_ticket_messages';
}

/**
 * Two tables: a ticket is the thread (one per client conversation), a
 * message is one inbound email logged against it. Kept separate from
 * azwc_watch (Client Watch) and azwc_leads (follow-up) — three different
 * plugins, three different jobs, no shared schema to accidentally corrupt.
 */
function azwc_tms_install() {
	if ( AZWC_TMS_DB_VERSION === get_option( 'azwc_tms_db_version' ) ) {
		return;
	}

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$collate = $wpdb->get_charset_collate();
	$tickets = azwc_tms_tickets_table();
	$messages = azwc_tms_messages_table();

	dbDelta(
		"CREATE TABLE {$tickets} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_gmt DATETIME NOT NULL,
			subject_key VARCHAR(255) NOT NULL DEFAULT '',
			client_email VARCHAR(190) NOT NULL DEFAULT '',
			client_name VARCHAR(120) NOT NULL DEFAULT '',
			status VARCHAR(16) NOT NULL DEFAULT 'open',
			last_client_gmt DATETIME NULL,
			nudged_gmt DATETIME NULL,
			nudge_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY client_email_idx (client_email),
			KEY status_idx (status),
			KEY subject_key_idx (subject_key(191))
		) {$collate};"
	);

	dbDelta(
		"CREATE TABLE {$messages} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_id BIGINT UNSIGNED NOT NULL,
			created_gmt DATETIME NOT NULL,
			from_email VARCHAR(190) NOT NULL DEFAULT '',
			subject VARCHAR(255) NOT NULL DEFAULT '',
			excerpt TEXT NULL,
			source VARCHAR(16) NOT NULL DEFAULT 'generic',
			ip VARCHAR(45) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY ticket_id_idx (ticket_id)
		) {$collate};"
	);

	update_option( 'azwc_tms_db_version', AZWC_TMS_DB_VERSION, false );
}
add_action( 'init', 'azwc_tms_install', 1 );

function azwc_tms_notify_email() {
	$to = get_option( 'azwc_tms_notify_email', 'info@azwebcorp.com' );
	return is_email( $to ) ? $to : 'info@azwebcorp.com';
}

function azwc_tms_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
	return substr( (string) $ip, 0, 45 );
}

/** "AZW-00042" — zero-padded so tickets sort and read consistently. */
function azwc_tms_format_number( $id ) {
	return 'AZW-' . str_pad( (string) (int) $id, 5, '0', STR_PAD_LEFT );
}

/** Strip Re:/Fwd: noise and the ticket tag itself, for loose subject matching. */
function azwc_tms_subject_key( $subject ) {
	$s = preg_replace( '/\[Ticket #AZW-\d+\]/i', '', (string) $subject );
	$s = trim( (string) $s );

	// "Re: Re: Fwd:" chains — strip repeatedly, not just once.
	do {
		$before = $s;
		$s      = preg_replace( '/^\s*(re|fwd?|aw)\s*:\s*/i', '', $s );
	} while ( $s !== $before );

	return mb_strtolower( trim( $s ) );
}
