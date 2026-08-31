<?php
/**
 * Plugin Name: AZW Category Archive Noindex
 * Description: Keeps thin WordPress category archives crawlable but out of Google's index.
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'rank_math/frontend/robots',
	static function ( $robots ) {
		if ( is_category() ) {
			$robots['index']  = 'noindex';
			$robots['follow'] = 'follow';
		}
		return $robots;
	},
	PHP_INT_MAX
);
