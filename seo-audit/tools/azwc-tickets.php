<?php
/**
 * AZ Web Corp — client email ticket tracking.
 *
 * Turns every client email thread (in or out of info@azwebcorp.com, which
 * requests@azwebcorp.com aliases into) into a numbered, trackable ticket, so
 * nothing sent or received gets forgotten. This plugin owns only the state
 * (the database table, ticket numbering, status transitions, the nudge
 * approval landing page, and the admin list) — the actual mailbox reading
 * happens elsewhere (a scheduled job with real IMAP access, since this
 * server's outbound sockets are locked down to port 443 only; confirmed by
 * direct test, see SESSION-STATUS.md 2026-08-25). That job talks to this
 * plugin exclusively through the `wp azwc-tickets ...` WP-CLI commands in
 * tickets/cli.php.
 *
 * WHY THIS IS A LOADER AND NOTHING ELSE — see azwc-followup.php in this same
 * directory for the incident that makes this rule non-negotiable on this
 * site: everything that does real work lives in tickets/, this file only
 * requires those parts.
 *
 * @package AZWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AZWC_TK_VERSION', '1.0.0' );
define( 'AZWC_TK_DB_VERSION', '2' );
define( 'AZWC_TK_DIR', __DIR__ . '/tickets' );
define( 'AZWC_TK_PREFIX', 'AZW' );
define( 'AZWC_TK_START_SEQ', 1001 );
define( 'AZWC_TK_STALE_HOURS', 48 );

// 'client' loads after 'rest' because it reuses azwc_tk_page() from it.
foreach ( array( 'core', 'cli', 'api', 'mail', 'rest', 'client', 'admin' ) as $azwc_tk_part ) {
	$azwc_tk_file = AZWC_TK_DIR . '/' . $azwc_tk_part . '.php';
	if ( is_readable( $azwc_tk_file ) ) {
		require_once $azwc_tk_file;
	}
}
unset( $azwc_tk_part, $azwc_tk_file );
