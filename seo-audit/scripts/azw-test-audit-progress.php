<?php
/**
 * Prove the live crawl feed works end to end: clear the cached report so the
 * audit really crawls, run it with a job id, then read back what the progress
 * feed recorded.
 *
 *     wp --path=/html eval-file azw-test-audit-progress.php <domain>
 *
 * Writes only the audit's own transients.
 */
$domain = $args[0] ?? 'https://azwebcorp.com/';
$url = azwc_audit_normalize($domain);
if (is_wp_error($url)) {
    WP_CLI::error($url->get_error_message());
}

delete_transient('azwc_audit_site_' . md5($url));
WP_CLI::line('cleared cached report for ' . $url);

$job = 'test' . substr(md5((string) microtime(true)), 0, 12);
$started = microtime(true);
$response = azwc_audit_stage_site($url, $job);
$elapsed = round(microtime(true) - $started, 1);

$state = get_transient(azwc_audit_job_key($job));
if (!is_array($state)) {
    WP_CLI::error('no progress state recorded — the feed is not working');
}

$events = $state['events'];
WP_CLI::line(sprintf(
    'audit finished in %ss, phase="%s", done=%s, %d events recorded',
    $elapsed,
    $state['phase'],
    !empty($state['done']) ? 'yes' : 'no',
    count($events)
));

$by_status = array();
foreach ($events as $e) {
    $band = !$e['status'] ? 'failed' : (int) floor($e['status'] / 100) . 'xx';
    $by_status[$band] = ($by_status[$band] ?? 0) + 1;
}
WP_CLI::line('status spread: ' . json_encode($by_status));

WP_CLI::line('');
WP_CLI::line('first 12 events as the browser would render them:');
foreach (array_slice($events, 0, 12) as $e) {
    WP_CLI::line(sprintf(
        '  +%-5ss  %-4s  %5dms  %8s  %s',
        number_format($e['at'], 1),
        $e['status'] ?: 'ERR',
        $e['ms'],
        $e['bytes'] ? size_format($e['bytes']) : '-',
        $e['url']
    ));
}
