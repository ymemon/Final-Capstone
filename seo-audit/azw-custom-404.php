<?php
/**
 * Plugin Name: AZWebCorp custom 404 page
 * Description: Swaps the theme's bare "Oops" 404 for a solutions directory
 * so lost visitors and dead links from retired content land on something
 * useful, not a dead end.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'template_include', function ( $template ) {
	if ( is_404() ) {
		$custom = WPMU_PLUGIN_DIR . '/azw-404/azw-custom-404-template.php';
		if ( file_exists( $custom ) ) {
			return $custom;
		}
	}
	return $template;
}, PHP_INT_MAX );
