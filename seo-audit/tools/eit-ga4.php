<?php
/**
 * Plugin Name: Everything IT GA4
 * Description: Adds the approved Google Analytics 4 measurement tag sitewide.
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

const EIT_GA4_MEASUREMENT_ID = 'G-927C6L1W1C';

/** Output the GA4 loader once, early in the document head. */
function eit_ga4_output_tag() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}

	$measurement_id = EIT_GA4_MEASUREMENT_ID;
	?>
	<!-- Everything IT Google Analytics 4 -->
	<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $measurement_id ); ?>"></script>
	<script>
	window.dataLayer = window.dataLayer || [];
	function gtag(){dataLayer.push(arguments);}
	gtag('js', new Date());
	gtag('config', '<?php echo esc_js( $measurement_id ); ?>');
	</script>
	<?php
}
add_action( 'wp_head', 'eit_ga4_output_tag', 1 );
