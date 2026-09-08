<?php
/**
 * Plugin Name: AZWebCorp — Arizona SEO page FAQ schema
 * Description: Emits FAQPage JSON-LD for /arizona-seo-services/, whose FAQ is an Elementor accordion that Rank Math does not detect.
 * Version:     1.0.0
 * Author:      AZ Web Corp
 *
 * WHY THIS IS A PLUGIN AND NOT PAGE CONTENT
 * Two reasons, both learned the hard way on this exact page:
 *
 *   1. /arizona-seo-services/ is built in Elementor. post_content is not its
 *      display source, so schema placed there renders to nobody.
 *   2. Saving a page in the WordPress editor strips <script> tags but keeps
 *      their contents. On /seo-company-phoenix-az/ that turned three JSON-LD
 *      blocks into 3KB of raw JSON displayed to visitors. Schema kept in the
 *      database is one careless save away from that.
 *
 * Rank Math already emits WebPage, Service and BreadcrumbList for this page.
 * Only FAQPage is missing, because the questions live in <details>/<summary>
 * accordion markup that Rank Math's FAQ detection does not read. This adds
 * that one type and nothing else, so there is no duplicate schema.
 *
 * The answers below must stay byte-identical to the visible copy on the page.
 * If the page's FAQ is edited, edit this too, or remove it — schema that does
 * not match the page is a structured-data violation, which is worse than
 * having none.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', function () {
	if ( ! is_page( 'arizona-seo-services' ) ) {
		return;
	}

	$faqs = array(
		array(
			'Can you guarantee first-place rankings?',
			'No ethical SEO provider can guarantee a specific Google ranking. Rankings depend on competition, search intent, location, the user’s device, site quality, search-algorithm changes, and many factors outside any agency’s control. We focus on the work most likely to improve your technical foundation, relevance, visibility, and ability to convert qualified visitors.',
		),
		array(
			'How long does SEO take?',
			'The timeline depends on your website’s condition, market competition, existing authority, service area, content needs, and the amount of work required. Some technical fixes can be completed quickly. Meaningful organic-growth results commonly require consistent work and measurement over several months.',
		),
		array(
			'Do you work only with Arizona businesses?',
			'Arizona is our home market, and we work extensively with businesses in Phoenix, Mesa, Gilbert, Tempe, Chandler, Scottsdale, the East Valley, Tucson, and statewide. We also provide SEO and web-development services for businesses across the United States.',
		),
		array(
			'Do I need a new website before starting SEO?',
			'Not always. We begin with an audit and determine whether your existing website can be improved effectively or whether a redesign, WordPress rebuild, or custom development project would create a stronger foundation.',
		),
		array(
			'Do you perform the implementation work?',
			'Yes. We can provide strategy and recommendations, work with your internal team, or implement technical and website changes directly. Our WordPress and custom web-development experience allows us to address many SEO recommendations in-house.',
		),
	);

	$entities = array();
	foreach ( $faqs as $faq ) {
		$entities[] = array(
			'@type'          => 'Question',
			'name'           => $faq[0],
			'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $faq[1] ),
		);
	}

	$schema = array(
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => $entities,
	);

	echo '<script type="application/ld+json">' . "\n"
		. wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		. "\n" . '</script>' . "\n";
}, 20 );
