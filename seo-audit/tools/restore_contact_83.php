<?php
global $wpdb;
$backup = file_get_contents('/tmp/contact-83-elementor-data-before.json');
if (!$backup) {
    echo "BACKUP READ FAILED\n";
    exit;
}
$updated = $wpdb->update($wpdb->postmeta, ['meta_value' => wp_slash($backup)], ['post_id' => 83, 'meta_key' => '_elementor_data']);
echo "restored rows: $updated\n";
delete_post_meta(83, '_elementor_css');
echo "done\n";
