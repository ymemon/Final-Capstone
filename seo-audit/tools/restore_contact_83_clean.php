<?php
global $wpdb;
$backup = file_get_contents('/tmp/contact-83-elementor-data-before.json');
if (!$backup) {
    echo "BACKUP READ FAILED\n";
    exit;
}
$decoded = json_decode($backup, true);
echo "backup is valid JSON: " . (json_last_error() === JSON_ERROR_NONE ? "YES" : "NO") . "\n";

// No wp_slash() here - update_post_meta() would normally unslash on the way
// in; going around it via $wpdb->update() directly means whatever we pass
// IS what lands in the column, so it must already be clean.
$updated = $wpdb->update($wpdb->postmeta, ['meta_value' => $backup], ['post_id' => 83, 'meta_key' => '_elementor_data']);
echo "restored rows: $updated\n";
delete_post_meta(83, '_elementor_css');

$check = get_post_meta(83, '_elementor_data', true);
$d2 = json_decode($check, true);
echo "post-restore get_post_meta valid JSON: " . (json_last_error() === JSON_ERROR_NONE ? "YES" : "NO") . "\n";

$rendered = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display(83);
echo "rendered contains 'Reach Out to Us': " . (strpos($rendered, 'Reach Out to Us') !== false ? "YES" : "NO") . "\n";
echo "rendered contains contact form box: " . (strpos($rendered, 'eit-form-box') !== false ? "YES" : "NO") . "\n";
