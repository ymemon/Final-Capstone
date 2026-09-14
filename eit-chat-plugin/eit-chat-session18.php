<?php
/**
 * Full message log for session 18, whose last_at (2026-09-07) is days after
 * it started (2026-09-03) - checking whether real new activity landed there
 * after the transcript was already emailed to the client.
 *
 *     wp --path=/html eval-file eit-chat-session18.php
 *
 * Read-only.
 */
global $wpdb;
$messages_table = $wpdb->prefix . 'eit_chat_messages';

$rows = $wpdb->get_results( $wpdb->prepare(
	"SELECT id, role, body, created_at FROM {$messages_table} WHERE session_id = %d ORDER BY id ASC",
	18
) );

foreach ( $rows as $r ) {
	WP_CLI::line( sprintf( '#%-3d %s [%s] %s', $r->id, $r->created_at, $r->role, substr( str_replace( "\n", ' ', $r->body ), 0, 200 ) ) );
}
