<?php
/**
 * v2 - operates on the RAW _elementor_data string directly (matching the
 * proven technique for this account: json_encode()-based escaping of each
 * inserted fragment, spliced into the raw text) rather than a full
 * json_decode/modify/json_encode round-trip, which silently produced a
 * postmeta value Elementor's own renderer refused to reflect even though
 * the raw bytes were correct (v1 attempt, reverted).
 */
global $wpdb;

$post_id = 83;
$raw = get_post_meta($post_id, '_elementor_data', true);
if (!$raw || strpos($raw, 'eit-eligibility') !== false) {
    echo "UNEXPECTED STATE - raw empty or already patched\n";
    exit;
}

/**
 * Same escaping Elementor itself uses for a string value inside its JSON.
 * Confirmed empirically: this file's _elementor_data does NOT escape
 * forward slashes (plain "/", not "\/") - matching JSON_UNESCAPED_SLASHES,
 * unlike PHP's json_encode() default. Without this flag, any anchor or
 * inserted fragment containing "/" (a closing tag, a URL) silently fails
 * to match or corrupts the structure.
 */
function azw_json_frag($html) {
    return substr(wp_json_encode($html, JSON_UNESCAPED_SLASHES), 1, -1);
}

$html_out = $raw;
$total_before = strlen($html_out);

function azw_splice(&$text, $anchor_plain, $insert_plain, $label, $after = false) {
    $anchor = azw_json_frag($anchor_plain);
    $pos = strpos($text, $anchor);
    if (false === $pos) {
        echo "ANCHOR NOT FOUND: $label\n";
        return false;
    }
    $insert = azw_json_frag($insert_plain);
    $at = $after ? $pos + strlen($anchor) : $pos;
    $text = substr($text, 0, $at) . $insert . substr($text, $at);
    return true;
}

$ok = true;

// 1) Eligibility badge under the hero paragraph opener.
$ok = azw_splice(
    $html_out,
    "<p>We're here to help you with all your IT needs",
    '<p class="eit-eligibility-badge">For businesses with 15+ employees</p>' . "\n",
    'badge'
) && $ok;

// 2) Eligibility notice before the two-column layout.
$notice = '<!-- ELIGIBILITY NOTICE -->' . "\n"
    . '<div class="eit-eligibility-notice">' . "\n"
    . "<strong>Please note:</strong> Everything IT currently partners with organisations that have <strong>15 or more employees</strong>. If your business is smaller than this, we're not able to take on new engagements at the moment &mdash; but we'd genuinely welcome hearing from you again as you grow." . "\n"
    . '</div>' . "\n\n";
$ok = azw_splice($html_out, "<!-- TWO COLUMN LAYOUT -->", $notice, 'notice') && $ok;

// 3) Number-of-employees field before the Email field.
$field = '<label for="employees">Number of Employees <span style="color:#ee8b2d;">*</span></label>' . "\n"
    . '<select id="employees" name="employees" required>' . "\n"
    . '<option value="" disabled selected>Please select&hellip;</option>' . "\n"
    . '<option value="under-15">Fewer than 15</option>' . "\n"
    . '<option value="15-49">15 &ndash; 49</option>' . "\n"
    . '<option value="50-199">50 &ndash; 199</option>' . "\n"
    . '<option value="200-plus">200+</option>' . "\n"
    . '</select>' . "\n\n";
$ok = azw_splice($html_out, '<label for="email">Email Address', $field, 'employees field') && $ok;

// 4) Polite client-side stop for a sub-15 submission, after the form closes.
$block = "\n\n" . '<div class="eit-eligibility-block" id="eit-eligibility-block" hidden>' . "\n"
    . "<p><strong>Thanks for your interest.</strong> Right now we're focused on supporting organisations with 15 or more employees, so we're not the right fit just yet. Please do check back as your team grows &mdash; or if your situation is different from what this form suggests, call us on <a href=\"tel:+35315240755\">+353 1 524 0755</a> and we'll be happy to talk.</p>" . "\n"
    . '</div>' . "\n"
    . '<script>' . "\n"
    . "(function(){\n"
    . "  var form = document.querySelector('#contact-form form');\n"
    . "  if (!form) { return; }\n"
    . "  var select = document.getElementById('employees');\n"
    . "  var block = document.getElementById('eit-eligibility-block');\n"
    . "  if (!select || !block) { return; }\n"
    . "  form.addEventListener('submit', function(e){\n"
    . "    if (select.value === 'under-15') {\n"
    . "      e.preventDefault();\n"
    . "      form.hidden = true;\n"
    . "      block.hidden = false;\n"
    . "      block.scrollIntoView({behavior:'smooth', block:'center'});\n"
    . "    }\n"
    . "  });\n"
    . "})();\n"
    . '</script>' . "\n";
$ok = azw_splice($html_out, '</form>', $block, 'eligibility block + script', true) && $ok;

// 5) CSS, inserted right before the existing mobile media query.
$css = '.eit-contact-page .eit-eligibility-badge{display:inline-block;background:rgba(238,139,45,.16);color:#f3ad68;font-size:.82rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;padding:6px 16px;border-radius:999px;margin:0 0 16px}' . "\n"
    . '.eit-contact-page .eit-eligibility-notice{background:#fff6ec;border-left:3px solid #ee8b2d;padding:18px 22px;margin:0 0 30px;border-radius:4px;color:#6b4a1f;font-size:.98rem;line-height:1.6}' . "\n"
    . '.eit-contact-page .eit-eligibility-block{background:#fff6ec;border-left:3px solid #ee8b2d;padding:22px 26px;margin-top:16px;border-radius:6px;color:#6b4a1f}' . "\n"
    . '.eit-contact-page .eit-eligibility-block p{margin:0;max-width:none}' . "\n";
$ok = azw_splice($html_out, '@media(max-width:768px){', $css, 'css') && $ok;

if (!$ok) {
    echo "ABORTED - one or more anchors not found, no write performed\n";
    exit;
}

echo "Length before: $total_before, after: " . strlen($html_out) . "\n";

// Sanity check: the result must still be valid JSON before we write it.
$check = json_decode($html_out, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "RESULT IS NOT VALID JSON: " . json_last_error_msg() . " - ABORTED, not writing\n";
    exit;
}
echo "Valid JSON confirmed post-splice.\n";

// NOT wp_slash()'d: $wpdb->update() writes exactly what it's given, and
// update_post_meta() (which we're bypassing) is what would normally strip
// slashes on the way in - adding them here just bakes literal backslashes
// into the column. Confirmed by direct SQL read after a wp_slash()'d write
// showing \"escaped\" structural quotes throughout - invalid JSON that
// Elementor silently failed to render, while get_post_meta() kept reporting
// "found" on simple substring checks because the corruption doesn't remove
// any text, just adds noise around it.
$updated = $wpdb->update($wpdb->postmeta, ['meta_value' => $html_out], ['post_id' => $post_id, 'meta_key' => '_elementor_data']);
echo "Rows updated: $updated\n";

delete_post_meta($post_id, '_elementor_css');
echo "DONE\n";
