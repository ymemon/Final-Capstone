<?php
/* One-off setup + self-test for the chat plugin. Run via wp eval-file. */
global $wpdb;

echo "=== tables ===\n";
foreach ([EIT_Chat_Store::sessions_table(), EIT_Chat_Store::messages_table()] as $t) {
    $found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t));
    printf("  %-42s %s\n", $t, $found ? 'OK' : 'MISSING');
}

echo "=== knowledge base ===\n";
$n = EIT_Chat_KB::rebuild();
echo "  indexed pages: {$n}\n";
echo "  built at:      " . EIT_Chat_KB::built_at() . "\n";

echo "=== retrieval spot-checks ===\n";
foreach (['do you supply laptops', 'microsoft 365 cloud', 'cyber security', 'iso 27001', 'where are you based'] as $q) {
    $hits = EIT_Chat_KB::search($q, 2);
    $titles = array_map(function ($h) { return $h['title'] . ' (' . $h['score'] . ')'; }, $hits);
    printf("  %-26s -> %s\n", $q, $titles ? implode(' | ', $titles) : 'NO MATCH');
}

echo "=== config ===\n";
printf("  assistant: %s\n", EIT_Chat_Brain::assistant_name());
printf("  model:     %s\n", EIT_Chat_Store::opt('eit_chat_model'));
printf("  enabled:   %s\n", EIT_Chat_Store::opt('eit_chat_enabled'));
printf("  live mode: %s\n", EIT_Chat_Brain::is_live() ? 'YES (api key set)' : 'NO (demo mode)');
