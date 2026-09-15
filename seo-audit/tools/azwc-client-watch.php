<?php
/**
 * AZ Web Corp — Client Watch (real-time rebuild).
 *
 * IMPORTANT CONTEXT FOR WHOEVER READS THIS NEXT
 * This file does not reverse-engineer the original "Azwebcorp client watch"
 * plugin — that plugin's source lives only on the production server and was
 * not available when this was written (no server login, no filesystem
 * access). This is a clean replacement built to do the same job — notice a
 * client email the moment it lands at info@azwebcorp.com — without waiting
 * on a 15-minute WP-Cron poll. Review it against the real plugin's behaviour
 * before retiring that plugin; do not assume the two are behaviourally
 * identical beyond "logs the email and notifies the team fast."
 *
 * WHY THIS CANNOT POLL IMAP EVERY FEW SECONDS
 * The mailbox is Titan (GoDaddy), which is plain IMAP/SMTP hosting with no
 * push API of its own, and WordPress here runs on hosting with no persistent
 * background process (WP-Cron only fires on a visitor hit). A true IMAP IDLE
 * listener needs a long-running process this hosting cannot provide. The
 * mechanism that IS real-time without a VPS: a mailbox rule on the Titan side
 * forwards a copy of every incoming message to Mailgun or SendGrid's inbound
 * parse address, and that service calls the webhook below within seconds of
 * receiving it. Setting up that forwarding rule is a Titan webmail step this
 * file cannot do for you — see client-watch/README.md.
 *
 * Loader-only, same reason as azwc-followup.php: mu-plugins are included on
 * every request, so real work stays in the client-watch/ subdirectory.
 *
 * @package AZWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AZWC_CW_VERSION', '1.0.0' );
define( 'AZWC_CW_DB_VERSION', '1' );
define( 'AZWC_CW_DIR', __DIR__ . '/client-watch' );

foreach ( array( 'core', 'rest', 'admin' ) as $azwc_cw_part ) {
	$azwc_cw_file = AZWC_CW_DIR . '/' . $azwc_cw_part . '.php';
	if ( is_readable( $azwc_cw_file ) ) {
		require_once $azwc_cw_file;
	}
}
unset( $azwc_cw_part, $azwc_cw_file );
