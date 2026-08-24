<?php
/**
 * Undo the accidental noindex on web-design-gilbert-az (ID 2094). It was
 * wrongly caught by a stale copy of azw-thin-pages.php's noindex list that
 * hadn't picked up the list edit removing it - the page carries 956 words
 * of real content, not the 44-word city-swap stub the rest of that batch is.
 *
 *     wp --path=/html eval-file azw-fix-gilbert-robots.php
 */
update_post_meta( 2094, 'rank_math_robots', array( 'index', 'follow' ) );
WP_CLI::line( 'Fixed: ' . print_r( get_post_meta( 2094, 'rank_math_robots', true ), true ) );
