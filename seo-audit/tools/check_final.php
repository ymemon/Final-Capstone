<?php
$rendered = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display(83);
echo "eligibility badge: " . (strpos($rendered, 'eit-eligibility-badge') !== false ? "YES" : "NO") . "\n";
echo "For businesses with: " . (strpos($rendered, 'For businesses with') !== false ? "YES" : "NO") . "\n";
echo "eligibility notice: " . (strpos($rendered, 'eit-eligibility-notice') !== false ? "YES" : "NO") . "\n";
echo "15 or more employees: " . (strpos($rendered, '15 or more employees') !== false ? "YES" : "NO") . "\n";
echo "employees select field: " . (strpos($rendered, 'id="employees"') !== false ? "YES" : "NO") . "\n";
echo "eligibility block script: " . (strpos($rendered, 'eit-eligibility-block') !== false ? "YES" : "NO") . "\n";
echo "still has Send Message button: " . (strpos($rendered, 'Send Message') !== false ? "YES" : "NO") . "\n";
echo "still has locations bar: " . (strpos($rendered, 'Dublin, Cork, Galway') !== false ? "YES" : "NO") . "\n";
