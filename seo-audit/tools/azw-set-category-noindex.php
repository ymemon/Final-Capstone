<?php
/** One-time WP-CLI helper: set Rank Math category archive defaults safely. */

$titles = get_option( 'rank-math-options-titles', array() );
$titles['tax_category_custom_robots'] = 'on';
$titles['tax_category_robots']        = array( 'noindex' );
update_option( 'rank-math-options-titles', $titles );

WP_CLI::success( 'Category robots default set to noindex.' );
