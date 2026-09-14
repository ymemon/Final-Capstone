<?php
global $wpdb;
$tables = $wpdb->get_col("SHOW TABLES LIKE '%fluent%'");
foreach ($tables as $t) {
    echo "$t\n";
}
