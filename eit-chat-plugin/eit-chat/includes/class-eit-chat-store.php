<?php
/**
 * Storage layer: two tables, plus the option defaults.
 *
 * Transcripts are real rows rather than postmeta so that a busy chat never
 * bloats wp_posts, and so "show me every chat this week" is one indexed query.
 */

defined('ABSPATH') || exit;

class EIT_Chat_Store {

    const DB_VERSION = '1';

    /* ── option helpers ──────────────────────────────────────────────── */

    const DEFAULTS = [
        'eit_chat_enabled'        => '1',
        'eit_chat_api_key'        => '',          // empty = demo mode (no API spend)
        'eit_chat_model'          => 'claude-sonnet-5',
        'eit_chat_assistant_name' => 'Niall',
        'eit_chat_notify_email'   => '',          // staff alert recipient
        'eit_chat_daily_cap'      => '200',       // hard stop on conversations/day
    ];

    public static function opt($key, $fallback = null) {
        $default = $fallback !== null ? $fallback : (self::DEFAULTS[$key] ?? '');
        return get_option($key, $default);
    }

    public static function sessions_table() {
        global $wpdb;
        return $wpdb->prefix . 'eit_chat_sessions';
    }

    public static function messages_table() {
        global $wpdb;
        return $wpdb->prefix . 'eit_chat_messages';
    }

    /* ── install ─────────────────────────────────────────────────────── */

    public static function maybe_install() {
        if (get_option('eit_chat_db_version') === self::DB_VERSION) {
            return;
        }
        self::install();
        update_option('eit_chat_db_version', self::DB_VERSION, false);
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $s = self::sessions_table();
        $m = self::messages_table();

        dbDelta("CREATE TABLE {$s} (
            id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_key     varchar(64)  NOT NULL,
            started_at      datetime     NOT NULL,
            last_at         datetime     NOT NULL,
            ended_at        datetime     NULL,
            mode            varchar(12)  NOT NULL DEFAULT 'ai',
            status          varchar(12)  NOT NULL DEFAULT 'open',
            page_url        varchar(255) NOT NULL DEFAULT '',
            referrer        varchar(255) NOT NULL DEFAULT '',
            ip_hash         varchar(64)  NOT NULL DEFAULT '',
            visitor_name    varchar(120) NOT NULL DEFAULT '',
            visitor_email   varchar(190) NOT NULL DEFAULT '',
            visitor_phone   varchar(60)  NOT NULL DEFAULT '',
            lead_type       varchar(24)  NOT NULL DEFAULT '',
            lead_note       text         NULL,
            staff_seen      tinyint(1)   NOT NULL DEFAULT 0,
            msg_count       smallint(5)  unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY session_key (session_key),
            KEY status_last (status, last_at),
            KEY started_at (started_at)
        ) {$charset};");

        dbDelta("CREATE TABLE {$m} (
            id          bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id  bigint(20) unsigned NOT NULL,
            role        varchar(12) NOT NULL,
            body        text        NOT NULL,
            created_at  datetime    NOT NULL,
            PRIMARY KEY  (id),
            KEY session_created (session_id, id)
        ) {$charset};");

        foreach (self::DEFAULTS as $k => $v) {
            add_option($k, $v, '', false);
        }
    }

    /* ── sessions ────────────────────────────────────────────────────── */

    public static function create_session($page_url, $referrer, $ip_hash) {
        global $wpdb;
        $now = current_time('mysql');
        $key = wp_generate_password(32, false, false);

        $ok = $wpdb->insert(self::sessions_table(), [
            'session_key' => $key,
            'started_at'  => $now,
            'last_at'     => $now,
            'page_url'    => substr((string) $page_url, 0, 255),
            'referrer'    => substr((string) $referrer, 0, 255),
            'ip_hash'     => $ip_hash,
        ], ['%s','%s','%s','%s','%s','%s']);

        if (!$ok) {
            return null;
        }
        return self::get_session_by_key($key);
    }

    public static function get_session_by_key($key) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::sessions_table() . " WHERE session_key = %s", $key
        ), ARRAY_A);
    }

    public static function get_session($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::sessions_table() . " WHERE id = %d", $id
        ), ARRAY_A);
    }

    public static function update_session($id, array $fields) {
        global $wpdb;
        $fields['last_at'] = current_time('mysql');
        return $wpdb->update(self::sessions_table(), $fields, ['id' => (int) $id]);
    }

    public static function touch_session($id) {
        return self::update_session($id, []);
    }

    /**
     * Sessions that are still open and had activity within the window —
     * this is what the staff "someone is chatting now" panel polls.
     */
    public static function live_sessions($within_minutes = 15) {
        global $wpdb;
        $cut = gmdate('Y-m-d H:i:s', current_time('timestamp') - ($within_minutes * 60));
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::sessions_table() . "
             WHERE status = 'open' AND last_at >= %s
             ORDER BY last_at DESC LIMIT 50", $cut
        ), ARRAY_A);
    }

    public static function recent_sessions($limit = 50, $offset = 0) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::sessions_table() . "
             ORDER BY started_at DESC LIMIT %d OFFSET %d", $limit, $offset
        ), ARRAY_A);
    }

    public static function count_sessions_today() {
        global $wpdb;
        $start = date('Y-m-d 00:00:00', current_time('timestamp'));
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::sessions_table() . " WHERE started_at >= %s", $start
        ));
    }

    /* ── messages ────────────────────────────────────────────────────── */

    public static function add_message($session_id, $role, $body) {
        global $wpdb;
        $wpdb->insert(self::messages_table(), [
            'session_id' => (int) $session_id,
            'role'       => $role,
            'body'       => $body,
            'created_at' => current_time('mysql'),
        ], ['%d','%s','%s','%s']);

        $wpdb->query($wpdb->prepare(
            "UPDATE " . self::sessions_table() . "
             SET msg_count = msg_count + 1, last_at = %s WHERE id = %d",
            current_time('mysql'), (int) $session_id
        ));

        return (int) $wpdb->insert_id;
    }

    public static function get_messages($session_id, $after_id = 0) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, role, body, created_at FROM " . self::messages_table() . "
             WHERE session_id = %d AND id > %d ORDER BY id ASC",
            (int) $session_id, (int) $after_id
        ), ARRAY_A);
    }
}
