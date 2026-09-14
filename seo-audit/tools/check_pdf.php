<?php
echo "dompdf_ready: " . (azwc_fu_dompdf_ready() ? "YES" : "NO") . "\n";
echo "active_plugins:\n";
foreach (get_option('active_plugins', array()) as $p) {
    if (stripos($p, 'mail') !== false || stripos($p, 'smtp') !== false) {
        echo "  - $p\n";
    }
}
echo "mu-plugins with 'mail' in name:\n";
foreach (glob(WPMU_PLUGIN_DIR . '/*mail*') as $f) {
    echo "  - " . basename($f) . "\n";
}
