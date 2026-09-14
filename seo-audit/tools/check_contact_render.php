<?php
$raw = get_post_meta(83, '_elementor_data', true);
echo "raw contains eit-eligibility-badge: " . (strpos($raw, 'eit-eligibility-badge') !== false ? "YES" : "NO") . "\n";
echo "raw length: " . strlen($raw) . "\n";

$rendered = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display(83);
echo "rendered contains eligibility badge: " . (strpos($rendered, 'eit-eligibility-badge') !== false ? "YES" : "NO") . "\n";
echo "rendered contains For businesses with: " . (strpos($rendered, 'For businesses with') !== false ? "YES" : "NO") . "\n";
