<?php
defined('ABSPATH') || exit;
global $wpdb;

$needles = [
    '/arizona-web-development/',
    '/arizona-backend-development/',
    '/frontend-development/',
    '/hire-our-services/',
];
$out = [];
foreach ($needles as $needle) {
    $like = '%' . $wpdb->esc_like($needle) . '%';
    $posts = $wpdb->get_results($wpdb->prepare(
        "SELECT ID, post_type, post_status, post_title FROM {$wpdb->posts}
         WHERE post_content LIKE %s AND post_type NOT IN ('revision','nav_menu_item')",
        $like
    ), ARRAY_A);
    $meta = $wpdb->get_results($wpdb->prepare(
        "SELECT pm.post_id AS ID, p.post_type, p.post_status, p.post_title, pm.meta_key
         FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID=pm.post_id
         WHERE pm.meta_value LIKE %s AND pm.meta_key IN ('_elementor_data','_menu_item_url')
           AND p.post_type <> 'revision'",
        $like
    ), ARRAY_A);
    $out[$needle] = ['post_content' => $posts, 'meta' => $meta];
}
WP_CLI::line(wp_json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
