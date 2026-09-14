<?php
/**
 * Read-only "Tickets" screen in wp-admin — same spirit as the follow-up
 * plugin's "SEO Leads" screen. All writes happen through WP-CLI (cli.php)
 * from the scheduled mailbox job or from the nudge landing page (rest.php);
 * this page only displays state.
 *
 * @package AZWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'azwc_tk_admin_menu' );

function azwc_tk_admin_menu() {
	add_menu_page(
		'Tickets',
		'Tickets',
		'manage_options',
		'azwc-tickets',
		'azwc_tk_admin_page',
		'dashicons-tickets-alt',
		27
	);
}

function azwc_tk_admin_page() {
	global $wpdb;
	$table = azwc_tk_table();

	$status_filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$where         = '';
	if ( $status_filter && in_array( $status_filter, array( 'waiting_us', 'waiting_client', 'resolved' ), true ) ) {
		$where = $wpdb->prepare( ' WHERE status = %s', $status_filter );
	}

	$rows = $wpdb->get_results( "SELECT * FROM {$table}{$where} ORDER BY last_activity_gmt DESC LIMIT 300" );

	$counts = $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status", OBJECT_K );

	echo '<div class="wrap"><h1>Tickets</h1>';
	echo '<p style="color:#646970">Every client email thread through info@ / requests@azwebcorp.com, numbered and tracked. '
		. 'Populated by the scheduled mailbox check, not edited here.</p>';

	echo '<ul class="subsubsub">';
	$tabs = array(
		''               => 'All',
		'waiting_us'     => 'Waiting on us',
		'waiting_client' => 'Waiting on client',
		'resolved'       => 'Resolved',
	);
	$links = array();
	foreach ( $tabs as $key => $label ) {
		$n      = '' === $key ? array_sum( wp_list_pluck( $counts, 'n' ) ) : ( isset( $counts[ $key ] ) ? $counts[ $key ]->n : 0 );
		$url    = esc_url( add_query_arg( 'status', $key, admin_url( 'admin.php?page=azwc-tickets' ) ) );
		$class  = ( $status_filter === $key ) ? ' class="current"' : '';
		$links[] = '<li><a href="' . $url . '"' . $class . '>' . esc_html( $label ) . ' <span class="count">(' . (int) $n . ')</span></a></li>';
	}
	echo implode( ' | ', $links );
	echo '</ul><div style="clear:both"></div>';

	echo '<table class="wp-list-table widefat fixed striped">';
	echo '<thead><tr>'
		. '<th style="width:90px">Ticket</th><th>Subject</th><th>Who</th>'
		. '<th style="width:140px">Status</th><th style="width:130px">Last activity</th><th style="width:100px">Nudge</th>'
		. '</tr></thead><tbody>';

	if ( ! $rows ) {
		echo '<tr><td colspan="6">No tickets yet.</td></tr>';
	}

	$badge = array(
		'waiting_us'     => '#d64545',
		'waiting_client' => '#e8a33d',
		'resolved'       => '#0f9d58',
	);

	foreach ( $rows as $row ) {
		$age = human_time_diff( strtotime( $row->last_activity_gmt . ' UTC' ) );
		$color = $badge[ $row->status ] ?? '#6b7480';

		echo '<tr>';
		echo '<td><b>' . esc_html( $row->ticket_no ) . '</b></td>';
		echo '<td>' . esc_html( $row->subject ) . '</td>';
		echo '<td>' . esc_html( $row->counterparty_name ) . '<br><span style="color:#646970">' . esc_html( $row->counterparty_email ) . '</span></td>';
		echo '<td><span style="color:' . esc_attr( $color ) . ';font-weight:700">' . esc_html( str_replace( '_', ' ', $row->status ) ) . '</span></td>';
		echo '<td>' . esc_html( $age ) . ' ago</td>';
		echo '<td>' . esc_html( str_replace( '_', ' ', $row->nudge_status ) ) . '</td>';
		echo '</tr>';

		if ( $row->notes ) {
			echo '<tr><td></td><td colspan="5" style="color:#646970;white-space:pre-wrap;font-size:12.5px;padding-top:0">' . esc_html( $row->notes ) . '</td></tr>';
		}
	}

	echo '</tbody></table></div>';
}
