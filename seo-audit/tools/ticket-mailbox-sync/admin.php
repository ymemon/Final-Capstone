<?php
/**
 * Ticket list and manual status control.
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
			'Ticket Mailbox',
			'Ticket Mailbox',
			'edit_posts',
			'azwc-ticket-mailbox',
			'azwc_tms_admin_page',
			'dashicons-tickets-alt',
			28
		);
	}
);

add_action(
	'admin_post_azwc_tms_set_status',
	function () {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'azwc_tms_set_status' );

		$id     = isset( $_POST['ticket_id'] ) ? (int) $_POST['ticket_id'] : 0;
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

		if ( $id && in_array( $status, array( 'open', 'resolved', 'closed' ), true ) ) {
			global $wpdb;
			$wpdb->update( azwc_tms_tickets_table(), array( 'status' => $status ), array( 'id' => $id ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=azwc-ticket-mailbox' ) );
		exit;
	}
);

function azwc_tms_admin_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Not allowed.' );
	}

	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT * FROM ' . azwc_tms_tickets_table() . ' ORDER BY last_client_gmt DESC LIMIT 200' );

	echo '<div class="wrap"><h1>Ticket Mailbox</h1>';
	echo '<p style="font-size:14px;color:#50575e;">Tickets open in real time from incoming client email. '
		. 'This has no visibility into replies sent from your own inbox — mark a ticket resolved here once it\'s handled, '
		. 'or it keeps nudging the client.</p>';

	if ( ! defined( 'AZWC_TMS_WEBHOOK_SECRET' ) || '' === AZWC_TMS_WEBHOOK_SECRET ) {
		echo '<div class="notice notice-warning"><p><strong>No webhook secret is configured.</strong> '
			. 'Add <code>define( \'AZWC_TMS_WEBHOOK_SECRET\', \'...\' );</code> to wp-config.php, then point your '
			. 'Mailgun/SendGrid inbound route at:<br><code>' . esc_url( rest_url( 'azwc/v1/webhook/ticket-mailbox/YOUR_SECRET' ) ) . '</code></p></div>';
	}

	if ( ! $rows ) {
		echo '<p style="margin-top:18px;">No tickets yet.</p></div>';
		return;
	}

	echo '<table class="wp-list-table widefat fixed striped" style="margin-top:14px;"><thead><tr>'
		. '<th style="width:100px">Ticket</th><th style="width:150px">Last contact</th>'
		. '<th style="width:100px">Status</th><th>Client</th><th style="width:80px">Nudges</th>'
		. '<th style="width:220px">Action</th></tr></thead><tbody>';

	$colours = array( 'open' => '#c8871f', 'resolved' => '#0f9d58', 'closed' => '#8a919b' );

	foreach ( $rows as $row ) {
		$number = azwc_tms_format_number( $row->id );
		$colour = isset( $colours[ $row->status ] ) ? $colours[ $row->status ] : '#8a919b';
		$local  = $row->last_client_gmt ? get_date_from_gmt( $row->last_client_gmt, 'D j M, g:ia' ) : '—';

		echo '<tr>';
		echo '<td><strong>' . esc_html( $number ) . '</strong></td>';
		echo '<td>' . esc_html( $local ) . '</td>';
		echo '<td><span style="display:inline-block;padding:2px 9px;border-radius:9px;font-size:11px;font-weight:700;'
			. 'text-transform:uppercase;letter-spacing:.05em;color:#fff;background:' . esc_attr( $colour ) . ';">'
			. esc_html( $row->status ) . '</span></td>';
		echo '<td>' . esc_html( $row->client_name ) . '<br><a href="mailto:' . esc_attr( $row->client_email ) . '">'
			. esc_html( $row->client_email ) . '</a></td>';
		echo '<td>' . (int) $row->nudge_count . '</td>';

		echo '<td>';
		foreach ( array( 'open', 'resolved', 'closed' ) as $status ) {
			if ( $status === $row->status ) {
				continue;
			}
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:4px;">';
			wp_nonce_field( 'azwc_tms_set_status' );
			echo '<input type="hidden" name="action" value="azwc_tms_set_status">';
			echo '<input type="hidden" name="ticket_id" value="' . (int) $row->id . '">';
			echo '<input type="hidden" name="status" value="' . esc_attr( $status ) . '">';
			echo '<button type="submit" class="button button-small">Mark ' . esc_html( $status ) . '</button>';
			echo '</form>';
		}
		echo '</td>';

		echo '</tr>';
	}

	echo '</tbody></table></div>';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'azwc-ticket-mailbox',
		function () {
			global $wpdb;
			$table = azwc_tms_tickets_table();
			WP_CLI::log( 'Table:    ' . ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) ? 'present' : 'MISSING' ) );
			WP_CLI::log( 'Tickets:  ' . (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) );
			WP_CLI::log( 'Open:     ' . (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'open'" ) );
			WP_CLI::log( 'Secret:   ' . ( defined( 'AZWC_TMS_WEBHOOK_SECRET' ) && AZWC_TMS_WEBHOOK_SECRET ? 'configured' : 'NOT SET' ) );
			$next = wp_next_scheduled( 'azwc_tms_nudge_tick' );
			WP_CLI::log( 'Next nudge tick: ' . ( $next ? gmdate( 'Y-m-d H:i:s', $next ) . ' UTC' : 'NOT SCHEDULED' ) );
		}
	);
}
