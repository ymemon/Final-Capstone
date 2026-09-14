<?php
/**
 * Remove customer-visible placeholder text from the Prestige Windows site.
 *
 * Targets (verified live 2026-08-26, cache-busted):
 *   1. Home (post 21)  - heading renders "Built to Last - Premium Window
 *      Systems Section Text". The widget's `content` field literally begins
 *      "Section Text\t" before the real paragraph.
 *   2. FAQ (post 592) - "What is the warranty on your products?" answer ends
 *      with "[CLIENT TO PROVIDE: Any additional warranty details you wish to
 *      include]". The preceding sentence already answers the question, so the
 *      placeholder is deleted outright.
 *
 * NOT touched here: the "What areas do you serve?" answer, which is
 * "[CLIENT TO PROVIDE: Service area details]". Writing that requires the
 * client's actual city list - the only geography this site publishes anywhere
 * is the Scottsdale address. Inventing a service area would be inventing a
 * business fact, so it waits for Nassim or for explicit sign-off on the
 * Scottsdale-only holding copy.
 *
 * Both `post_content` and `_elementor_data` are updated. Post 21 is in
 * `builder` mode so `_elementor_data` is what renders, but the 24 Aug brand
 * sweep established that this site keeps both in sync - leaving post_content
 * dirty would leave a landmine for the next literal search.
 *
 * Per the 24 Aug lesson: this counts occurrences before and after with
 * substr_count rather than trusting a SQL-side regex sweep, which on this
 * site has reported "CLEAN" over a demonstrably dirty row.
 *
 * Usage (from /html):
 *   php fix-visible-placeholders.php          # dry run, changes nothing
 *   php fix-visible-placeholders.php apply
 */

require_once __DIR__ . '/wp-load.php';

$APPLY = (isset($argv[1]) && $argv[1] === 'apply');

// Exact literal replacements. Keys are deliberately long enough to be unique.
$JOBS = [
    21 => [
        [
            'label' => 'Home: stray "Section Text" label before the intro paragraph',
            'find'  => '"content":"Section Text\tPrestige Windows fits',
            'repl'  => '"content":"Prestige Windows fits',
            'field' => '_elementor_data',
        ],
        [
            'label' => 'Home: same label in post_content',
            'find'  => "Section Text\tPrestige Windows fits",
            'repl'  => "Prestige Windows fits",
            'field' => 'post_content',
        ],
    ],
    592 => [
        [
            'label' => 'FAQ: warranty placeholder',
            'find'  => ' [CLIENT TO PROVIDE: Any additional warranty details you wish to include]',
            'repl'  => '',
            'field' => '_elementor_data',
        ],
        [
            'label' => 'FAQ: warranty placeholder in post_content',
            'find'  => ' [CLIENT TO PROVIDE: Any additional warranty details you wish to include]',
            'repl'  => '',
            'field' => 'post_content',
        ],
    ],
];

$stamp  = date('Ymd-His');
$backup = rtrim(getenv('HOME') ?: '/tmp', '/') . "/prestige-placeholder-backup-$stamp";
if ($APPLY && !is_dir($backup) && !mkdir($backup, 0755, true)) {
    fwrite(STDERR, "FATAL: cannot create backup dir $backup\n");
    exit(1);
}

echo $APPLY ? "MODE: APPLY\n" : "MODE: DRY RUN (nothing will be written)\n";
echo $APPLY ? "Backups: $backup\n\n" : "\n";

$fail = 0;

foreach ($JOBS as $post_id => $jobs) {
    $post = get_post($post_id);
    if (!$post) {
        echo "  post $post_id NOT FOUND - skipping\n";
        $fail++;
        continue;
    }
    echo "== post $post_id ({$post->post_title}, {$post->post_status})\n";

    foreach ($jobs as $job) {
        $field = $job['field'];
        $value = ($field === 'post_content')
            ? $post->post_content
            : (string) get_post_meta($post_id, $field, true);

        $hits = substr_count($value, $job['find']);
        printf("   %-58s %s\n", $job['label'], "$hits occurrence(s)");

        if ($hits === 0) {
            // Not an error on a re-run - it means this one is already clean.
            echo "      already clean, nothing to do\n";
            continue;
        }
        if ($hits > 1) {
            echo "      REFUSING: expected exactly 1, found $hits. Target is not unique.\n";
            $fail++;
            continue;
        }
        if (!$APPLY) {
            continue;
        }

        // Back up the exact pre-change value, one file per field touched.
        file_put_contents("$backup/{$post_id}-{$field}.txt", $value);

        $new = str_replace($job['find'], $job['repl'], $value);
        if ($new === $value) {
            echo "      REFUSING: replacement produced no change\n";
            $fail++;
            continue;
        }

        if ($field === 'post_content') {
            $r = wp_update_post(['ID' => $post_id, 'post_content' => $new], true);
            $ok = !is_wp_error($r);
        } else {
            // update_post_meta returns false when the value is unchanged, which
            // cannot happen here - we already proved $new !== $value.
            $ok = (bool) update_post_meta($post_id, $field, wp_slash($new));
        }

        // Verify by re-reading, not by trusting the return value.
        $after = ($field === 'post_content')
            ? get_post($post_id)->post_content
            : (string) get_post_meta($post_id, $field, true);
        $left = substr_count($after, $job['find']);

        printf("      %s  written=%s  remaining=%d\n",
            ($ok && $left === 0) ? 'OK  ' : 'FAIL', $ok ? 'yes' : 'no', $left);
        if (!$ok || $left !== 0) {
            $fail++;
        }
    }
    echo "\n";
}

if ($APPLY && $fail === 0) {
    // Elementor keeps its own render cache; a WP object-cache flush is not
    // enough to make an _elementor_data edit show up.
    if (class_exists('\Elementor\Plugin')) {
        \Elementor\Plugin::$instance->files_manager->clear_cache();
        echo "Elementor CSS/render cache cleared.\n";
    }
    wp_cache_flush();
    echo "WP object cache flushed.\n";
    echo "\nNOTE: the Cloudflare edge in front of this origin can still serve\n";
    echo "stale HTML. Verify with ?cachebust=<something>; a real edge purge\n";
    echo "needs the authenticated wp-admin wpaas_action=flush_cache call.\n";
}

echo $fail === 0
    ? ($APPLY ? "\nDONE - all replacements verified.\n" : "\nDry run clean. Re-run with: apply\n")
    : "\n$fail problem(s) - nothing further attempted.\n";

exit($fail === 0 ? 0 : 1);
