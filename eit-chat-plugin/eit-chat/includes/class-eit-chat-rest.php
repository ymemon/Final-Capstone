<?php
/**
 * REST API.
 *
 * Public routes are unauthenticated by necessity (website visitors), so the
 * session key is the bearer secret and every route is rate limited. A public
 * endpoint sitting in front of a metered API is an abuse target: the caps
 * here are the first line of defence, and the account spend limit is the
 * backstop.
 */

defined('ABSPATH') || exit;

class EIT_Chat_REST {

    const NS = 'eit/v1';

    const MAX_SESSIONS_PER_IP_DAY = 8;
    const MAX_MESSAGES_PER_SESSION = 30;
    const MIN_SECONDS_BETWEEN_MESSAGES = 1;
    const MAX_MESSAGE_CHARS = 1000;

    public static function register() {
        $public = ['permission_callback' => '__return_true'];
        $admin  = ['permission_callback' => [__CLASS__, 'can_manage']];

        register_rest_route(self::NS, '/chat/start', array_merge($public, [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'start'],
        ]));

        register_rest_route(self::NS, '/chat/message', array_merge($public, [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'message'],
        ]));

        register_rest_route(self::NS, '/chat/poll', array_merge($public, [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'poll'],
        ]));

        register_rest_route(self::NS, '/chat/lead', array_merge($public, [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'lead'],
        ]));

        register_rest_route(self::NS, '/chat/end', array_merge($public, [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'end'],
        ]));

        // Staff side
        register_rest_route(self::NS, '/chat/admin/live', array_merge($admin, [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'admin_live'],
        ]));

        register_rest_route(self::NS, '/chat/admin/thread', array_merge($admin, [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'admin_thread'],
        ]));

        register_rest_route(self::NS, '/chat/admin/reply', array_merge($admin, [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'admin_reply'],
        ]));

        register_rest_route(self::NS, '/chat/admin/rebuild-kb', array_merge($admin, [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'admin_rebuild_kb'],
        ]));
    }

    public static function can_manage() {
        return current_user_can('edit_pages');
    }

    /* ── helpers ─────────────────────────────────────────────────────── */

    private static function ip_hash() {
        $ip = '';
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = explode(',', $_SERVER[$h])[0];
                break;
            }
        }
        // Store a hash, never the address itself.
        return hash('sha256', trim($ip) . '|' . wp_salt('auth'));
    }

    private static function enabled() {
        return EIT_Chat_Store::opt('eit_chat_enabled') === '1';
    }

    private static function session_from_request($req) {
        $key = sanitize_text_field((string) $req->get_param('key'));
        if ($key === '') {
            return null;
        }
        return EIT_Chat_Store::get_session_by_key($key);
    }

    private static function err($code, $message, $status = 400) {
        return new WP_Error($code, $message, ['status' => $status]);
    }

    /* ── public routes ───────────────────────────────────────────────── */

    public static function start($req) {
        if (!self::enabled()) {
            return self::err('eit_chat_disabled', 'Chat is currently switched off.', 503);
        }

        $cap = (int) EIT_Chat_Store::opt('eit_chat_daily_cap');
        if ($cap > 0 && EIT_Chat_Store::count_sessions_today() >= $cap) {
            return self::err('eit_chat_daily_cap', 'Chat is unavailable right now.', 429);
        }

        $ip  = self::ip_hash();
        $bkt = 'eit_chat_ip_' . substr($ip, 0, 20);
        $n   = (int) get_transient($bkt);
        if ($n >= self::MAX_SESSIONS_PER_IP_DAY) {
            return self::err('eit_chat_rate', 'Too many chats from this connection today.', 429);
        }
        set_transient($bkt, $n + 1, DAY_IN_SECONDS);

        $session = EIT_Chat_Store::create_session(
            esc_url_raw((string) $req->get_param('page_url')),
            esc_url_raw((string) $req->get_param('referrer')),
            $ip
        );
        if (!$session) {
            return self::err('eit_chat_store', 'Could not start the chat.', 500);
        }

        $greeting = EIT_Chat_Brain::greeting();
        EIT_Chat_Store::add_message($session['id'], 'assistant', $greeting);

        return rest_ensure_response([
            'key'      => $session['session_key'],
            'name'     => EIT_Chat_Brain::assistant_name(),
            'greeting' => $greeting,
            'live'     => EIT_Chat_Brain::is_live(),
        ]);
    }

    public static function message($req) {
        if (!self::enabled()) {
            return self::err('eit_chat_disabled', 'Chat is currently switched off.', 503);
        }

        $session = self::session_from_request($req);
        if (!$session) {
            return self::err('eit_chat_no_session', 'Chat session not found.', 404);
        }
        if ($session['status'] !== 'open') {
            return self::err('eit_chat_closed', 'This chat has ended.', 409);
        }
        if ((int) $session['msg_count'] >= self::MAX_MESSAGES_PER_SESSION * 2) {
            return self::err('eit_chat_too_long', 'This chat has reached its limit.', 429);
        }

        $text = trim((string) $req->get_param('text'));
        if ($text === '') {
            return self::err('eit_chat_empty', 'Message is empty.');
        }
        if (mb_strlen($text) > self::MAX_MESSAGE_CHARS) {
            $text = mb_substr($text, 0, self::MAX_MESSAGE_CHARS);
        }
        $text = wp_strip_all_tags($text);

        // Simple flood guard per session.
        $throttle = 'eit_chat_t_' . substr($session['session_key'], 0, 20);
        if (get_transient($throttle)) {
            return self::err('eit_chat_slow_down', 'Sending too quickly.', 429);
        }
        set_transient($throttle, 1, self::MIN_SECONDS_BETWEEN_MESSAGES);

        EIT_Chat_Store::add_message($session['id'], 'visitor', $text);

        // Alert staff on the first real message rather than on widget open,
        // so an idle open never pings anyone.
        if (!(int) $session['staff_seen'] && (int) $session['msg_count'] <= 2) {
            self::notify_staff($session, $text);
            EIT_Chat_Store::update_session($session['id'], ['staff_seen' => 1]);
        }

        // A human has taken the conversation — the assistant stays quiet.
        $session = EIT_Chat_Store::get_session($session['id']);
        if ($session['mode'] === 'staff') {
            return rest_ensure_response([
                'mode'    => 'staff',
                'reply'   => null,
                'handoff' => false,
            ]);
        }

        $history = EIT_Chat_Store::get_messages($session['id']);
        // Drop the message just stored; it is passed separately as the question.
        array_pop($history);

        $answer = EIT_Chat_Brain::answer($session, $history, $text);
        $reply  = EIT_Chat_Brain::strip_handoff($answer['reply']);

        EIT_Chat_Store::add_message($session['id'], 'assistant', $reply);

        return rest_ensure_response([
            'mode'    => 'ai',
            'reply'   => $reply,
            'handoff' => (bool) $answer['handoff'],
        ]);
    }

    /**
     * Long-ish poll for staff messages arriving while the visitor waits.
     */
    public static function poll($req) {
        $session = self::session_from_request($req);
        if (!$session) {
            return self::err('eit_chat_no_session', 'Chat session not found.', 404);
        }
        $after = (int) $req->get_param('after');

        $rows = EIT_Chat_Store::get_messages($session['id'], $after);
        $out  = [];
        foreach ($rows as $r) {
            if ($r['role'] === 'staff') {
                $out[] = ['id' => (int) $r['id'], 'role' => 'staff', 'body' => $r['body']];
            }
        }

        return rest_ensure_response([
            'mode'     => $session['mode'],
            'status'   => $session['status'],
            'messages' => $out,
        ]);
    }

    public static function lead($req) {
        $session = self::session_from_request($req);
        if (!$session) {
            return self::err('eit_chat_no_session', 'Chat session not found.', 404);
        }

        $name  = sanitize_text_field((string) $req->get_param('name'));
        $email = sanitize_email((string) $req->get_param('email'));
        $phone = sanitize_text_field((string) $req->get_param('phone'));
        $type  = sanitize_text_field((string) $req->get_param('type'));
        $note  = wp_strip_all_tags((string) $req->get_param('note'));

        if ($email === '' && $phone === '') {
            return self::err('eit_chat_no_contact', 'Please give an email address or a phone number.');
        }
        if ($email !== '' && !is_email($email)) {
            return self::err('eit_chat_bad_email', 'That email address does not look right.');
        }

        EIT_Chat_Store::update_session($session['id'], [
            'visitor_name'  => mb_substr($name, 0, 120),
            'visitor_email' => mb_substr($email, 0, 190),
            'visitor_phone' => mb_substr($phone, 0, 60),
            'lead_type'     => in_array($type, ['callback', 'quote', 'support', 'general'], true) ? $type : 'general',
            'lead_note'     => mb_substr($note, 0, 2000),
        ]);

        EIT_Chat_Store::add_message(
            $session['id'], 'system',
            sprintf('Contact details left — %s%s%s',
                $name !== '' ? $name . ' ' : '',
                $email !== '' ? '<' . $email . '> ' : '',
                $phone !== '' ? $phone : ''
            )
        );

        self::notify_lead(EIT_Chat_Store::get_session($session['id']));

        return rest_ensure_response([
            'ok'      => true,
            'message' => 'Thanks — someone from the team will come back to you.',
        ]);
    }

    public static function end($req) {
        $session = self::session_from_request($req);
        if (!$session) {
            return self::err('eit_chat_no_session', 'Chat session not found.', 404);
        }
        EIT_Chat_Store::update_session($session['id'], [
            'status'   => 'closed',
            'ended_at' => current_time('mysql'),
        ]);
        return rest_ensure_response(['ok' => true]);
    }

    /* ── staff routes ────────────────────────────────────────────────── */

    public static function admin_live() {
        $rows = EIT_Chat_Store::live_sessions(15);
        $out  = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'        => (int) $r['id'],
                'started'   => $r['started_at'],
                'last'      => $r['last_at'],
                'mode'      => $r['mode'],
                'page'      => $r['page_url'],
                'msgs'      => (int) $r['msg_count'],
                'name'      => $r['visitor_name'],
                'email'     => $r['visitor_email'],
                'phone'     => $r['visitor_phone'],
            ];
        }
        return rest_ensure_response(['sessions' => $out, 'now' => current_time('mysql')]);
    }

    public static function admin_thread($req) {
        $id = (int) $req->get_param('id');
        $s  = EIT_Chat_Store::get_session($id);
        if (!$s) {
            return self::err('eit_chat_no_session', 'Not found.', 404);
        }
        return rest_ensure_response([
            'session'  => $s,
            'messages' => EIT_Chat_Store::get_messages($id),
        ]);
    }

    /**
     * A staff reply also flips the session to staff mode — from that point the
     * assistant stops answering and the human owns the conversation.
     */
    public static function admin_reply($req) {
        $id   = (int) $req->get_param('id');
        $text = trim(wp_strip_all_tags((string) $req->get_param('text')));
        $s    = EIT_Chat_Store::get_session($id);

        if (!$s) {
            return self::err('eit_chat_no_session', 'Not found.', 404);
        }
        if ($text === '') {
            return self::err('eit_chat_empty', 'Message is empty.');
        }

        EIT_Chat_Store::update_session($id, ['mode' => 'staff']);
        $mid = EIT_Chat_Store::add_message($id, 'staff', $text);

        return rest_ensure_response(['ok' => true, 'id' => $mid]);
    }

    public static function admin_rebuild_kb() {
        $n = EIT_Chat_KB::rebuild();
        return rest_ensure_response(['ok' => true, 'pages' => $n, 'built' => EIT_Chat_KB::built_at()]);
    }

    /* ── notifications ───────────────────────────────────────────────── */

    private static function notify_staff($session, $first_message) {
        $to = trim((string) EIT_Chat_Store::opt('eit_chat_notify_email'));
        if ($to === '') {
            return;
        }
        $admin = admin_url('admin.php?page=eit-chat&session=' . (int) $session['id']);
        $body  = "Someone has started a chat on the website.\n\n"
               . "First message:\n  \"{$first_message}\"\n\n"
               . "Page: {$session['page_url']}\n"
               . "Started: {$session['started_at']}\n\n"
               . "Open the conversation to watch it or take over:\n{$admin}\n";

        wp_mail($to, 'Website chat started — Everything IT', $body);
    }

    private static function notify_lead($session) {
        $to = trim((string) EIT_Chat_Store::opt('eit_chat_notify_email'));
        if ($to === '') {
            return;
        }
        $admin = admin_url('admin.php?page=eit-chat&session=' . (int) $session['id']);
        $body  = "A website chat visitor left their details.\n\n"
               . "Name:  {$session['visitor_name']}\n"
               . "Email: {$session['visitor_email']}\n"
               . "Phone: {$session['visitor_phone']}\n"
               . "Type:  {$session['lead_type']}\n\n"
               . "Page: {$session['page_url']}\n\n"
               . "Full transcript:\n{$admin}\n";

        wp_mail($to, 'Website chat lead — Everything IT', $body);
    }
}
