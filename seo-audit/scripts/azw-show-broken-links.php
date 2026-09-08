<?php
/**
 * Print the broken_links finding from the cached audit report.
 *
 *     wp --path=/html eval-file azw-show-broken-links.php <domain>
 *
 * Read-only.
 */
$url = azwc_audit_normalize($args[0] ?? 'https://azwebcorp.com/');
if (is_wp_error($url)) {
    WP_CLI::error($url->get_error_message());
}
$report = get_transient('azwc_audit_site_' . md5($url));
if (!is_array($report)) {
    WP_CLI::error('no cached report for ' . $url);
}
foreach ($report['checks'] as $check) {
    if ('broken_links' !== $check['id']) {
        continue;
    }
    WP_CLI::line($check['label'] . ' [' . $check['status'] . ']');
    WP_CLI::line($check['detail']);
    foreach ((array) ($check['items'] ?? array()) as $item) {
        WP_CLI::line('  - ' . $item);
    }
}
