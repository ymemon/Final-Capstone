<?php
/**
 * Storage for real-time client-watch events.
 *
 * @package AZWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function azwc_cw_table() {
	global $wpdb;
	return $wpdb->prefix . 'azwc_watch';
}

/**
 * One row per email observed. Deliberately separate from the follow-up
 * leads table (wp_azwc_leads) — this is a different plugin watching a
 * different thing (the raw inbox), not the lead-capture forms.
 */
function azwc_cw_install() {
	if ( AZWC_CW_DB_VERSION === get_option( 'azwc_cw_db_version' ) ) {
		return;
	}

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = azwc_cw_table();
	$collate = $wpdb->get_charset_collate();

	dbDelta(
		"CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_gmt DATETIME NOT NULL,
			from_email VARCHAR(190) NOT NULL DEFAULT '',
			from_name VARCHAR(120) NOT NULL DEFAULT '',
			subject VARCHAR(255) NOT NULL DEFAULT '',
			excerpt TEXT NULL,
			source VARCHAR(16) NOT NULL DEFAULT 'generic',
			status VARCHAR(16) NOT NULL DEFAULT 'new',
			notified_gmt DATETIME NULL,
			ip VARCHAR(45) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY created_idx (created_gmt),
			KEY from_email_idx (from_email),
			KEY status_idx (status)
		) {$collate};"
	);

	update_option( 'azwc_cw_db_version', AZWC_CW_DB_VERSION, false );
}
add_action( 'init', 'azwc_cw_install', 1 );

/** Where real-time notifications go. Option so it can change without a deploy. */
function azwc_cw_notify_email() {
	$to = get_option( 'azwc_cw_notify_email', 'info@azwebcorp.com' );
	return is_email( $to ) ? $to : 'info@azwebcorp.com';
}

function azwc_cw_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
	return substr( (string) $ip, 0, 45 );
}
