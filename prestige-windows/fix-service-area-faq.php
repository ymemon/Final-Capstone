<?php
/**
 * Replace the FAQ "What areas do you serve?" placeholder with the client's
 * real service area.
 *
 * The last of the three customer-visible placeholders found on 2026-08-26.
 * The other two (Home "Section Text", FAQ warranty) were fixed the same day;
 * this one waited because writing a service area without the client's actual
 * city list would be inventing a business fact. The only geography this site
 * publishes anywhere is the Scottsdale address (34462 N Scottsdale Rd, 85266)
 * - there is no areaServed in the schema and no city list on any page.
 *
 * >>> PUT THE CLIENT'S CITIES IN $CITIES BELOW, then run. <<<
 * Do not guess them. If the list is not confirmed, do not run this file.
 *
 * Usage (from /html):
 *   php fix-service-area-faq.php          # dry run, changes nothing
 *   php fix-service-area-faq.php apply
 *
 * Afterwards: delete this file from the web root. It is publicly reachable
 * while it sits in /html.
 */

require_once __DIR__ . '/wp-load.php';

// ---------------------------------------------------------------------------
// Confirmed by Nassim via Yasir, 2026-08-26: coverage is STATEWIDE Arizona.
// The named areas are the ones worth calling out by name - they are where the
// work concentrates and the markets being targeted.
//
// Note on "Biltmore": that is a district of Phoenix, not a city, so it is
// written as "the Biltmore area of Phoenix" rather than listed as a peer of
// Scottsdale/Gilbert. Getting that wrong reads as unfamiliar with the market
// to exactly the local buyers it is meant to attract.
// ---------------------------------------------------------------------------
$CITIES = ['Scottsdale', 'Gilbert', 'Chandler', 'Queen Creek'];

// Statewide is the honest headline claim; the named areas sit underneath it
// rather than replacing it.
$STATEWIDE = true;
// ---------------------------------------------------------------------------

$APPLY  = (isset($argv[1]) && $argv[1] === 'apply');
$POST   = 592;
$FIND   = '[CLIENT TO PROVIDE: Service area details]';

if (!$CITIES) {
    fwrite(STDERR, "ABORT: \$CITIES is empty. Fill in the client-confirmed city\n");
    fwrite(STDERR, "list before running. Do not guess a service area.\n");
    exit(1);
}

// Build a natural sentence: "A, B, C and D".
$cities = array_values(array_filter(array_map('trim', $CITIES)));
$last   = array_pop($cities);
$list   = $cities ? implode(', ', $cities) . ' and ' . $last : $last;

$answer = ($STATEWIDE ? 'We serve customers throughout Arizona. ' : '')
        . 'Much of our work is in ' . $list
        . ', along with the Biltmore area of Phoenix and the surrounding '
        . 'communities. If you are not sure whether we reach your address, '
        . 'contact us and we will confirm.';

echo $APPLY ? "MODE: APPLY\n\n" : "MODE: DRY RUN (nothing will be written)\n\n";
echo "New answer:\n  $answer\n\n";

$stamp  = date('Ymd-His');
$backup = rtrim(getenv('HOME') ?: '/tmp', '/') . "/prestige-servicearea-backup-$stamp";
if ($APPLY && !is_dir($backup) && !mkdir($backup, 0755, true)) {
    fwrite(STDERR, "FATAL: cannot create backup dir $backup\n");
    exit(1);
}

$post = get_post($POST);
if (!$post) {
    fwrite(STDERR, "FATAL: post $POST not found\n");
    exit(1);
}

$fail = 0;

// Both fields, same as the 26 Aug placeholder fix - post 592 keeps
// post_content and _elementor_data in sync on this site.
foreach (['_elementor_data', 'post_content'] as $field) {
    $value = ($field === 'post_content')
        ? $post->post_content
        : (string) get_post_meta($POST, $field, true);

    // In _elementor_data the value is inside a JSON string, so the replacement
    // has to be JSON-escaped or the blob stops parsing.
    $find = $FIND;
    $repl = $answer;
    if ($field === '_elementor_data') {
        $find = trim(json_encode($FIND), '"');
        $repl = trim(json_encode($answer), '"');
    }

    $hits = substr_count($value, $find);
    printf("  %-18s %d occurrence(s)\n", $field, $hits);

    if ($hits === 0) {
        echo "     already clean or target not present - skipping\n";
        continue;
    }
    if ($hits > 1) {
        echo "     REFUSING: expected exactly 1, found $hits\n";
        $fail++;
        continue;
    }
    if (!$APPLY) {
        continue;
    }

    file_put_contents("$backup/{$POST}-{$field}.txt", $value);
    $new = str_replace($find, $repl, $value);

    if ($field === '_elementor_data' && json_decode($new) === null) {
        echo "     REFUSING: result is not valid JSON, not writing\n";
        $fail++;
        continue;
    }

    if ($field === 'post_content') {
        $r  = wp_update_post(['ID' => $POST, 'post_content' => $new], true);
        $ok = !is_wp_error($r);
    } else {
        $ok = (bool) update_post_meta($POST, $field, wp_slash($new));
    }

    $after = ($field === 'post_content')
        ? get_post($POST)->post_content
        : (string) get_post_meta($POST, $field, true);
    $left = substr_count($after, $find);

    printf("     %s  remaining=%d\n", ($ok && $left === 0) ? 'OK' : 'FAIL', $left);
    if (!$ok || $left !== 0) {
        $fail++;
    }
}

if ($APPLY && $fail === 0) {
    if (class_exists('\Elementor\Plugin')) {
        \Elementor\Plugin::$instance->files_manager->clear_cache();
        echo "\nElementor render cache cleared.\n";
    }
    wp_cache_flush();
    echo "WP object cache flushed.\n";
    echo "Verify with ?cachebust=<something>; the Cloudflare edge still needs\n";
    echo "the authenticated wp-admin wpaas_action=flush_cache call for real users.\n";
    echo "\nThen DELETE this file from /html.\n";
}

echo $fail === 0
    ? ($APPLY ? "\nDONE.\n" : "\nDry run clean. Re-run with: apply\n")
    : "\n$fail problem(s).\n";

exit($fail === 0 ? 0 : 1);
