<?php
/**
 * Add the Palo Verde PET Scan location to the "Our Locations" block.
 *
 * Mike Bustard, 6 March 2026: "Add Palo Verde PET scan to the locations.
 * Address is 16641 N. 40th St. Phoenix, AZ. 85032." Never actioned — the
 * address appears nowhere on the site, verified 28 August 2026.
 *
 * The block appears on two published pages: 841 (medical-oncology) and
 * 961 (pet-scan-imaging). Both are updated.
 *
 * NO TELEPHONE NUMBER IS ADDED. Every other entry in the block carries one,
 * so the omission is visible — but Mike supplied only an address, and a wrong
 * phone number on a cancer centre's location listing is far worse than a
 * missing one. Ask him for it and add it in a second pass.
 *
 * Both post_content AND _elementor_data are updated. On THIS site the live
 * front end renders from post_content (the opposite of the Prestige site), but
 * leaving _elementor_data stale means anyone opening the page in Elementor
 * later sees the old version and can silently overwrite the fix on save.
 *
 * Usage (from the site root):
 *   wp eval-file pv-add-pet-location.php
 *   wp eval-file pv-add-pet-location.php apply
 */

$APPLY = in_array('apply', $args ?? [], true);
$POSTS = [841 => 'medical-oncology', 961 => 'pet-scan-imaging'];

// Anchor: the closing link of the East Valley entry, which is the last one in
// the list. Unique on both pages.
$ANCHOR = 'destination=1488+W+Elliot+Rd+Gilbert,+AZ+85233" target="_blank" '
        . 'rel="noopener noreferrer">View Location</a>';

$INSERT = "\n      <!-- PET Scan Imaging -->\n"
        . "        <h3>PET SCAN IMAGING</h3>\n"
        . "        <p>\n"
        . "          16641 N. 40th St.<br>\n"
        . "          Phoenix, AZ 85032\n"
        . "        </p>\n"
        . '        <a href="https://www.google.com/maps/dir/?api=1&amp;'
        . 'destination=16641+N+40th+St+Phoenix,+AZ+85032" target="_blank" '
        . 'rel="noopener noreferrer">View Location</a>';

$stamp  = date('Ymd-His');
$backup = rtrim(getenv('HOME') ?: '/tmp', '/') . "/pv-pet-location-backup-$stamp";
if ($APPLY && !is_dir($backup)) { mkdir($backup, 0755, true); }

echo $APPLY ? "MODE: APPLY\n\n" : "MODE: DRY RUN\n\n";
$fail = 0;

foreach ($POSTS as $id => $slug) {
    $post = get_post($id);
    if (!$post) { echo "  post $id NOT FOUND\n"; $fail++; continue; }
    echo "== $id ($slug)\n";

    foreach (['post_content', '_elementor_data'] as $field) {
        $value = ($field === 'post_content')
            ? $post->post_content
            : (string) get_post_meta($id, $field, true);

        if ($value === '') { echo "   $field: empty, skipping\n"; continue; }

        // Already done? Never insert twice.
        $needlePet = ($field === '_elementor_data')
            ? trim(json_encode('16641 N. 40th St.'), '"')
            : '16641 N. 40th St.';
        if (strpos($value, $needlePet) !== false) {
            echo "   $field: PET entry already present, skipping\n";
            continue;
        }

        $anchor = $ANCHOR;
        $insert = $INSERT;
        if ($field === '_elementor_data') {
            $anchor = trim(json_encode($ANCHOR), '"');
            $insert = trim(json_encode($INSERT), '"');
        }

        $hits = substr_count($value, $anchor);
        printf("   %-18s anchor found %d time(s)\n", $field, $hits);
        if ($hits === 0) { echo "      anchor absent - skipping\n"; continue; }
        if ($hits > 1)   { echo "      REFUSING: anchor not unique\n"; $fail++; continue; }
        if (!$APPLY)     { continue; }

        file_put_contents("$backup/{$id}-{$field}.txt", $value);
        $new = str_replace($anchor, $anchor . $insert, $value);

        if ($field === '_elementor_data' && json_decode($new) === null) {
            echo "      REFUSING: result is not valid JSON\n"; $fail++; continue;
        }

        if ($field === 'post_content') {
            $ok = !is_wp_error(wp_update_post(['ID' => $id, 'post_content' => $new], true));
        } else {
            $ok = (bool) update_post_meta($id, $field, wp_slash($new));
        }

        $after = ($field === 'post_content')
            ? get_post($id)->post_content
            : (string) get_post_meta($id, $field, true);
        $done = (strpos($after, $needlePet) !== false);
        printf("      %s  address present after write: %s\n",
               ($ok && $done) ? 'OK' : 'FAIL', $done ? 'yes' : 'no');
        if (!$ok || !$done) { $fail++; }
    }
    if ($APPLY) { delete_post_meta($id, '_elementor_element_cache'); }
    echo "\n";
}

if ($APPLY && $fail === 0) {
    if (class_exists('\Elementor\Plugin')) {
        \Elementor\Plugin::$instance->files_manager->clear_cache();
    }
    wp_cache_flush();
    echo "Caches cleared.\nBackup: $backup\n";
}
echo $fail === 0 ? ($APPLY ? "\nDONE.\n" : "\nDry run clean. Re-run with: apply\n")
                 : "\n$fail problem(s).\n";
