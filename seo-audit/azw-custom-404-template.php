<?php
/**
 * Custom 404 template — replaces the theme's bare "Oops" page with a
 * helpful solutions directory so lost visitors (and dead links from old
 * content) land on something useful instead of a dead end.
 */

get_header();

$solutions = array(
	'SEO &amp; Marketing'      => array(
		array( 'Arizona SEO Services', '/arizona-seo-services/' ),
		array( 'SEO Company Serving Phoenix, AZ', '/seo-company-phoenix-az/' ),
		array( 'Arizona Digital Marketing', '/arizona-digital-marketing/' ),
		array( 'Free SEO Audit', '/free-seo-audit/' ),
	),
	'Web Design &amp; Development' => array(
		array( 'Arizona Web Development &amp; Web Design', '/web-development/' ),
		array( 'Arizona Web Design', '/arizona-web-design/' ),
		array( 'Website Builder for Small Businesses', '/website-builder/' ),
	),
	'Hosting &amp; Domains'     => array(
		array( 'Web Hosting Plans &amp; Pricing', '/hosting-domains/' ),
		array( 'Managed WordPress Hosting', '/wordpress-hosting/' ),
		array( 'SSD VPS Hosting', '/vps-hosting/' ),
		array( 'Domain Registration', '/domain-registration/' ),
		array( 'Business Email', '/business-email/' ),
		array( 'SSL Certificates', '/ssl/' ),
		array( 'Website Security', '/website-security/' ),
	),
	'Company'                => array(
		array( 'About AZWebCorp', '/about-azwebcorp/' ),
		array( 'Tech Industry Blog', '/tech/' ),
		array( 'Featured Projects', '/our-featured-projects/' ),
		array( 'Contact Us', '/contact-us/' ),
	),
);
?>
<div id="content" class="site-content">
	<div class="container">
		<section class="error-404 not-found" style="padding:56px 0 24px;text-align:center;">
			<header class="page-header">
				<p style="font-size:15px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#1e40af;margin:0 0 10px;">Error 404</p>
				<h1 class="page-title" style="font-size:clamp(28px,4vw,42px);margin:0 0 16px;">We couldn&rsquo;t find that page</h1>
				<p style="max-width:620px;margin:0 auto 28px;font-size:17px;line-height:1.6;color:#4b5563;">The link you followed may be outdated, or the page may have moved. Try a search, or jump straight to one of our services below.</p>
			</header>
			<div class="error-404-form" style="max-width:480px;margin:0 auto 12px;">
				<?php get_search_form(); ?>
			</div>
		</section>

		<section class="error-404-solutions" style="padding:24px 0 64px;">
			<h2 style="text-align:center;font-size:22px;margin:0 0 32px;">Explore Our Solutions</h2>
			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:32px;max-width:1100px;margin:0 auto;">
				<?php foreach ( $solutions as $group_title => $links ) : ?>
					<div>
						<h3 style="font-size:13px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#1e40af;margin:0 0 14px;border-bottom:2px solid #e5e7eb;padding-bottom:10px;"><?php echo $group_title; ?></h3>
						<ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px;">
							<?php foreach ( $links as $link ) : ?>
								<li><a href="<?php echo esc_url( home_url( $link[1] ) ); ?>" style="color:#1f2937;text-decoration:none;font-size:15px;"><?php echo esc_html( $link[0] ); ?> &rarr;</a></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</div>
			<div style="text-align:center;margin-top:48px;">
				<a href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>" style="display:inline-block;background:#1e40af;color:#fff;padding:14px 32px;border-radius:6px;text-decoration:none;font-weight:600;">Talk to Us About Your Project</a>
			</div>
		</section>
	</div>
</div>
<?php
get_footer();
