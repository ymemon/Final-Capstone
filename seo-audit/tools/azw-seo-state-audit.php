<?php
defined('ABSPATH') || exit;
global $wpdb;

$yoast = $wpdb->get_row(
    "SELECT COUNT(*) AS rows_count, COUNT(DISTINCT post_id) AS posts_count
     FROM {$wpdb->postmeta} WHERE meta_key LIKE '_yoast_wpseo%'",
    ARRAY_A
);
$titles = get_option('rank-math-options-titles', []);
$matching_options = [];
foreach ($titles as $key => $value) {
    if (preg_match('/phone|geo|lat|long|coord|location/i', (string) $key)) {
        $matching_options[$key] = $value;
    }
}
$pages = get_posts([
    'post_type' => 'page', 'post_status' => ['publish', 'draft'],
    'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC',
]);
$inventory = [];
foreach ($pages as $page) {
    $plain = trim(wp_strip_all_tags(strip_shortcodes($page->post_content)));
    $robots = get_post_meta($page->ID, 'rank_math_robots', true);
    $inventory[] = [
        'ID' => $page->ID,
        'post_name' => $page->post_name,
        'post_title' => $page->post_title,
        'post_status' => $page->post_status,
        'words' => str_word_count($plain),
        'noindex' => is_array($robots) && in_array('noindex', $robots, true),
    ];
}
WP_CLI::line(wp_json_encode([
    'yoast_postmeta' => $yoast,
    'local_seo' => [
        'phone' => $titles['phone'] ?? null,
        'email' => $titles['email'] ?? null,
        'address' => $titles['local_address'] ?? null,
        'latitude' => $titles['geo_latitude'] ?? null,
        'longitude' => $titles['geo_longitude'] ?? null,
        'foundingDate' => $titles['foundingDate'] ?? null,
    ],
    'matching_local_option_keys' => $matching_options,
    'pages' => $inventory,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
