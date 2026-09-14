<?php
/**
 * Apply accessible media-library metadata to the dated evidence images used
 * on /case-studies/.
 *
 *   wp --path=/html eval-file azw-update-case-study-evidence.php       # dry run
 *   wp --path=/html eval-file azw-update-case-study-evidence.php apply # write
 */

$args  = (array) $args;
$apply = in_array( 'apply', $args, true );
$items = array(
	array(
		'file'        => '2026/09/gsc-performance-everything-it-2026-09-09.png',
		'title'       => 'Everything IT Google Search Console performance - September 9, 2026',
		'caption'     => 'Google Search Console performance snapshot captured September 9, 2026.',
		'alt'         => 'Google Search Console performance report captured September 9, 2026, showing 538 clicks, 93,300 impressions, 0.6% click-through rate and average position 29.1 for the latest three months',
		'description' => 'First-party Google Search Console evidence for the Everything IT case study. The screenshot compares the latest three months with the same period last year and was captured September 9, 2026.',
	),
	array(
		'file'        => '2026/09/prestige-windows-homepage-build-2026-09-09.png',
		'title'       => 'Prestige Windows website build - September 9, 2026',
		'caption'     => 'Prestige Windows live homepage captured September 9, 2026.',
		'alt'         => 'Prestige Windows homepage designed and built by AZWebCorp, captured September 9, 2026',
		'description' => 'Dated live-site evidence for the Prestige Windows ground-up website build by AZWebCorp, captured before the new SEO reporting period.',
	),
	array(
		'file'        => '2026/09/azwebcorp-seo-client-portal-2026-09-09.png',
		'title'       => 'AZWebCorp SEO Client Portal - September 9, 2026',
		'caption'     => 'AZWebCorp private SEO client portal dashboard captured September 9, 2026.',
		'alt'         => 'AZWebCorp SEO client portal dashboard showing live analytics, Search Console metrics, charts and reporting tools in one private workspace',
		'description' => 'Dated product screenshot of the AZWebCorp SEO Client Portal. The AZ Web Corp workspace is shown so the platform can be documented without exposing another client\'s private account.',
	),
	array(
		'file'        => '2026/09/azwebcorp-public-seo-audit-tool-2026-09-09.png',
		'title'       => 'AZWebCorp Public SEO Audit - September 9, 2026',
		'caption'     => 'AZWebCorp public SEO audit example report captured September 9, 2026.',
		'alt'         => 'AZWebCorp public SEO audit report showing an overall health score, result breakdown, category scores, Core Web Vitals and detailed findings',
		'description' => 'Dated product screenshot of the AZWebCorp public SEO audit tool. The example report uses a publicly accessible domain and does not imply a client, partner or endorsement relationship.',
	),
);

WP_CLI::line( $apply ? 'Mode: apply' : 'Mode: dry run' );

foreach ( $items as $item ) {
	$attachments = get_posts( array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => 2,
		'meta_key'       => '_wp_attached_file',
		'meta_value'     => $item['file'],
	) );

	if ( 1 !== count( $attachments ) ) {
		WP_CLI::error( sprintf( 'Expected one attachment for %s; found %d.', $item['file'], count( $attachments ) ) );
	}

	$attachment = $attachments[0];
	WP_CLI::line( sprintf( 'Attachment %d: %s', $attachment->ID, wp_get_attachment_url( $attachment->ID ) ) );
	WP_CLI::line( '  Title: ' . $item['title'] );
	WP_CLI::line( '  Caption: ' . $item['caption'] );
	WP_CLI::line( '  Alt: ' . $item['alt'] );

	if ( ! $apply ) {
		continue;
	}

	$updated = wp_update_post( array(
		'ID'           => $attachment->ID,
		'post_title'   => $item['title'],
		'post_excerpt' => $item['caption'],
		'post_content' => $item['description'],
	), true );

	if ( is_wp_error( $updated ) ) {
		WP_CLI::error( $updated->get_error_message() );
	}

	update_post_meta( $attachment->ID, '_wp_attachment_image_alt', $item['alt'] );
}

WP_CLI::success( $apply ? 'Attachment metadata updated.' : 'Dry run complete; nothing changed.' );
