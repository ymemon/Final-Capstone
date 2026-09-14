<?php
$raw = get_post_meta(83, '_elementor_data', true);
$decoded = json_decode($raw, true);
echo "current get_post_meta value valid JSON: " . (json_last_error() === JSON_ERROR_NONE ? "YES" : "NO (" . json_last_error_msg() . ")") . "\n";
echo "length: " . strlen($raw) . "\n";
echo "first 200 chars:\n" . substr($raw, 0, 200) . "\n";

global $wpdb;
$sql_raw = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_elementor_data'", 83));
$sql_decoded = json_decode($sql_raw, true);
echo "\ndirect SQL value valid JSON: " . (json_last_error() === JSON_ERROR_NONE ? "YES" : "NO (" . json_last_error_msg() . ")") . "\n";
echo "SQL length: " . strlen($sql_raw) . "\n";
echo "SQL first 200 chars:\n" . substr($sql_raw, 0, 200) . "\n";
