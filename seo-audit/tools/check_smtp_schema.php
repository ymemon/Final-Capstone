<?php
$dir = WP_PLUGIN_DIR . '/fluent-smtp';
echo "Plugin exists: " . (is_dir($dir) ? "YES" : "NO") . "\n";
$files = glob($dir . '/app/Services/Mailer/Manager.php');
$files2 = glob($dir . '/app/Services/Mailer/*.php');
foreach ($files2 as $f) {
    echo basename($f) . "\n";
}
echo "---Provider settings class hints---\n";
$smtp_file = $dir . '/app/Services/Mailer/Manager.php';
if (file_exists($smtp_file)) {
    echo substr(file_get_contents($smtp_file), 0, 500) . "\n";
}
