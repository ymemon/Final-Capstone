<?php
/**
 * Describe a page's Elementor tree, so an edit can target a real node instead
 * of a guess.
 *
 *     wp --path=/html eval-file azw-elementor-inspect.php <post_id>
 *
 * Read-only. Prints the section/column/widget skeleton with element ids and,
 * for text widgets, the opening of their content.
 */
$id = (int) ($args[0] ?? 0);
if (!$id) {
    WP_CLI::error('Name a post id.');
}

$raw = get_post_meta($id, '_elementor_data', true);
if (!$raw) {
    WP_CLI::error("post {$id} has no _elementor_data");
}
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = json_decode(wp_unslash($raw), true);
}
if (!is_array($data)) {
    WP_CLI::error('could not decode _elementor_data');
}

WP_CLI::line('top-level sections: ' . count($data));

$walk = function ($nodes, $depth) use (&$walk) {
    foreach ($nodes as $i => $node) {
        $type = $node['elType'] ?? '?';
        $widget = $node['widgetType'] ?? '';
        $label = $widget ? "{$type}:{$widget}" : $type;
        $extra = '';
        if ('text-editor' === $widget || 'heading' === $widget) {
            $content = $node['settings']['editor'] ?? ($node['settings']['title'] ?? '');
            $extra = ' | ' . mb_substr(trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) $content))), 0, 70);
        }
        WP_CLI::line(sprintf(
            '%s[%d] %-26s id=%s%s',
            str_repeat('  ', $depth),
            $i,
            $label,
            $node['id'] ?? '?',
            $extra
        ));
        if (!empty($node['elements'])) {
            $walk($node['elements'], $depth + 1);
        }
    }
};
$walk($data, 0);
