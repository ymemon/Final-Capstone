<?php
/**
 * Plugin Name: AZWebCorp — Business Email offer schema
 * Description: Emits Product/Offer JSON-LD for /business-email/, which advertises eight priced plans but carried no pricing schema.
 * Version:     1.0.0
 * Author:      AZ Web Corp
 *
 * WHY A PLUGIN AND NOT PAGE CONTENT
 * /business-email/ is built in Elementor, so post_content is not its display
 * source - JSON-LD placed there renders to nobody. And schema stored in the
 * database is one careless editor save away from being stripped of its script
 * tags and dumped on the page as raw text, which is exactly what happened to
 * /seo-company-phoenix-az/.
 *
 * WHAT IS ALREADY THERE
 * Rank Math emits BreadcrumbList, FAQPage, Service and WebPage for this page.
 * It does not emit Product or Offer. Only the pricing layer is added here, so
 * nothing is duplicated.
 *
 * WHERE THE PRICES COME FROM
 * Every figure below was read off the rendered page on 2026-09-08, not from a
 * price list or a supplier feed. They are the prices a visitor sees:
 *
 *   Professional Email (Titan)   Light 4.99  Pro 5.99  Premium 7.99  Ultra 11.99
 *   Microsoft 365                Email Essentials 5.99  Email Plus 6.99
 *                                Online Business Essentials 9.99
 *                                Online Business Professional 13.99
 *
 * If the page's pricing changes, this must change with it. Schema that
 * disagrees with the visible price is a structured-data violation and, worse,
 * advertises a price the customer will not be charged. Re-read the page rather
 * than trusting this list.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', function () {
	if ( ! is_page( 'business-email' ) ) {
		return;
	}

	$url = 'https://azwebcorp.com/business-email/';

	/** One monthly-subscription Offer. */
	$offer = function ( $name, $price ) use ( $url ) {
		return array(
			'@type'            => 'Offer',
			'name'             => $name,
			'price'            => $price,
			'priceCurrency'    => 'USD',
			'availability'     => 'https://schema.org/InStock',
			'url'              => $url,
			// Without this the figure reads as a one-off charge rather than a
			// monthly one, which would overstate the value of every plan.
			'priceSpecification' => array(
				'@type'         => 'UnitPriceSpecification',
				'price'         => $price,
				'priceCurrency' => 'USD',
				'unitText'      => 'MONTH',
			),
		);
	};

	$products = array(
		array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Product',
			'name'        => 'Professional Email',
			'brand'       => array( '@type' => 'Brand', 'name' => 'AZWebCorp' ),
			'description' => 'Branded business email on your own domain, with mailbox features and mobile access.',
			'url'         => $url,
			'offers'      => array(
				$offer( 'Light', '4.99' ),
				$offer( 'Pro', '5.99' ),
				$offer( 'Premium', '7.99' ),
				$offer( 'Ultra', '11.99' ),
			),
		),
		array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Product',
			'name'        => 'Microsoft 365 Email and Productivity',
			'brand'       => array( '@type' => 'Brand', 'name' => 'AZWebCorp' ),
			'description' => 'Professional email with Microsoft 365 productivity options.',
			'url'         => $url,
			'offers'      => array(
				$offer( 'Email Essentials', '5.99' ),
				$offer( 'Email Plus', '6.99' ),
				$offer( 'Online Business Essentials', '9.99' ),
				$offer( 'Online Business Professional', '13.99' ),
			),
		),
	);

	foreach ( $products as $product ) {
		echo '<script type="application/ld+json">' . "\n"
			. wp_json_encode( $product, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			. "\n" . '</script>' . "\n";
	}
}, 20 );
