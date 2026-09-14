<?php
/**
 * Plugin Name: Prestige Security Headers
 * Description: Adds baseline HTTP security headers. Deliberately limited to
 * headers with no plausible functional risk to the site (no
 * Content-Security-Policy or Cross-Origin-Embedder-Policy here — both can
 * silently break third-party embeds, fonts, or scripts, and need a full
 * inventory of what the site actually loads before being written safely).
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('send_headers', function () {
    if (headers_sent()) {
        return;
    }
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
});
