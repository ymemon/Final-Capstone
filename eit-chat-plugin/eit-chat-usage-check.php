<?php
/**
 * List every chat session recorded on staging, newest first, so we can see
 * whether anyone (other than known internal testing) has used it.
 *
 *     wp --path=/html eval-file eit-chat-usage-check.php
 *
 * Read-only.
 */
global $wpdb;
$sessions_table = $wpdb->prefix . 'eit_chat_sessions';
$messages_table = $wpdb->prefix . 'eit_chat_messages';

$rows = $wpdb->get_results(
	"SELECT id, session_key, started_at, last_at, status, mode, page_url, referrer,
	        ip_hash, visitor_name, visitor_email, visitor_phone, lead_type, msg_count
	 FROM {$sessions_table}
	 ORDER BY started_at DESC"
);

WP_CLI::line( 'Total sessions: ' . count( $rows ) );
WP_CLI::line( '' );

foreach ( $rows as $r ) {
	WP_CLI::line( sprintf(
		'#%-4d %-19s -> %-19s msgs=%-3d status=%-6s mode=%-4s page=%s ref=%s ip_hash=%s lead=%s name=%s email=%s phone=%s',
		$r->id, $r->started_at, $r->last_at, $r->msg_count, $r->status, $r->mode,
		$r->page_url, $r->referrer, substr( $r->ip_hash, 0, 8 ), $r->lead_type,
		$r->visitor_name, $r->visitor_email, $r->visitor_phone
	) );
}
