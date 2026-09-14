<?php
/**
 * Plugin Name: Everything IT — Website Chat
 * Description: Visitor chat answered by an AI assistant, with stored transcripts, staff alerts, live human takeover and lead capture.
 * Version:     0.1.0
 * Author:      AZ Web Corp
 *
 * ── LOADER ONLY ───────────────────────────────────────────────────────────
 * WordPress auto-includes EVERY top-level .php file in mu-plugins/ on EVERY
 * request. Only this small loader belongs in the root; all classes live in
 * eit-chat/includes/, which WordPress never auto-loads.
 *
 * A missing include would be a fatal error on every page load, so this file
 * checks that every required file exists BEFORE requiring any of them, and
 * bails silently if the set is incomplete (e.g. a half-finished upload).
 * That turns a site-down into a temporarily-absent chat widget.
 */

defined('ABSPATH') || exit;

define('EIT_CHAT_VERSION', '0.1.0');
define('EIT_CHAT_DIR', __DIR__ . '/eit-chat');
define('EIT_CHAT_URL', content_url('mu-plugins/eit-chat'));

/**
 * Load only if the whole set is present — never a partial load.
 */
$eit_chat_files = [
    EIT_CHAT_DIR . '/includes/class-eit-chat-store.php',
    EIT_CHAT_DIR . '/includes/class-eit-chat-kb.php',
    EIT_CHAT_DIR . '/includes/class-eit-chat-brain.php',
    EIT_CHAT_DIR . '/includes/class-eit-chat-rest.php',
    EIT_CHAT_DIR . '/includes/class-eit-chat-widget.php',
    EIT_CHAT_DIR . '/includes/class-eit-chat-admin.php',
];

foreach ($eit_chat_files as $eit_chat_file) {
    if (!file_exists($eit_chat_file)) {
        return; // incomplete install — do nothing rather than fatal
    }
}
foreach ($eit_chat_files as $eit_chat_file) {
    require_once $eit_chat_file;
}
unset($eit_chat_files, $eit_chat_file);

add_action('plugins_loaded',      ['EIT_Chat_Store',  'maybe_install']);
add_action('rest_api_init',       ['EIT_Chat_REST',   'register']);
add_action('wp_enqueue_scripts',  ['EIT_Chat_Widget', 'assets']);
add_action('wp_footer',           ['EIT_Chat_Widget', 'render']);
add_action('admin_menu',          ['EIT_Chat_Admin',  'menu']);
add_action('admin_enqueue_scripts', ['EIT_Chat_Admin','assets']);
add_action('admin_bar_menu',      ['EIT_Chat_Admin',  'admin_bar'], 100);
