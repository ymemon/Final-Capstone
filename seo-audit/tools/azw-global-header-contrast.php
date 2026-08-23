<?php
/**
 * Plugin Name: AZW Global Header Contrast
 * Description: Keeps primary navigation readable over every page background.
 */

defined('ABSPATH') || exit;

add_action('wp_footer', static function () {
    ?>
    <style id="azw-global-header-contrast" data-noptimize="1">
    /* azw-hero-unify: one hero palette across the site. ------------- */

    /* domain-registration carried a second lime (#e8f01a), missed by the
       Elementor colour pass because that pass targeted #e9f027. */
    .azwc-domain-shell {
        background-image: radial-gradient(circle at 85% 10%,rgba(230,184,77,.24),rgba(0,0,0,0) 60%), linear-gradient(135deg,#050608,#111823 60%,#30240a) !important;
    }
    .azwc-domain-kicker {
        color: #f5d47d !important;
    }
    /* azw-tld-gold: these are lime-filled pills/buttons, so the fill is
       what needs replacing - not the text colour. */
    .azwc-tld-slider__item,
    .azwc-official-search button,
    .azwc-official-search input[type="submit"] {
        background: #e6b84d !important;
        border-color: #e6b84d !important;
        color: #161208 !important;
    }
    .azwc-tld-slider__item:hover,
    .azwc-official-search button:hover,
    .azwc-official-search input[type="submit"]:hover {
        background: #f5d47d !important;
        border-color: #f5d47d !important;
        color: #161208 !important;
    }

    /* ssl was the only light hero on the site. */
    .azwc-catalog-head {
        background: linear-gradient(135deg,#050608,#111823 60%,#30240a) !important;
    }
    /* Set by a higher-specificity rule in azwebcorp-security-pages.php,
       so qualify with body + element to outrank it. */
    /* Must include the #azwc-ss id: the security-pages stylesheet sets
       this heading with an ID selector and !important, which outranks any
       class-only override no matter how many !importants it carries. */
    #azwc-ss .azwc-catalog-head h1,
    #azwc-ss .azwc-catalog-head h2,
    #azwc-ss .azwc-catalog-head p,
    body .azwc-catalog-head h1,
    body section.azwc-catalog-head h1,
    body .azwc-catalog-head h2,
    body section.azwc-catalog-head h2 {
        color: #ffffff !important;
        -webkit-text-fill-color: #ffffff !important;
    }
    .azwc-catalog-head p,
    .azwc-catalog-head li {
        color: #d6dce4 !important;
    }

    /* Shared hero type scale. */
    .azwc-hero h1,
    .azwc-domain-shell h1,
    .azwc-catalog-head h1 {
        color: #ffffff !important;
    }
    .azwc-hero p,
    .azwc-domain-shell p {
        color: #d6dce4 !important;
    }
    .azwc-hero .azwc-kicker,
    .azwc-hero .azwc-eyebrow,
    .azwc-hero .azwc-location {
        color: #f5d47d !important;
    }
    .azwc-hero .azwc-eyebrow::before,
    .azwc-hero .azwc-kicker::before {
        background: #e6b84d !important;
    }

    /* Buttons. */
    .azwc-hero .azwc-btn-primary,
    .azwc-domain-shell .azwc-btn-primary,
    .azwc-catalog-head .azwc-btn-primary {
        background: #e6b84d !important;
        border-color: #e6b84d !important;
        color: #161208 !important;
    }
    .azwc-hero .azwc-btn-ghost,
    .azwc-domain-shell .azwc-btn-ghost,
    .azwc-catalog-head .azwc-btn-ghost {
        background: transparent !important;
        border: 1px solid rgba(255,255,255,.25) !important;
        color: #ffffff !important;
    }

    /* The breadcrumb strip sat light directly above a dark hero. */
    .breadcrumbs {
        background: #111823 !important;
        border-color: rgba(255,255,255,.08) !important;
    }
    .breadcrumbs,
    .breadcrumbs a,
    .breadcrumbs span,
    .breadcrumbs .breadcrumb_last {
        color: #d6dce4 !important;
    }
    .breadcrumbs a:hover,
    .breadcrumbs a:focus {
        color: #e6b84d !important;
    }

    /* azw-form-gold: FluentForm submit buttons still used the legacy lime
       (#e9f027) for text, and a lime fill on hover. Bring them onto the
       gold so forms match the rest of the site. */
    form[class*="fluent_form"] .ff-btn-submit,
    form[class*="fluent_form"] button.ff-btn-submit {
        color: #e6b84d !important;
        border-color: #e6b84d !important;
    }
    form[class*="fluent_form"] .ff-btn-submit:hover,
    form[class*="fluent_form"] .ff-btn-submit:focus,
    form[class*="fluent_form"] button.ff-btn-submit:hover,
    form[class*="fluent_form"] button.ff-btn-submit:focus {
        background-color: #e6b84d !important;
        border-color: #e6b84d !important;
        color: #161208 !important;
    }

    /* azw-footer-gold: theme accent is a bright lime (#e9f027); the rest of
       the site - header CTA, page buttons - uses gold (#e6b84d). Unify the
       footer onto the gold. Scoped to .site-footer on purpose. */
    .site-footer h1, .site-footer h2, .site-footer h3,
    .site-footer h4, .site-footer h5, .site-footer h6,
    .site-footer .widget-title,
    .site-footer .wp-block-heading {
        color: #e6b84d !important;
    }
    .site-footer .widget .widget-title::before {
        background-color: #e6b84d !important;
    }
    .site-footer a:hover, .site-footer a:focus, .site-footer a:active,
    .site-footer .widget ul li a:hover, .site-footer .widget ul li a:focus,
    .site-footer .site-info a:hover, .site-footer .site-info a:focus {
        color: #e6b84d !important;
    }
    .site-footer .social-profile ul li a:hover,
    .site-footer .social-profile ul li a:focus,
    .site-footer .widget .tagcloud a:hover,
    .site-footer .widget .tagcloud a:focus {
        background-color: #e6b84d !important;
        border-color: #e6b84d !important;
    }

    /* azw-overlay-fix ------------------------------------------------
       Theme paints .overlay solid white across the header strip, hiding
       the dark bar below and leaving white nav text on white. Kill it in
       the non-sticky state; the sticky header is genuinely white and is
       left alone. */
    body header.site-header.header-two:not(.sticky-header) .bottom-header > .overlay,
    body header.site-header.header-two:not(.sticky-header) .overlay-header > .overlay {
        background: transparent !important;
    }

    /* Stock logo is dark ink, unreadable on the dark bar. Swap to the light
       variant (attachment 2480) only while the header is dark; `content` on
       a replaced element is the least invasive swap and needs no markup
       change. Sticky/white header keeps the original. */
    body header.site-header.header-two:not(.sticky-header) .site-branding img#headerLogo,
    body header.site-header.header-two:not(.sticky-header) .site-branding img {
        content: url('https://azwebcorp.com/wp-content/uploads/2026/08/Azwebcorp-light_logo.png');
    }

    body header.site-header.header-two:not(.sticky-header) .bottom-header {
        background: rgba(7, 9, 13, .92) !important;
        -webkit-backdrop-filter: blur(10px);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(255, 255, 255, .10) !important;
    }
    body.home header#masthead.header-two:not(.sticky-header) .overlay-header,
    body.home header#masthead.header-two:not(.sticky-header) .bottom-header {
        background: #07090d !important;
    }
    body.home header#masthead .site-branding,
    body.home header#masthead .main-navigation,
    body.home header#masthead .header-icons,
    body.home header#masthead .mobile-menu-container {
        position: relative;
        z-index: 1000;
        opacity: 1 !important;
        visibility: visible !important;
    }
    body header.site-header.header-two:not(.sticky-header) .main-navigation ul.menu > li > a {
        color: #fff !important;
        -webkit-text-fill-color: #fff !important;
        text-shadow: none !important;
    }
    /* azw-no-capsules: plain white labels, same as every other page. No
       display:flex here on purpose - the theme's own layout centres the
       text correctly, and the flex override was what knocked it off. */
    body.home header#masthead.header-two:not(.sticky-header) .main-navigation ul.menu > li > a {
        color: #fff !important;
        -webkit-text-fill-color: #fff !important;
        background: transparent !important;
        border: 0 !important;
        border-radius: 0 !important;
        opacity: 1 !important;
        visibility: visible !important;
        mix-blend-mode: normal !important;
    }
    body header.site-header.header-two:not(.sticky-header) .main-navigation ul.menu > li > a:hover,
    body header.site-header.header-two:not(.sticky-header) .main-navigation ul.menu > li > a:focus,
    body header.site-header.header-two:not(.sticky-header) .main-navigation ul.menu > li > a:active,
    body header.site-header.header-two:not(.sticky-header) .main-navigation ul.menu > li:hover > a,
    body header.site-header.header-two:not(.sticky-header) .main-navigation ul.menu > li.current-menu-item > a,
    body header.site-header.header-two:not(.sticky-header) .main-navigation ul.menu > li.current_page_item > a {
        color: #e6b84d !important;
    }
    body.home header#masthead.header-two:not(.sticky-header) .main-navigation ul.menu > li > a:hover,
    body.home header#masthead.header-two:not(.sticky-header) .main-navigation ul.menu > li > a:focus,
    body.home header#masthead.header-two:not(.sticky-header) .main-navigation ul.menu > li > a:active,
    body.home header#masthead.header-two:not(.sticky-header) .main-navigation ul.menu > li:hover > a,
    body.home header#masthead.header-two:not(.sticky-header) .main-navigation ul.menu > li.current-menu-item > a,
    body.home header#masthead.header-two:not(.sticky-header) .main-navigation ul.menu > li.current_page_item > a {
        color: #e6b84d !important;
        -webkit-text-fill-color: #e6b84d !important;
        background: transparent !important;
        border-color: transparent !important;
    }
    body header.site-header.header-two.sticky-header .bottom-header {
        background: #fff !important;
    }
    body header.site-header.header-two.sticky-header .main-navigation ul.menu > li > a {
        color: #111820 !important;
    }
    body header.site-header.header-two.sticky-header .main-navigation ul.menu > li > a:hover,
    body header.site-header.header-two.sticky-header .main-navigation ul.menu > li > a:focus,
    body header.site-header.header-two.sticky-header .main-navigation ul.menu > li:hover > a,
    body header.site-header.header-two.sticky-header .main-navigation ul.menu > li.current-menu-item > a,
    body header.site-header.header-two.sticky-header .main-navigation ul.menu > li.current_page_item > a {
        color: #9b711b !important;
    }
    body #masthead .main-navigation ul.menu ul {
        background: #fff !important;
    }
    body #masthead .main-navigation ul.menu ul li a {
        color: #111820 !important;
    }
    body #masthead .main-navigation ul.menu ul li a:hover,
    body #masthead .main-navigation ul.menu ul li a:focus,
    body #masthead .main-navigation ul.menu ul li a:active {
        color: #604b16 !important;
        background: #f5f7f9 !important;
    }
    body header.site-header.header-two:not(.sticky-header) .header-icons a,
    body header.site-header.header-two:not(.sticky-header) .header-icons .search-icon {
        color: #fff !important;
    }
    body header.site-header .header-btn-1.button-primary {
        background: #e6b84d !important;
        color: #161208 !important;
    }
    body header.site-header .header-btn-1.button-primary:hover,
    body header.site-header .header-btn-1.button-primary:focus {
        background: #f5d47d !important;
        color: #161208 !important;
    }
    body #masthead .slicknav_nav {
        background: #07090d !important;
    }
    body #masthead .slicknav_nav a {
        color: #fff !important;
    }
    body #masthead .slicknav_nav a:hover,
    body #masthead .slicknav_nav a:focus {
        color: #e6b84d !important;
        background: #11161e !important;
    }
    body #masthead .slicknav_menutxt {
        color: #fff !important;
    }
    body #masthead .slicknav_icon-bar,
    body #masthead .slicknav_icon-bar::before,
    body #masthead .slicknav_icon-bar::after {
        background: #fff !important;
    }
    @media (min-width: 992px) {
        body.home header#masthead .main-navigation {
            display: block !important;
        }
    }
    </style>
    <?php
}, 999);
