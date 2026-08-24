<?php
if (!defined('ABSPATH')) exit;
$slugs = ['home','about','products','faq','contact','gallery','testimonials','suppliers','terms','privacy','custom-glass-options','window-configurator','blog','yith-compare'];
foreach ($slugs as $slug) {
    $p = $slug === 'home' ? get_post((int)get_option('page_on_front')) : get_page_by_path($slug);
    if (!$p) { echo "$slug\tMISSING\n"; continue; }
    $data = (string)get_post_meta($p->ID, '_elementor_data', true);
    echo implode("\t", [$slug, $p->ID, $p->post_status, strlen($p->post_content), strlen($data)]);
    foreach (['vindors@example.com','authorized dealer','authorized distributor','distribution partner','Great things are on the horizon','Screenshot'] as $n) {
        if (stripos($p->post_content . $data, $n) !== false) echo "\tHAS:$n";
    }
    echo "\n";
}
