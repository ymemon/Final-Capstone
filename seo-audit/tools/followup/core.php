<?php
/**
 * Storage, availability, and report assembly.
 *
 * @package AZWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Storage
 * ---------------------------------------------------------------------- */

function azwc_fu_table() {
	global $wpdb;
	return $wpdb->prefix . 'azwc_leads';
}

/**
 * One table for both lead types.
 *
 * A PDF request and a call booking share almost every column and are the same
 * thing to whoever reads the list in the morning — a person who asked for
 * something. `kind` separates them; the slot columns are simply null for a
 * report request.
 */
function azwc_fu_install() {
	if ( AZWC_FU_DB_VERSION === get_option( 'azwc_fu_db_version' ) ) {
		return;
	}

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = azwc_fu_table();
	$collate = $wpdb->get_charset_collate();

	dbDelta(
		"CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_gmt DATETIME NOT NULL,
			kind VARCHAR(16) NOT NULL DEFAULT 'report',
			name VARCHAR(120) NOT NULL DEFAULT '',
			email VARCHAR(190) NOT NULL DEFAULT '',
			phone VARCHAR(40) NOT NULL DEFAULT '',
			domain VARCHAR(190) NOT NULL DEFAULT '',
			score SMALLINT NULL,
			slot_start_gmt DATETIME NULL,
			status VARCHAR(16) NOT NULL DEFAULT 'pending',
			token CHAR(32) NOT NULL DEFAULT '',
			confirmed_gmt DATETIME NULL,
			reminded_gmt DATETIME NULL,
			ip VARCHAR(45) NOT NULL DEFAULT '',
			notes TEXT NULL,
			PRIMARY KEY  (id),
			KEY slot_status (slot_start_gmt, status),
			KEY kind_created (kind, created_gmt),
			KEY email_idx (email),
			UNIQUE KEY token_idx (token)
		) {$collate};"
	);

	update_option( 'azwc_fu_db_version', AZWC_FU_DB_VERSION, false );
}
add_action( 'init', 'azwc_fu_install', 1 );

/** Where booking notifications go. Option so it can change without a deploy. */
function azwc_fu_notify_email() {
	$to = get_option( 'azwc_fu_notify_email', 'info@azwebcorp.com' );
	return is_email( $to ) ? $to : 'info@azwebcorp.com';
}

function azwc_fu_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
	return substr( (string) $ip, 0, 45 );
}

/**
 * Per-IP hourly cap.
 *
 * Every endpoint here sends mail, so an open one is a spam relay pointed at
 * whatever address the caller likes. Counted against the table rather than a
 * transient so the limit survives an object-cache flush.
 */
function azwc_fu_rate_ok( $kind, $max ) {
	global $wpdb;
	$table = azwc_fu_table();

	$count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE kind = %s AND ip = %s AND created_gmt > %s",
			$kind,
			azwc_fu_ip(),
			gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS )
		)
	);

	return $count < $max;
}

function azwc_fu_new_token() {
	return substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 32 );
}

function azwc_fu_find_by_token( $token ) {
	global $wpdb;
	$token = preg_replace( '/[^a-f0-9]/i', '', (string) $token );
	if ( 32 !== strlen( $token ) ) {
		return null;
	}
	$table = azwc_fu_table();

	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token = %s", $token ) );
}


/* -------------------------------------------------------------------------
 * Time and availability
 * ---------------------------------------------------------------------- */

function azwc_fu_tz() {
	static $tz = null;
	if ( null === $tz ) {
		$tz = new DateTimeZone( AZWC_FU_TZ );
	}
	return $tz;
}

/** A UTC 'Y-m-d H:i:s' string as a DateTimeImmutable in local (Arizona) time. */
function azwc_fu_local( $gmt ) {
	$d = new DateTimeImmutable( $gmt, new DateTimeZone( 'UTC' ) );
	return $d->setTimezone( azwc_fu_tz() );
}

/** How a slot is written to a human. */
function azwc_fu_pretty( $gmt ) {
	$l   = azwc_fu_local( $gmt );
	$end = $l->modify( '+' . AZWC_FU_CALL_MIN . ' minutes' );

	return sprintf(
		'%s at %s–%s Arizona time (MST)',
		$l->format( 'l j F Y' ),
		$l->format( 'g:ia' ),
		$end->format( 'g:ia' )
	);
}

/**
 * Is this a slot we actually offer?
 *
 * Re-derived server-side for every booking. The calendar the visitor sees is
 * a convenience; this is the rule. Anything posted that does not satisfy all
 * of it is rejected, whatever the UI allowed.
 */
function azwc_fu_slot_valid( $gmt, &$why = '' ) {
	$now = time();
	$ts  = strtotime( $gmt . ' UTC' );

	if ( ! $ts ) {
		$why = 'That is not a valid time.';
		return false;
	}
	if ( $ts < $now + ( AZWC_FU_MIN_NOTICE_H * HOUR_IN_SECONDS ) ) {
		$why = sprintf( 'Please pick a time at least %d hours from now.', AZWC_FU_MIN_NOTICE_H );
		return false;
	}
	if ( $ts > $now + ( AZWC_FU_HORIZON_D * DAY_IN_SECONDS ) ) {
		$why = sprintf( 'We are only taking bookings %d days ahead at the moment.', AZWC_FU_HORIZON_D );
		return false;
	}

	$l = azwc_fu_local( $gmt );

	if ( (int) $l->format( 'N' ) >= 6 ) {
		$why = 'Calls run Monday to Friday.';
		return false;
	}
	if ( 0 !== ( (int) $l->format( 'i' ) % AZWC_FU_GRID_MIN ) || 0 !== (int) $l->format( 's' ) ) {
		$why = 'Please pick one of the offered times.';
		return false;
	}

	// The call has to finish inside the window, not merely start inside it.
	$mins     = ( (int) $l->format( 'G' ) * 60 ) + (int) $l->format( 'i' );
	$open     = AZWC_FU_OPEN_HOUR * 60;
	$last_end = AZWC_FU_CLOSE_HOUR * 60;

	if ( $mins < $open || ( $mins + AZWC_FU_CALL_MIN ) > $last_end ) {
		$why = sprintf( 'Calls run %d:00am to %d:00pm Arizona time.', AZWC_FU_OPEN_HOUR, AZWC_FU_CLOSE_HOUR - 12 );
		return false;
	}

	return true;
}

/**
 * Slots already spoken for.
 *
 * Returns UTC start strings. A held-but-unconfirmed booking counts: otherwise
 * the same slot goes out to several people and the first to confirm wins,
 * which is a worse experience than being shown it as taken.
 */
function azwc_fu_busy() {
	global $wpdb;
	$table = azwc_fu_table();

	$rows = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT slot_start_gmt FROM {$table}
			  WHERE kind = 'call'
			    AND slot_start_gmt IS NOT NULL
			    AND slot_start_gmt > %s
			    AND ( status = 'confirmed'
			          OR ( status = 'pending' AND created_gmt > %s ) )",
			gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ),
			gmdate( 'Y-m-d H:i:s', time() - ( AZWC_FU_HOLD_H * HOUR_IN_SECONDS ) )
		)
	);

	return $rows ? $rows : array();
}

/**
 * Does a 30-minute call at $gmt overlap anything already booked?
 *
 * On a 15-minute grid a 30-minute call collides with its neighbours, not just
 * with an identical start time — booking 10:00 has to close 09:45 and 10:15
 * as well. Checked as a real interval overlap so the rule survives someone
 * changing the grid or the call length.
 */
function azwc_fu_slot_free( $gmt, $busy = null ) {
	$busy = ( null === $busy ) ? azwc_fu_busy() : $busy;
	$len  = AZWC_FU_CALL_MIN * 60;
	$s    = strtotime( $gmt . ' UTC' );

	foreach ( $busy as $b ) {
		$e = strtotime( $b . ' UTC' );
		if ( $s < $e + $len && $e < $s + $len ) {
			return false;
		}
	}

	return true;
}

/**
 * The calendar the front end draws: bookable days, each with its free slots.
 */
function azwc_fu_availability() {
	$busy = azwc_fu_busy();
	$days = array();

	$cursor = ( new DateTimeImmutable( 'now', azwc_fu_tz() ) )->setTime( 0, 0 );
	$limit  = $cursor->modify( '+' . AZWC_FU_HORIZON_D . ' days' );

	while ( $cursor < $limit ) {
		if ( (int) $cursor->format( 'N' ) < 6 ) {
			$slots = array();

			for ( $m = AZWC_FU_OPEN_HOUR * 60; $m + AZWC_FU_CALL_MIN <= AZWC_FU_CLOSE_HOUR * 60; $m += AZWC_FU_GRID_MIN ) {
				$local = $cursor->setTime( intdiv( $m, 60 ), $m % 60 );
				$gmt   = $local->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );

				if ( azwc_fu_slot_valid( $gmt ) && azwc_fu_slot_free( $gmt, $busy ) ) {
					$slots[] = array(
						'gmt'   => $gmt,
						'label' => $local->format( 'g:i a' ),
					);
				}
			}

			if ( $slots ) {
				$days[] = array(
					'date'  => $cursor->format( 'Y-m-d' ),
					'label' => $cursor->format( 'D j M' ),
					'long'  => $cursor->format( 'l j F' ),
					'slots' => $slots,
				);
			}
		}

		$cursor = $cursor->modify( '+1 day' );
	}

	return $days;
}


/* -------------------------------------------------------------------------
 * Report assembly
 * ---------------------------------------------------------------------- */

/**
 * Rebuild the finished report on the server.
 *
 * The browser has the assembled report already and posting it back would be
 * one line — but then the contents of an email we send under our own domain
 * would be whatever the caller typed. Instead this reads the same transients
 * the audit endpoint writes, so every word in the PDF came from our own code.
 * Nothing the visitor sends is ever rendered into it.
 */
function azwc_fu_report( $url ) {
	if ( ! function_exists( 'azwc_audit_normalize' ) ) {
		return new WP_Error( 'azwc_fu_no_audit', 'The audit tool is not available right now.' );
	}

	$url = azwc_audit_normalize( $url );
	if ( is_wp_error( $url ) ) {
		return $url;
	}

	$key  = md5( $url );
	$site = get_transient( 'azwc_audit_site_' . $key );

	// Expired, or the visitor is deep-linking. Re-run it; this is the fast
	// half of the audit and it repopulates the same cache.
	if ( ! $site && function_exists( 'azwc_audit_stage_site' ) ) {
		$response = azwc_audit_stage_site( $url );
		if ( $response instanceof WP_REST_Response && 200 === $response->get_status() ) {
			$site = $response->get_data();
		}
	}

	if ( ! is_array( $site ) || empty( $site['checks'] ) ) {
		return new WP_Error(
			'azwc_fu_no_report',
			'We could not rebuild that report. Please run the audit again and then request the PDF.'
		);
	}

	return array(
		'url'     => $url,
		'site'    => $site,
		// Absent if PageSpeed did not answer. The PDF says so rather than
		// leaving a blank where a number should be.
		'mobile'  => get_transient( 'azwc_audit_psi_mobile_' . $key ) ?: null,
		'desktop' => get_transient( 'azwc_audit_psi_desktop_' . $key ) ?: null,
	);
}

/** Public URL for a confirm / cancel action. */
function azwc_fu_action_url( $action, $token ) {
	return add_query_arg(
		array(
			'azwc_call' => $action,
			'token'     => $token,
		),
		home_url( '/free-seo-audit/' )
	);
}
