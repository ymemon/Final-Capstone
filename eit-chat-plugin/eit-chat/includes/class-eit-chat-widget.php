<?php
/**
 * Front-end widget: enqueues its own assets and prints the mount point.
 *
 * CSS and JS are real enqueued files, not inline blocks — Autoptimize is
 * active on this site and folds inline <style> into a cached aggregate,
 * which makes an inline widget look broken until the bundle regenerates.
 */

defined('ABSPATH') || exit;

class EIT_Chat_Widget {

    public static function should_render() {
        if (is_admin() || wp_doing_ajax()) {
            return false;
        }
        if (EIT_Chat_Store::opt('eit_chat_enabled') !== '1') {
            return false;
        }
        // Password-protected pages (the hardware demo) still get the widget;
        // only skip the login screen and feeds.
        if (is_feed() || is_robots()) {
            return false;
        }
        return true;
    }

    public static function assets() {
        if (!self::should_render()) {
            return;
        }

        wp_enqueue_style(
            'eit-chat',
            EIT_CHAT_URL . '/assets/widget.css',
            [],
            EIT_CHAT_VERSION
        );

        wp_enqueue_script(
            'eit-chat',
            EIT_CHAT_URL . '/assets/widget.js',
            [],
            EIT_CHAT_VERSION,
            true
        );

        wp_localize_script('eit-chat', 'EIT_CHAT', [
            'root'  => esc_url_raw(rest_url('eit/v1/chat')),
            'name'  => EIT_Chat_Brain::assistant_name(),
            'page'  => esc_url_raw(self::current_url()),
            'ref'   => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '',
        ]);
    }

    public static function render() {
        if (!self::should_render()) {
            return;
        }
        $name = esc_attr(EIT_Chat_Brain::assistant_name());
        echo '<div id="eit-chat-root" data-name="' . $name . '"></div>' . "\n";
    }

    private static function current_url() {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host   = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $uri    = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        return $scheme . $host . $uri;
    }
}
