<?php
global $wpdb;
$opt = get_option('fluentmail-settings');
echo "fluentmail-settings option exists: " . ($opt ? "YES" : "NO") . "\n";
if ($opt) {
    echo json_encode($opt, JSON_PRETTY_PRINT) . "\n";
}
echo "---\n";
// Check for any scheduled/queued mail-related cron events
foreach (_get_cron_array() as $ts => $hooks) {
    foreach ($hooks as $hook => $events) {
        if (stripos($hook, 'mail') !== false || stripos($hook, 'fluent') !== false) {
            echo gmdate('Y-m-d H:i:s', $ts) . " - $hook\n";
        }
    }
}
