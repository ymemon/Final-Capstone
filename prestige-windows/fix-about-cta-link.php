<?php
if (!defined('ABSPATH')) {
    exit;
}

$page_id = 223;
$data = json_decode(get_post_meta($page_id, '_elementor_data', true), true);
if (!is_array($data)) {
    WP_CLI::error('Could not decode About page Elementor data.');
}

$found = false;
$replace_urls = static function (&$value) use (&$replace_urls): void {
    if (!is_array($value)) {
        return;
    }
    foreach ($value as $key => &$item) {
        if ($key === 'url' && is_string($item)) {
            $item = 'https://prestigewindowsaz.com/contact/';
        } elseif (is_array($item)) {
            $replace_urls($item);
        }
    }
};

$walk = static function (&$elements) use (&$walk, &$replace_urls, &$found): void {
    foreach ($elements as &$element) {
        if (($element['id'] ?? '') === 'd2ca435') {
            $replace_urls($element['settings']);
            $found = true;
        }
        if (!empty($element['elements']) && is_array($element['elements'])) {
            $walk($element['elements']);
        }
    }
};

$walk($data);
if (!$found) {
    WP_CLI::error('Consultation button widget was not found.');
}

update_post_meta($page_id, '_elementor_data', wp_slash(wp_json_encode($data)));
WP_CLI::success('About page consultation button now links to Contact.');

