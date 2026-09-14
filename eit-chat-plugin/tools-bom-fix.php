<?php
/* Strip a leading UTF-8 BOM from a mu-plugin. Backs up first, verifies the
   result still parses, and restores the backup if it does not.
   Usage: wp eval-file bom-fix.php [apply] */
$apply  = in_array('apply', $args ?? [], true);
$target = WP_CONTENT_DIR . '/mu-plugins/ai-crawler-logger.php';
$bom    = "\xEF\xBB\xBF";

if (!is_file($target)) { echo "Target not found: $target\n"; return; }

$raw = file_get_contents($target);
if (strncmp($raw, $bom, 3) !== 0) { echo "No BOM present — nothing to do.\n"; return; }

printf("Target : %s\nBytes  : %d -> %d after strip\n", $target, strlen($raw), strlen($raw) - 3);

if (!$apply) { echo "\nDRY RUN. Re-run with: apply\n"; return; }

$backup = dirname($target) . '/../../../ai-crawler-logger.php.bom-backup';
$backup = WP_CONTENT_DIR . '/ai-crawler-logger.php.bom-backup';
file_put_contents($backup, $raw);
echo "Backup : $backup\n";

$clean = substr($raw, 3);
file_put_contents($target, $clean);

// Verify it still parses; restore immediately if not. A broken mu-plugin
// takes the whole site down, so this must not be left to chance.
$out = [];
$rc  = 0;
exec('php -l ' . escapeshellarg($target) . ' 2>&1', $out, $rc);
if ($rc !== 0) {
    file_put_contents($target, $raw);
    echo "LINT FAILED — restored original.\n" . implode("\n", $out) . "\n";
    return;
}
echo "Lint   : " . implode(' ', $out) . "\nDone.\n";
