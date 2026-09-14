<?php
global $wpdb;
$table = $wpdb->prefix . 'fluentmail_logs';
$rows = $wpdb->get_results("SELECT id, to_json, subject, status, response, created_at FROM {$table} WHERE subject LIKE '%SEO report%' ORDER BY id DESC LIMIT 5");
foreach ($rows as $r) {
    echo "ID {$r->id} | {$r->created_at} | {$r->status} | {$r->subject} | to: {$r->to_json}\n";
    echo "response: " . substr($r->response, 0, 300) . "\n\n";
}
echo "---attachment column check---\n";
$cols = $wpdb->get_col("SHOW COLUMNS FROM {$table}");
echo implode(", ", $cols) . "\n";
