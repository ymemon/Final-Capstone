<?php
/**
 * Plugin Name: Prestige Homepage Polish
 * Description: Focused homepage and primary-navigation presentation fixes.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_head', static function (): void {
    ?>
    <style id="prestige-home-polish">
        /* About page: remove the theme-generated title/breadcrumb banner entirely. */
        body.page-id-223 .main-title-section-wrapper {
            display: none !important;
        }
        body.page-id-223 #main {
            padding-top: 0;
        }
        body.page-id-223 .elementor-element-60f5377 {
            padding-top: clamp(58px, 7vw, 105px) !important;
            padding-bottom: clamp(58px, 7vw, 100px) !important;
        }
        body.page-id-223 .wdt-heading-content-wrapper,
        body.page-id-223 .elementor-widget-text-editor {
            font-size: 16px;
            line-height: 1.75;
        }
        body.page-id-223 .wdt-heading-title {
            line-height: 1.15;
        }

        /* Defend footer readability from aggressive theme link styles. */
        .pw-footer .pw-footer-links a,
        .pw-footer .pw-footer-contact a,
        .pw-footer .pw-footer-contact span {
            color: #eee8de !important;
            opacity: 1 !important;
        }
        .pw-footer .pw-footer-bottom-inner,
        .pw-footer .pw-footer-legal a {
            color: #c9c2b7 !important;
        }

        /* Keep primary navigation labels distinct and comfortably clickable. */
        .elementor-location-header .hfe-nav-menu {
            column-gap: clamp(8px, 1vw, 18px);
        }
        .elementor-location-header .hfe-nav-menu > li > .hfe-menu-item {
            padding-left: 8px !important;
            padding-right: 8px !important;
            white-space: nowrap;
        }

        /* The theme's breadcrumb icon font is not rendering; provide a reliable separator. */
        .main-title-section-wrapper .breadcrumb {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .main-title-section-wrapper .breadcrumb .wdticon-angle-right {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 12px;
            height: 18px;
            font-size: 0;
        }
        .main-title-section-wrapper .breadcrumb .wdticon-angle-right::before {
            content: "›";
            color: #c99a3b;
            font-family: Arial, sans-serif;
            font-size: 22px;
            font-weight: 400;
            line-height: 1;
        }

        /* Give the homepage hero a deliberate, balanced two-column composition. */
        body.home .wdt-cus-slider-fractiontype-tmp {
            min-height: min(720px, calc(100vh - 110px));
            align-items: center;
        }
        body.home .wdt-cus-slider-fractiontype-tmp > .elementor-container {
            align-items: center;
        }
        body.home .wdt-cus-slider-fractiontype-tmp .elementor-element-3924af1 > .elementor-widget-wrap {
            position: relative;
            z-index: 2;
            padding: clamp(34px, 5vw, 76px) clamp(24px, 4.5vw, 68px);
            background: linear-gradient(135deg, rgba(8, 13, 18, .96), rgba(12, 20, 27, .88));
            border-left: 3px solid #c99a3b;
            box-shadow: 0 22px 60px rgba(0, 0, 0, .24);
        }
        body.home .wdt-cus-slider-fractiontype-tmp .wdt-heading-title,
        body.home .wdt-cus-slider-fractiontype-tmp .wdt-heading-title-wrapper {
            color: #fff !important;
            text-shadow: 0 2px 18px rgba(0, 0, 0, .28);
        }
        body.home .wdt-cus-slider-fractiontype-tmp .wdt-heading-title {
            font-size: clamp(38px, 4.2vw, 68px);
            line-height: 1.06;
            letter-spacing: -.025em;
        }
        body.home .wdt-cus-slider-fractiontype-tmp .wdt-heading-content-wrapper {
            color: #e5e7e9 !important;
            font-size: clamp(16px, 1.25vw, 20px);
            line-height: 1.7;
            margin-top: 22px;
        }
        body.home .wdt-cus-slider-fractiontype-tmp .elementor-element-23a5037 {
            margin-top: 26px;
        }
        body.home .wdt-cus-slider-fractiontype-tmp .elementor-element-375bcb5 img {
            min-height: 560px;
            width: 100%;
            object-fit: cover;
        }

        @media (max-width: 1024px) {
            html, body {
                width: 100% !important;
                max-width: 100% !important;
                overflow-x: hidden !important;
            }
            body * {
                box-sizing: border-box;
            }
            .elementor-section,
            .elementor-container,
            .elementor-column,
            .elementor-widget-wrap,
            .e-con,
            .e-con-inner {
                max-width: 100% !important;
            }
            .elementor-section .elementor-container {
                flex-wrap: wrap !important;
            }
            .elementor-column {
                width: 100% !important;
            }
            h1, h2, h3, h4, p, a, span {
                overflow-wrap: anywhere;
            }
            .mystickyelements-fixed,
            .hfe-site-header-cart-li,
            .woocommerce-custom-menu-item {
                display: none !important;
            }
            .pw-mobile-menu-toggle {
                display: flex !important;
            }
            body.home .wdt-cus-slider-fractiontype-tmp .elementor-element-3924af1 > .elementor-widget-wrap {
                width: calc(100% - 24px) !important;
                margin: 12px !important;
                padding: 32px 22px !important;
            }
            body.home .wdt-cus-slider-fractiontype-tmp .wdt-heading-title {
                font-size: clamp(28px, 9vw, 40px) !important;
            }
            body.page-id-223 .elementor-element-60f5377 .elementor-column,
            body.page-id-223 .elementor-element-60f5377 .elementor-widget-wrap {
                width: 100% !important;
                max-width: 100% !important;
            }
            body.page-id-223 .elementor-element-60f5377 {
                padding-left: 16px !important;
                padding-right: 16px !important;
            }
            body.page-id-223 .wdt-heading-title {
                font-size: clamp(28px, 8vw, 40px) !important;
            }
            .pw-footer-cta h2,
            .pw-footer * {
                max-width: 100% !important;
            }
            .pw-footer-cta h2 {
                font-size: 25px !important;
                overflow-wrap: normal;
            }
            .main-title-section h1 {
                font-size: clamp(23px, 7vw, 34px) !important;
                letter-spacing: .12em !important;
            }
            .wdt-accordion-toggle-holder,
            .wdt-accordion-toggle-title-holder,
            .wdt-accordion-toggle-description {
                width: 100% !important;
                max-width: 100% !important;
            }
            table {
                width: 100% !important;
                table-layout: fixed;
            }
            body.home .wdt-cus-slider-fractiontype-tmp {
                min-height: auto;
            }
            body.home .wdt-cus-slider-fractiontype-tmp .elementor-element-3924af1 > .elementor-widget-wrap {
                margin: 20px;
            }
            body.home .wdt-cus-slider-fractiontype-tmp .elementor-element-375bcb5 img {
                min-height: 380px;
            }
        }

        .pw-mobile-menu-toggle,
        .pw-mobile-menu-panel {
            display: none;
        }
        @media (max-width: 1024px) {
            .pw-mobile-menu-toggle {
                position: absolute;
                z-index: 100001;
                top: 22px;
                right: 18px;
                width: 46px;
                height: 42px;
                align-items: center;
                justify-content: center;
                padding: 0;
                border: 1px solid #c59b5f;
                background: #fffaf4;
                color: #151515;
                font-size: 24px;
                line-height: 1;
                cursor: pointer;
            }
            .pw-mobile-menu-panel.is-open {
                display: flex;
            }
            .pw-mobile-menu-panel {
                position: fixed;
                z-index: 100000;
                inset: 0;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                gap: 19px;
                padding: 80px 28px 40px;
                background: rgba(8, 10, 11, .98);
            }
            .pw-mobile-menu-panel a {
                color: #f7f1e8 !important;
                font-family: Georgia, serif;
                font-size: 24px;
                letter-spacing: .06em;
            }
            .pw-mobile-menu-panel a:last-child {
                margin-top: 12px;
                padding: 13px 20px;
                border: 1px solid #c59b5f;
                color: #d6ae70 !important;
                font-family: inherit;
                font-size: 14px;
                font-weight: 700;
                text-transform: uppercase;
            }
            body.pw-menu-open {
                overflow: hidden !important;
            }
        }
    </style>
    <?php
}, 100);

add_action('wp_footer', static function (): void {
    ?>
    <button class="pw-mobile-menu-toggle" type="button" aria-label="Open navigation" aria-expanded="false">☰</button>
    <nav class="pw-mobile-menu-panel" aria-label="Mobile navigation">
        <a href="/">Home</a><a href="/about/">About Us</a><a href="/products/">Products</a>
        <a href="/window-configurator/">Window Configurator</a><a href="/gallery/">Gallery</a>
        <a href="/blog/">Blog</a><a href="/faq/">FAQ</a><a href="/contact/">Contact</a>
        <a href="/contact/">Book Consultation</a>
    </nav>
    <script>
    (() => {
      const button = document.querySelector('.pw-mobile-menu-toggle');
      const panel = document.querySelector('.pw-mobile-menu-panel');
      if (button && panel) button.addEventListener('click', () => {
        const open = panel.classList.toggle('is-open');
        document.body.classList.toggle('pw-menu-open', open);
        button.textContent = open ? '×' : '☰';
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        button.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
      });
    })();
    </script>
    <?php
}, 100);

add_action('template_redirect', static function (): void {
    if (is_page('yith-compare')) {
        wp_safe_redirect(home_url('/products/'), 301);
        exit;
    }
    ob_start(static function (string $html): string {
        $replacements = [
            'vindors@example.com' => 'info@prestigewindowsaz.com',
            'vendors@example.com' => 'info@prestigewindowsaz.com',
            'or a contractor exploring a distribution partnership' => 'or want guidance selecting the right windows for your property',
            'or a fellow contractor seeking a trusted distribution partner for premium window products' => 'or are planning a distinctive renovation or new-build project',
            'We are authorized dealers for three of the most respected premium window brands in the industry: Pella, Renewal by Andersen, and American Windows &amp; Doors. Each brand is carefully selected for its quality, warranty support, and design range.' => 'We help clients evaluate well-established window and door systems based on design, performance, warranty coverage, and suitability for the project. Available brands and product lines are confirmed during consultation.',
            'Yes. Prestige Windows serves as an authorized distributor for other licensed private contractors. If you are a contractor seeking access to our premium window lines at distributor pricing, please contact us to discuss partnership opportunities.' => 'Yes. We coordinate with homeowners, architects, builders, and licensed trade professionals when a project requires close collaboration. Contact us to discuss the scope, specifications, and installation requirements.',
            '>Screenshot<' => '>Prestige Windows project<',
        ];
        $html = str_replace(array_keys($replacements), array_values($replacements), $html);
        if (is_page('gallery')) {
            $html = preg_replace('/<img(?![^>]*\balt=)([^>]*)>/i', '<img alt="Prestige Windows installation project"$1>', $html);
            $html = preg_replace('/<img([^>]*)\balt=("|\')\s*\2([^>]*)>/i', '<img$1 alt="Prestige Windows installation project"$3>', $html);
        }
        return $html;
    });
}, 0);

add_filter('the_content', static function (string $content): string {
    if (!is_page('terms') || !in_the_loop() || !is_main_query()) return $content;
    return '<div class="pw-legal-content" style="max-width:980px;margin:70px auto;padding:0 24px 80px;line-height:1.75">'
        . '<h2>Terms and Conditions</h2><p><strong>Effective date: August 17, 2026</strong></p>'
        . '<p>These terms govern your use of the Prestige Windows website and requests for consultations, estimates, products, or installation services. By using this website, you agree to these terms.</p>'
        . '<h3>Estimates and project agreements</h3><p>Website information and preliminary discussions are not binding estimates. Product selection, measurements, pricing, schedules, warranties, and payment terms are confirmed in a separate written proposal or service agreement.</p>'
        . '<h3>Website information</h3><p>We work to keep product and service information accurate, but availability, specifications, colors, pricing, and manufacturer programs may change. Images are illustrative and actual products may vary.</p>'
        . '<h3>Intellectual property</h3><p>Site text, branding, graphics, and original media may not be reproduced or used commercially without written permission. Third-party trademarks remain the property of their respective owners.</p>'
        . '<h3>Acceptable use</h3><p>You may not misuse the website, interfere with its operation, attempt unauthorized access, or submit unlawful, deceptive, or harmful material through its forms.</p>'
        . '<h3>Third-party services</h3><p>The website may link to or use third-party services. Prestige Windows is not responsible for the content, availability, or privacy practices of independent third parties.</p>'
        . '<h3>Limitation of website liability</h3><p>The website is provided for general informational purposes. To the extent permitted by law, Prestige Windows is not liable for indirect or consequential losses arising solely from use of this website.</p>'
        . '<h3>Changes</h3><p>We may revise these terms by posting an updated version on this page. Continued use of the website after an update constitutes acceptance of the revised terms.</p>'
        . '<h3>Contact</h3><p>Questions may be sent to <a href="mailto:info@prestigewindowsaz.com">info@prestigewindowsaz.com</a> or directed to Prestige Windows at 34462 N Scottsdale Rd, Scottsdale, AZ 85266.</p>'
        . '</div>';
}, 999);

add_filter('rank_math/frontend/description', static function ($description) {
    if (is_page('about')) return 'Meet Prestige Windows, a Scottsdale window and door specialist focused on thoughtful product selection, skilled installation, and personal service.';
    if (is_page('privacy')) return 'Read how Prestige Windows collects, uses, protects, and manages information submitted through our website and consultation forms.';
    return $description;
});

add_filter('rank_math/frontend/canonical', static function ($canonical) {
    if (is_page()) return get_permalink();
    return $canonical;
});
