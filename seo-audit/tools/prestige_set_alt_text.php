<?php
$alts = [
    'IMG_0116' => 'Black-framed multi-slide glass door opening from a marble-floored interior to a backyard pool',
    'IMG_0114' => 'Black-framed bi-fold glass doors connecting a living room to a covered patio and pool',
    'IMG_0111' => 'Black-framed casement windows overlooking a desert landscaped garden',
    'Screenshot-2026-05-05-at-7.57.45-PM' => 'Corner multi-slide glass door system with black aluminum frames, illuminated at night',
    'Screenshot-2026-05-05-at-7.56.14-PM' => 'Black-framed sliding glass doors along a home exterior patio and pool at twilight',
    'Marketing-Prestige-Windows-3' => 'Modern home with black-framed corner windows and glass doors opening to a patio',
];

global $wpdb;
foreach ($alts as $slug => $alt) {
    $post_id = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND guid LIKE %s LIMIT 1",
        '%' . $wpdb->esc_like($slug) . '%'
    ));
    if (!$post_id) {
        echo "NOT FOUND: $slug\n";
        continue;
    }
    $current = get_post_meta($post_id, '_wp_attachment_image_alt', true);
    update_post_meta($post_id, '_wp_attachment_image_alt', $alt);
    echo "ID $post_id ($slug): '$current' -> '$alt'\n";
}
