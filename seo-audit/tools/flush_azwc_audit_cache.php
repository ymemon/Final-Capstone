<?php
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_azwc_audit_site_%' OR option_name LIKE '_transient_timeout_azwc_audit_site_%'");
wp_cache_flush();
echo "flushed\n";
