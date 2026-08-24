<?php
global $wpdb;
$css = file_get_contents('/tmp/prestige-footer-v2.css');
$html = file_get_contents('/tmp/prestige-footer-v2.html');
if ($css === false || $html === false) {
    throw new Exception('Footer asset files are missing.');
}
foreach ([235, 487] as $id) {
    $data = json_decode(get_post_meta($id, '_elementor_data', true), true);
    $data[0]['elements'][0]['elements'][0]['settings']['html'] = $html;
    $json = wp_json_encode($data);
    $rows = $wpdb->update(
        $wpdb->postmeta,
        ['meta_value' => $json],
        ['post_id' => $id, 'meta_key' => '_elementor_data'],
        ['%s'],
        ['%d', '%s']
    );
    wp_cache_delete($id, 'post_meta');
    echo "Updated {$id}; rows={$rows}\n";
}
update_option('prestige_footer_css', $css, false);
echo "Footer HTML and CSS updated.\n";
