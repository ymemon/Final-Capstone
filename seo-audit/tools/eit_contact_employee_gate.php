<?php
/**
 * Add a 15+-employee eligibility notice and a required "Number of Employees"
 * field to Everything IT's Contact Us page (ID 83), and a client-side check
 * that stops a sub-15 submission with a polite message instead of letting it
 * go through - the whole point being nobody spends time on a proposal for a
 * company that was never going to qualify.
 */
global $wpdb;

$post_id = 83;
$raw = get_post_meta($post_id, '_elementor_data', true);
if (!$raw) {
    echo "NO _elementor_data FOUND\n";
    exit;
}

$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "JSON DECODE FAILED: " . json_last_error_msg() . "\n";
    exit;
}

// Backup before touching anything.
file_put_contents('/tmp/contact-83-elementor-data-before.json', $raw);
file_put_contents('/tmp/contact-83-post-content-before.txt', get_post_field('post_content', $post_id));

$found = false;

function &azw_find_editor_widget(&$elements, &$found) {
    foreach ($elements as &$el) {
        if (isset($el['widgetType']) && $el['widgetType'] === 'text-editor' && isset($el['settings']['editor']) && strpos($el['settings']['editor'], 'eit-contact-page') !== false) {
            $found = true;
            return $el['settings']['editor'];
        }
        if (!empty($el['elements'])) {
            $ref = &azw_find_editor_widget($el['elements'], $found);
            if ($found) {
                return $ref;
            }
        }
    }
    $null = null;
    return $null;
}

$editor = &azw_find_editor_widget($data, $found);
if (!$found) {
    echo "WIDGET NOT FOUND\n";
    exit;
}

$html = $editor;
$orig_len = strlen($html);

// 1) A short eligibility badge right under the hero H1.
$needle1 = '<p>We\'re here to help you with all your IT needs';
if (strpos($html, $needle1) === false) {
    echo "NEEDLE 1 NOT FOUND\n";
    exit;
}
$html = str_replace(
    $needle1,
    '<p class="eit-eligibility-badge">For businesses with 15+ employees</p>' . "\n" . $needle1,
    $html
);

// 2) A clear, polite eligibility notice, placed right after the hero button
//    so it is one of the first things a visitor reads - "on the very top".
$needle2 = "<!-- TWO COLUMN LAYOUT -->";
if (strpos($html, $needle2) === false) {
    echo "NEEDLE 2 NOT FOUND\n";
    exit;
}
$notice = <<<'HTML'
<!-- ELIGIBILITY NOTICE -->
<div class="eit-eligibility-notice">
<strong>Please note:</strong> Everything IT currently partners with organisations that have <strong>15 or more employees</strong>. If your business is smaller than this, we're not able to take on new engagements at the moment &mdash; but we'd genuinely welcome hearing from you again as you grow.
</div>

HTML;
$html = str_replace($needle2, $notice . $needle2, $html);

// 3) A required "Number of Employees" field in the form, right after Company.
$needle3 = '<label for="email">Email Address';
if (strpos($html, $needle3) === false) {
    echo "NEEDLE 3 NOT FOUND\n";
    exit;
}
$field = <<<'HTML'
<label for="employees">Number of Employees <span style="color:#ee8b2d;">*</span></label>
<select id="employees" name="employees" required>
<option value="" disabled selected>Please select…</option>
<option value="under-15">Fewer than 15</option>
<option value="15-49">15 &ndash; 49</option>
<option value="50-199">50 &ndash; 199</option>
<option value="200-plus">200+</option>
</select>

HTML;
$html = str_replace($needle3, $field . $needle3, $html);

// 4) Intercept a sub-15 submission client-side with a polite message rather
//    than letting it go to us and wasting everyone's time down the line.
$needle4 = '</form>';
if (strpos($html, $needle4) === false) {
    echo "NEEDLE 4 NOT FOUND\n";
    exit;
}
$script = <<<'HTML'
</form>

<div class="eit-eligibility-block" id="eit-eligibility-block" hidden>
<p><strong>Thanks for your interest.</strong> Right now we're focused on supporting organisations with 15 or more employees, so we're not the right fit just yet. Please do check back as your team grows &mdash; or if your situation is different from what this form suggests, call us on <a href="tel:+35315240755">+353 1 524 0755</a> and we'll be happy to talk.</p>
</div>
<script>
(function(){
  var form = document.querySelector('#contact-form form');
  if (!form) { return; }
  var select = document.getElementById('employees');
  var block = document.getElementById('eit-eligibility-block');
  if (!select || !block) { return; }
  form.addEventListener('submit', function(e){
    if (select.value === 'under-15') {
      e.preventDefault();
      form.hidden = true;
      block.hidden = false;
      block.scrollIntoView({behavior:'smooth', block:'center'});
    }
  });
})();
</script>
HTML;
$html = str_replace($needle4, $script, $html);

// 5) CSS for the badge/notice (appended to the widget's own <style> block).
$css_needle = '@media(max-width:768px){';
if (strpos($html, $css_needle) === false) {
    echo "CSS NEEDLE NOT FOUND\n";
    exit;
}
$css = <<<'CSS'
.eit-contact-page .eit-eligibility-badge{display:inline-block;background:rgba(238,139,45,.16);color:#f3ad68;font-size:.82rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;padding:6px 16px;border-radius:999px;margin:0 0 16px}
.eit-contact-page .eit-eligibility-notice{background:#fff6ec;border-left:3px solid #ee8b2d;padding:18px 22px;margin:0 0 30px;border-radius:4px;color:#6b4a1f;font-size:.98rem;line-height:1.6}
.eit-contact-page .eit-eligibility-block{background:#fff6ec;border-left:3px solid #ee8b2d;padding:22px 26px;margin-top:16px;border-radius:6px;color:#6b4a1f}
.eit-contact-page .eit-eligibility-block p{margin:0;max-width:none}
CSS;
$html = str_replace($css_needle, $css . "\n" . $css_needle, $html);

echo "Length before: $orig_len, after: " . strlen($html) . "\n";

$editor = $html; // write back through the reference

$new_json = wp_json_encode($data);
if (!$new_json) {
    echo "RE-ENCODE FAILED\n";
    exit;
}

// Bypass wp_update_post's content filtering, same technique used elsewhere
// on this account for _elementor_data writes.
$updated = $wpdb->update($wpdb->postmeta, ['meta_value' => wp_slash($new_json)], ['post_id' => $post_id, 'meta_key' => '_elementor_data']);
echo "Rows updated: $updated\n";

delete_post_meta($post_id, '_elementor_css');
echo "DONE\n";
