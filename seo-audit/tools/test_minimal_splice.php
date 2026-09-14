<?php
global $wpdb;
$post_id = 83;
$raw = get_post_meta($post_id, '_elementor_data', true);

// Pick a completely plain-ASCII anchor: letters, numbers, spaces only.
$anchor_plain = "Reach Out to Us";
$anchor_json = substr(wp_json_encode($anchor_plain), 1, -1);
$pos = strpos($raw, $anchor_json);
echo "anchor found at: " . var_export($pos, true) . "\n";
if (false === $pos) {
    echo "ABORT\n";
    exit;
}

$insert_plain = "ZZZTESTMARKERZZZ ";
$insert_json = substr(wp_json_encode($insert_plain), 1, -1);
$new = substr($raw, 0, $pos) . $insert_json . substr($raw, $pos);

echo "context before splice:\n" . substr($raw, $pos - 30, 60) . "\n\n";
echo "context after splice:\n" . substr($new, $pos - 30, 60 + strlen($insert_json)) . "\n\n";

$check = json_decode($new, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "INVALID JSON AFTER SPLICE: " . json_last_error_msg() . "\n";
    exit;
}
echo "valid JSON confirmed\n";

$updated = $wpdb->update($wpdb->postmeta, ['meta_value' => wp_slash($new)], ['post_id' => $post_id, 'meta_key' => '_elementor_data']);
echo "rows updated: $updated\n";
delete_post_meta($post_id, '_elementor_css');

$rendered = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($post_id);
echo "rendered contains marker: " . (strpos($rendered, 'ZZZTESTMARKERZZZ') !== false ? "YES" : "NO") . "\n";
