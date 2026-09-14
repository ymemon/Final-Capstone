<?php
/**
 * Staff side: settings, live conversation monitor, transcripts, takeover.
 *
 * The live monitor is what makes human intervention possible — it polls for
 * open conversations and raises a desktop notification the moment one starts,
 * so whoever is responsible can watch and step in mid-chat.
 */

defined('ABSPATH') || exit;

class EIT_Chat_Admin {

    const SLUG = 'eit-chat';

    public static function menu() {
        $live = count(EIT_Chat_Store::live_sessions(10));
        $title = 'Website Chat';
        if ($live > 0) {
            $title .= ' <span class="update-plugins count-' . $live . '"><span class="update-count">' . $live . '</span></span>';
        }

        add_menu_page(
            'Website Chat',
            $title,
            'edit_pages',
            self::SLUG,
            [__CLASS__, 'render_page'],
            'dashicons-format-chat',
            26
        );
    }

    public static function admin_bar($bar) {
        if (!current_user_can('edit_pages')) {
            return;
        }
        $live = count(EIT_Chat_Store::live_sessions(10));
        if ($live < 1) {
            return;
        }
        $bar->add_node([
            'id'    => 'eit-chat-live',
            'title' => sprintf('💬 %d chatting now', $live),
            'href'  => admin_url('admin.php?page=' . self::SLUG),
            'meta'  => ['title' => 'Website visitors currently in a chat'],
        ]);
    }

    public static function assets($hook) {
        if (strpos((string) $hook, self::SLUG) === false) {
            return;
        }
        wp_enqueue_style('eit-chat-admin', EIT_CHAT_URL . '/assets/admin.css', [], EIT_CHAT_VERSION);
        wp_enqueue_script('eit-chat-admin', EIT_CHAT_URL . '/assets/admin.js', [], EIT_CHAT_VERSION, true);
        wp_localize_script('eit-chat-admin', 'EIT_CHAT_ADMIN', [
            'root'  => esc_url_raw(rest_url('eit/v1/chat/admin')),
            'nonce' => wp_create_nonce('wp_rest'),
            'open'  => isset($_GET['session']) ? (int) $_GET['session'] : 0,
        ]);
    }

    /* ── page ────────────────────────────────────────────────────────── */

    public static function render_page() {
        if (!current_user_can('edit_pages')) {
            wp_die('Not allowed.');
        }

        $saved   = isset($_GET['saved']);
        $live    = EIT_Chat_Brain::is_live();
        $kb      = EIT_Chat_KB::get();
        $kb_time = EIT_Chat_KB::built_at();
        $err     = get_option('eit_chat_last_error', []);
        ?>
        <div class="wrap eit-chat-wrap">
            <h1>Website Chat</h1>

            <?php if ($saved) : ?>
                <div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
            <?php endif; ?>

            <?php if (!$live) : ?>
                <div class="notice notice-warning">
                    <p><strong>Demo mode.</strong> No API key is set, so replies are assembled
                    from the best-matching page on this site instead of being written by the
                    assistant. Everything else — transcripts, alerts, takeover, lead capture —
                    works exactly as it will in production.</p>
                </div>
            <?php endif; ?>

            <?php if (!empty($err['message'])) : ?>
                <div class="notice notice-error">
                    <p><strong>Last assistant error</strong> (<?php echo esc_html($err['at']); ?>):
                    <?php echo esc_html($err['message']); ?></p>
                </div>
            <?php endif; ?>

            <div class="eit-chat-cols">

                <div class="eit-chat-col-main">
                    <h2>Happening now</h2>
                    <p class="description">Updates every few seconds. Sending a reply takes the
                    conversation over from the assistant.</p>
                    <div id="eit-chat-live">
                        <p class="eit-chat-empty">Checking…</p>
                    </div>

                    <h2>Conversation</h2>
                    <div id="eit-chat-thread" class="eit-chat-thread">
                        <p class="eit-chat-empty">Pick a conversation to read it.</p>
                    </div>
                    <div id="eit-chat-replybar" class="eit-chat-replybar" hidden>
                        <textarea id="eit-chat-reply" rows="2" placeholder="Type to join the conversation…"></textarea>
                        <button class="button button-primary" id="eit-chat-send">Send as staff</button>
                    </div>

                    <h2>Recent conversations</h2>
                    <?php self::render_recent(); ?>
                </div>

                <div class="eit-chat-col-side">
                    <h2>Settings</h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="eit_chat_save">
                        <?php wp_nonce_field('eit_chat_save'); ?>

                        <p>
                            <label>
                                <input type="checkbox" name="eit_chat_enabled" value="1"
                                    <?php checked(EIT_Chat_Store::opt('eit_chat_enabled'), '1'); ?>>
                                Show the chat widget on the site
                            </label>
                        </p>

                        <p>
                            <label for="eit_chat_assistant_name"><strong>Assistant name</strong></label><br>
                            <input type="text" class="regular-text" id="eit_chat_assistant_name"
                                name="eit_chat_assistant_name"
                                value="<?php echo esc_attr(EIT_Chat_Store::opt('eit_chat_assistant_name')); ?>">
                        </p>

                        <p>
                            <label for="eit_chat_notify_email"><strong>Alert this address</strong></label><br>
                            <input type="email" class="regular-text" id="eit_chat_notify_email"
                                name="eit_chat_notify_email"
                                value="<?php echo esc_attr(EIT_Chat_Store::opt('eit_chat_notify_email')); ?>">
                            <span class="description">Emailed when a chat starts and when someone leaves details.</span>
                        </p>

                        <p>
                            <label for="eit_chat_api_key"><strong>Anthropic API key</strong></label><br>
                            <input type="password" class="regular-text" id="eit_chat_api_key"
                                name="eit_chat_api_key" autocomplete="off"
                                placeholder="<?php echo $live ? 'Key is set — leave blank to keep' : 'sk-ant-…'; ?>"
                                value="">
                            <span class="description">Leave blank to keep the current key. Stored in the
                            database, never in the plugin file.</span>
                        </p>

                        <p>
                            <label for="eit_chat_model"><strong>Model</strong></label><br>
                            <select name="eit_chat_model" id="eit_chat_model">
                                <?php
                                $models = [
                                    'claude-sonnet-5' => 'Sonnet 5 — recommended, ~8c per chat',
                                    'claude-opus-5'   => 'Opus 5 — best quality, ~20c per chat',
                                    'claude-haiku-4-5'=> 'Haiku 4.5 — cheapest, ~4c per chat',
                                ];
                                $cur = EIT_Chat_Store::opt('eit_chat_model');
                                foreach ($models as $id => $label) {
                                    printf('<option value="%s"%s>%s</option>',
                                        esc_attr($id), selected($cur, $id, false), esc_html($label));
                                }
                                ?>
                            </select>
                        </p>

                        <p>
                            <label for="eit_chat_daily_cap"><strong>Daily conversation cap</strong></label><br>
                            <input type="number" min="0" step="1" id="eit_chat_daily_cap"
                                name="eit_chat_daily_cap" class="small-text"
                                value="<?php echo esc_attr(EIT_Chat_Store::opt('eit_chat_daily_cap')); ?>">
                            <span class="description">Hard stop, so spend cannot run away. 0 = no cap.</span>
                        </p>

                        <p><button class="button button-primary">Save settings</button></p>
                    </form>

                    <hr>

                    <h2>Knowledge base</h2>
                    <p>
                        <?php if ($kb) : ?>
                            <strong><?php echo count($kb); ?> pages</strong> indexed<?php
                            echo $kb_time ? ', last built ' . esc_html($kb_time) : ''; ?>.
                        <?php else : ?>
                            <strong>Not built yet.</strong> The assistant needs this to answer.
                        <?php endif; ?>
                    </p>
                    <p><button class="button" id="eit-chat-rebuild">Rebuild from published pages</button>
                       <span id="eit-chat-rebuild-out"></span></p>
                    <p class="description">Answers are drawn only from published pages, so the
                    assistant cannot state anything the site does not already say.</p>
                </div>

            </div>
        </div>
        <?php
    }

    private static function render_recent() {
        $rows = EIT_Chat_Store::recent_sessions(25);
        if (!$rows) {
            echo '<p class="eit-chat-empty">No conversations yet.</p>';
            return;
        }
        echo '<table class="widefat striped eit-chat-table"><thead><tr>'
           . '<th>Started</th><th>Page</th><th>Msgs</th><th>Contact</th><th></th>'
           . '</tr></thead><tbody>';
        foreach ($rows as $r) {
            $contact = trim($r['visitor_name'] . ' ' . $r['visitor_email'] . ' ' . $r['visitor_phone']);
            printf(
                '<tr><td>%s</td><td class="eit-chat-url">%s</td><td>%d</td><td>%s</td>'
                . '<td><button class="button button-small eit-chat-open" data-id="%d">Read</button></td></tr>',
                esc_html($r['started_at']),
                esc_html(self::short_path($r['page_url'])),
                (int) $r['msg_count'],
                $contact !== '' ? esc_html($contact) : '<span class="eit-chat-dim">—</span>',
                (int) $r['id']
            );
        }
        echo '</tbody></table>';
    }

    private static function short_path($url) {
        $p = wp_parse_url($url, PHP_URL_PATH);
        return $p ? $p : $url;
    }

    /* ── save ────────────────────────────────────────────────────────── */

    public static function save() {
        if (!current_user_can('edit_pages') || !check_admin_referer('eit_chat_save')) {
            wp_die('Not allowed.');
        }

        update_option('eit_chat_enabled', isset($_POST['eit_chat_enabled']) ? '1' : '0', false);
        update_option('eit_chat_assistant_name',
            sanitize_text_field(wp_unslash($_POST['eit_chat_assistant_name'] ?? 'Niall')), false);
        update_option('eit_chat_notify_email',
            sanitize_email(wp_unslash($_POST['eit_chat_notify_email'] ?? '')), false);
        update_option('eit_chat_daily_cap',
            (string) max(0, (int) ($_POST['eit_chat_daily_cap'] ?? 200)), false);

        $model = sanitize_text_field(wp_unslash($_POST['eit_chat_model'] ?? 'claude-sonnet-5'));
        if (in_array($model, ['claude-sonnet-5', 'claude-opus-5', 'claude-haiku-4-5'], true)) {
            update_option('eit_chat_model', $model, false);
        }

        // Blank means "keep the existing key" — so saving the form never wipes it.
        $key = trim((string) wp_unslash($_POST['eit_chat_api_key'] ?? ''));
        if ($key !== '') {
            update_option('eit_chat_api_key', $key, false);
            delete_option('eit_chat_last_error');
        }

        wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG . '&saved=1'));
        exit;
    }
}

add_action('admin_post_eit_chat_save', ['EIT_Chat_Admin', 'save']);
