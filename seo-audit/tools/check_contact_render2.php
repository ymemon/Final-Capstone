<?php
$rendered = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display(83);
echo "rendered contains eligibility badge: " . (strpos($rendered, 'eit-eligibility-badge') !== false ? "YES" : "NO") . "\n";
echo "rendered contains For businesses with: " . (strpos($rendered, 'For businesses with') !== false ? "YES" : "NO") . "\n";
echo "rendered contains select id=employees: " . (strpos($rendered, 'id="employees"') !== false ? "YES" : "NO") . "\n";
