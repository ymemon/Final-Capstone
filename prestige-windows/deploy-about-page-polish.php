<?php
if (!defined('ABSPATH')) {
    exit;
}

$page_id = 223;
$raw = get_post_meta($page_id, '_elementor_data', true);

if (!is_string($raw) || $raw === '') {
    WP_CLI::error('About page Elementor data was not found.');
}

$replacements = [
    'SHOP NOW' => 'EXPLORE OUR PRODUCTS',
    'Shop Now' => 'Explore Our Products',
    'As a licensed private contractor and authorized distributor for the country&#039;s leading window manufacturers, we bring an unmatched level of craftsmanship, care, and expertise to every project we undertake.'
        => 'As a licensed private contractor, we combine thoughtfully selected, high-performance window and door systems with careful planning, skilled installation, and personal attention throughout every project.',
    "As a licensed private contractor and authorized distributor for the country's leading window manufacturers, we bring an unmatched level of craftsmanship, care, and expertise to every project we undertake."
        => 'As a licensed private contractor, we combine thoughtfully selected, high-performance window and door systems with careful planning, skilled installation, and personal attention throughout every project.',
    'A Partner You Can Trust' => 'Local Expertise. Personal Service.',
    'A PARTNER YOU CAN TRUST' => 'LOCAL EXPERTISE. PERSONAL SERVICE.',
    'Prestige Windows proudly serves as an authorized distributor for fellow licensed contractors. If you are a private contractor seeking access to our premium window lines at competitive distributor pricing, we invite you to reach out and explore a partnership that elevates your business alongside ours.'
        => 'From the first conversation through final installation, Prestige Windows provides straightforward guidance, careful product selection, and responsive service. Every recommendation is shaped around the home, the architecture, and the people who will live with the result.',
    'INQUIRE ABOUT A PARTNERSHIP' => 'SCHEDULE A CONSULTATION',
    'Inquire About a Partnership' => 'Schedule a Consultation',
];

$updated = str_replace(array_keys($replacements), array_values($replacements), $raw, $count);

if ($updated === $raw) {
    WP_CLI::warning('No copy replacements were needed or matched.');
} else {
    update_post_meta($page_id, '_elementor_data', wp_slash($updated));
    wp_update_post(['ID' => $page_id]);
    WP_CLI::success("Updated About page copy ($count replacements).");
}

