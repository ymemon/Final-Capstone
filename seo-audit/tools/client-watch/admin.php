<?php
/**
 * A live list of what Client Watch has caught.
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
			'Client Watch',
			'Client Watch',
			'edit_posts',
			'azwc-client-watch',
			'azwc_cw_admin_page',
			'dashicons-email-alt',
			27
		);
	}
);

function azwc_cw_admin_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Not allowed.' );
	}

	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT * FROM ' . azwc_cw_table() . ' ORDER BY created_gmt DESC LIMIT 200' );

	echo '<div class="wrap"><h1>Client Watch</h1>';
	echo '<p style="font-size:14px;color:#50575e;">Emails caught in real time as they land, not on the old 15-minute check.</p>';

	if ( ! defined( 'AZWC_CW_WEBHOOK_SECRET' ) || '' === AZWC_CW_WEBHOOK_SECRET ) {
		echo '<div class="notice notice-warning"><p><strong>No webhook secret is configured.</strong> '
			. 'Add <code>define( \'AZWC_CW_WEBHOOK_SECRET\', \'...\' );</code> to wp-config.php, then point your '
			. 'Mailgun/SendGrid inbound route at:<br><code>' . esc_url( rest_url( 'azwc/v1/webhook/client-watch/YOUR_SECRET' ) ) . '</code></p></div>';
	} else {
		echo '<p style="font-size:13px;color:#6b7480;">Webhook URL: <code>'
			. esc_url( rest_url( 'azwc/v1/webhook/client-watch/' . AZWC_CW_WEBHOOK_SECRET ) ) . '</code></p>';
	}

	if ( ! $rows ) {
		echo '<p style="margin-top:18px;">Nothing caught yet.</p></div>';
		return;
	}

	echo '<table class="wp-list-table widefat fixed striped" style="margin-top:14px;"><thead><tr>'
		. '<th style="width:150px">Received</th><th style="width:90px">Via</th>'
		. '<th>From</th><th>Subject</th></tr></thead><tbody>';

	foreach ( $rows as $row ) {
		$local = get_date_from_gmt( $row->created_gmt, 'D j M, g:ia' );
		echo '<tr>';
		echo '<td><strong>' . esc_html( $local ) . '</strong></td>';
		echo '<td>' . esc_html( $row->source ) . '</td>';
		echo '<td>' . esc_html( $row->from_name ) . '<br><a href="mailto:' . esc_attr( $row->from_email ) . '">'
			. esc_html( $row->from_email ) . '</a></td>';
		echo '<td>' . esc_html( $row->subject ) . '</td>';
		echo '</tr>';
	}

	echo '</tbody></table></div>';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'azwc-client-watch',
		function () {
			global $wpdb;
			$table = azwc_cw_table();
			WP_CLI::log( 'Table:   ' . ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) ? 'present' : 'MISSING' ) );
			WP_CLI::log( 'Rows:    ' . (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) );
			WP_CLI::log( 'Secret:  ' . ( defined( 'AZWC_CW_WEBHOOK_SECRET' ) && AZWC_CW_WEBHOOK_SECRET ? 'configured' : 'NOT SET' ) );
			WP_CLI::log( 'Notify:  ' . azwc_cw_notify_email() );
		}
	);
}
