<?php
/**
 * Dump one Elementor widget's content, to inspect before editing it.
 *
 *     wp --path=/html eval-file azw-elementor-dump-widget.php <post_id> <widget_id> [headings]
 *
 * With "headings" it prints only the heading skeleton; otherwise raw content.
 * Read-only.
 */
$id = (int) ($args[0] ?? 0);
$widget_id = (string) ($args[1] ?? '');
$mode = (string) ($args[2] ?? 'raw');

$raw = get_post_meta($id, '_elementor_data', true);
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = json_decode(wp_unslash($raw), true);
}
if (!is_array($data)) {
    WP_CLI::error('could not decode _elementor_data');
}

$found = null;
$walk = function ($nodes) use (&$walk, $widget_id, &$found) {
    foreach ($nodes as $node) {
        if (($node['id'] ?? '') === $widget_id) {
            $found = $node;
            return;
        }
        if (!empty($node['elements'])) {
            $walk($node['elements']);
        }
    }
};
$walk($data);

if (!$found) {
    WP_CLI::error("widget {$widget_id} not found");
}

$settings = $found['settings'] ?? array();
WP_CLI::line('settings keys: ' . implode(', ', array_keys($settings)));

$content = '';
foreach (array('html', 'editor', 'text') as $key) {
    if (isset($settings[$key])) {
        $content = (string) $settings[$key];
        WP_CLI::line("content key: {$key}, " . strlen($content) . ' bytes');
        break;
    }
}

if ('headings' === $mode) {
    if (preg_match_all('#<(h[1-3])[^>]*>(.*?)</\1>#is', $content, $m, PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            WP_CLI::line(sprintf('  %s  %s', $hit[1], trim(wp_strip_all_tags($hit[2]))));
        }
    }
    WP_CLI::line('');
    WP_CLI::line('tail of content:');
    WP_CLI::line(mb_substr($content, -400));
} else {
    WP_CLI::line($content);
}
