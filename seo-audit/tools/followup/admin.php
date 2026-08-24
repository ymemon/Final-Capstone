<?php
/**
 * Somewhere to see the leads that is not an inbox.
 *
 * @package AZWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	function () {
		add_menu_page(
			'SEO Leads',
			'SEO Leads',
			'edit_posts',
			'azwc-leads',
			'azwc_fu_admin_page',
			'dashicons-phone',
			26
		);
	}
);

function azwc_fu_admin_badge( $status ) {
	$colours = array(
		'confirmed' => '#0f9d58',
		'pending'   => '#c8871f',
		'sent'      => '#3b6fd4',
		'cancelled' => '#8a919b',
		'expired'   => '#8a919b',
	);
	$bg = isset( $colours[ $status ] ) ? $colours[ $status ] : '#8a919b';

	return '<span style="display:inline-block;padding:2px 9px;border-radius:9px;font-size:11px;'
		. 'font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#fff;background:'
		. esc_attr( $bg ) . '">' . esc_html( $status ) . '</span>';
}

function azwc_fu_admin_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Not allowed.' );
	}

	global $wpdb;
	$table = azwc_fu_table();

	// phpcs:ignore WordPress.Security.NonceVerification -- read-only filter.
	$kind  = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : 'call';
	$kind  = in_array( $kind, array( 'call', 'report' ), true ) ? $kind : 'call';

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE kind = %s ORDER BY COALESCE(slot_start_gmt, created_gmt) DESC LIMIT 200",
			$kind
		)
	);

	$upcoming = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE kind = 'call' AND status = 'confirmed' AND slot_start_gmt > %s",
			gmdate( 'Y-m-d H:i:s' )
		)
	);

	echo '<div class="wrap"><h1>SEO Leads</h1>';
	echo '<p style="font-size:14px;color:#50575e;">'
		. sprintf(
			/* translators: %d: number of confirmed upcoming calls. */
			esc_html__( '%d confirmed call(s) still to come. All times below are Arizona time.', 'default' ),
			$upcoming
		)
		. '</p>';

	echo '<h2 class="nav-tab-wrapper">';
	foreach ( array( 'call' => 'Call bookings', 'report' => 'Report requests' ) as $k => $label ) {
		printf(
			'<a href="%s" class="nav-tab%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=azwc-leads&kind=' . $k ) ),
			$kind === $k ? ' nav-tab-active' : '',
			esc_html( $label )
		);
	}
	echo '</h2>';

	if ( ! $rows ) {
		echo '<p style="margin-top:18px;">Nothing here yet.</p></div>';
		return;
	}

	echo '<table class="wp-list-table widefat fixed striped" style="margin-top:14px;"><thead><tr>'
		. '<th style="width:150px">' . ( 'call' === $kind ? 'When' : 'Requested' ) . '</th>'
		. '<th style="width:100px">Status</th><th>Who</th><th>Site</th>'
		. '<th style="width:70px">Score</th><th style="width:130px">Phone</th>'
		. '</tr></thead><tbody>';

	foreach ( $rows as $row ) {
		$when = ( 'call' === $kind && $row->slot_start_gmt ) ? $row->slot_start_gmt : $row->created_gmt;
		$past = strtotime( $when . ' UTC' ) < time();

		echo '<tr' . ( $past ? ' style="opacity:.62"' : '' ) . '>';
		echo '<td><strong>' . esc_html( azwc_fu_local( $when )->format( 'D j M, g:ia' ) ) . '</strong></td>';
		echo '<td>' . azwc_fu_admin_badge( $row->status ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<td>' . esc_html( $row->name ) . '<br><a href="mailto:' . esc_attr( $row->email ) . '">'
			. esc_html( $row->email ) . '</a></td>';
		echo '<td><a href="' . esc_url( 'https://' . $row->domain ) . '" target="_blank" rel="noopener">'
			. esc_html( $row->domain ) . '</a></td>';
		echo '<td>' . ( null === $row->score ? '&mdash;' : (int) $row->score ) . '</td>';
		echo '<td>' . ( $row->phone ? esc_html( $row->phone ) : '&mdash;' ) . '</td>';
		echo '</tr>';
	}

	echo '</tbody></table></div>';
}


/**
 * CLI, because checking a booking should not need a browser.
 *
 *   wp azwc-leads list
 *   wp azwc-leads health
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'azwc-leads',
		function ( $args ) {
			global $wpdb;
			$table = azwc_fu_table();
			$sub   = isset( $args[0] ) ? $args[0] : 'list';

			if ( 'health' === $sub ) {
				WP_CLI::log( 'Table:      ' . ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) ? 'present' : 'MISSING' ) );
				WP_CLI::log( 'Rows:       ' . (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) );
				WP_CLI::log( 'PDF engine: ' . ( azwc_fu_dompdf_ready() ? 'dompdf ready' : 'MISSING - will send HTML instead' ) );
				WP_CLI::log( 'Notify to:  ' . azwc_fu_notify_email() );
				$next = wp_next_scheduled( 'azwc_fu_tick' );
				WP_CLI::log( 'Next tick:  ' . ( $next ? gmdate( 'Y-m-d H:i:s', $next ) . ' UTC' : 'NOT SCHEDULED' ) );
				$days = azwc_fu_availability();
				WP_CLI::log( 'Open days:  ' . count( $days ) . ' (' . array_sum( array_map( function ( $d ) {
					return count( $d['slots'] );
				}, $days ) ) . ' slots)' );
				return;
			}

			$rows = $wpdb->get_results( "SELECT id,kind,status,name,email,domain,slot_start_gmt,created_gmt FROM {$table} ORDER BY id DESC LIMIT 40", ARRAY_A );
			if ( ! $rows ) {
				WP_CLI::success( 'No leads yet.' );
				return;
			}
			WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'kind', 'status', 'name', 'email', 'domain', 'slot_start_gmt', 'created_gmt' ) );
		}
	);
}
