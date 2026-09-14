<?php
/**
 * Plugin Name: AZW Rebound Booking Mail Worker
 * Description: Drains Rebound's transactional mail queue every five minutes on the server.
 * Version: 1.0.0
 * Author: AZWebCorp
 */

defined('ABSPATH') || exit;

const AZW_REBOUND_MAIL_HOOK = 'azw_rebound_drain_mail_queue';
const AZW_REBOUND_MAIL_LOCK = 'azw_rebound_mail_worker_lock';
const AZW_REBOUND_MAIL_LAST = 'azw_rebound_mail_worker_last';

add_filter('cron_schedules', static function (array $schedules): array {
    $schedules['azw_rebound_five_minutes'] = [
        'interval' => 5 * MINUTE_IN_SECONDS,
        'display' => 'Every five minutes (Rebound mail)',
    ];
    return $schedules;
});

add_action('init', static function (): void {
    if (!wp_next_scheduled(AZW_REBOUND_MAIL_HOOK)) {
        wp_schedule_event(time() + MINUTE_IN_SECONDS, 'azw_rebound_five_minutes', AZW_REBOUND_MAIL_HOOK);
    }
});

add_action(AZW_REBOUND_MAIL_HOOK, 'azw_rebound_drain_mail_queue');

function azw_rebound_drain_mail_queue(): void
{
    if (get_transient(AZW_REBOUND_MAIL_LOCK)) {
        return;
    }
    set_transient(AZW_REBOUND_MAIL_LOCK, 1, 4 * MINUTE_IN_SECONDS);

    $status = ['at' => gmdate('Y-m-d H:i:s'), 'ok' => false];
    try {
        $root = ABSPATH . 'preview/rebound/booking';
        foreach (['Clock.php', 'Db.php', 'Config.php', 'Templates.php', 'Mailer.php'] as $file) {
            $path = $root . '/src/' . $file;
            if (!is_readable($path)) {
                throw new RuntimeException('Missing booking dependency: ' . $file);
            }
            require_once $path;
        }

        $dbPath = \Rebound\Config::databasePath();
        if (!is_file($dbPath)) {
            throw new RuntimeException('Booking database is unavailable');
        }

        $db = \Rebound\Db::sqlite($dbPath);
        $mailer = new \Rebound\Mailer($db);
        $status['result'] = $mailer->drain(50);
        $status['ok'] = true;
    } catch (Throwable $error) {
        $status['error'] = substr($error->getMessage(), 0, 300);
    } finally {
        update_option(AZW_REBOUND_MAIL_LAST, $status, false);
        delete_transient(AZW_REBOUND_MAIL_LOCK);
    }
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('rebound-mail status', static function (): void {
        WP_CLI::line((string) wp_json_encode([
            'next_run_utc' => ($next = wp_next_scheduled(AZW_REBOUND_MAIL_HOOK))
                ? gmdate('Y-m-d H:i:s', $next)
                : null,
            'last_run' => get_option(AZW_REBOUND_MAIL_LAST, null),
        ], JSON_PRETTY_PRINT));
    });
}

