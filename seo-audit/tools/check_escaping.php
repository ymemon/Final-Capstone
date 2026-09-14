<?php
$raw = get_post_meta(83, '_elementor_data', true);
$pos = strpos($raw, "here to help you with all your IT needs");
echo "context around plain anchor:\n";
echo substr($raw, $pos - 40, 120) . "\n\n";

$pos2 = strpos($raw, "Send Message");
echo "context around Send Message / form close:\n";
echo substr($raw, $pos2 - 10, 150) . "\n\n";

echo "wp_json_encode slash test: " . wp_json_encode("</form>") . "\n";
echo "wp_json_encode apostrophe test: " . wp_json_encode("We're") . "\n";
