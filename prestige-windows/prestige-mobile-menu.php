<?php
/**
 * Plugin Name: Prestige Mobile Menu Polish
 * Description: Fixes the mobile navigation overlay - opaque panel, correctly sized close button, brand mark, and balanced spacing. Overrides the base styles printed by prestige-home-polish.php.
 * Version: 1.0.0
 *
 * WHY THIS IS A SEPARATE FILE
 * The base mobile-menu markup and CSS live in prestige-home-polish.php, which
 * is 16KB and has a `prestige-home-polish-broken-v2.php.bak` sitting next to
 * it from a previous edit that broke it. Overriding from a small separate file
 * is reversible by deleting one file; editing that one is not.
 *
 * Safe at the mu-plugins ROOT: pure hook registration, no work at include time.
 *
 * WHAT WAS WRONG (reproduced at a real 390px viewport with Playwright,
 * 2026-08-26 - headless Edge silently clamps to ~504px and cannot show this):
 *
 * 1. Panel background was `rgba(8, 10, 11, .98)`. That last 2% let the hero
 *    photograph and the "LUXURY WINDOWS & DOORS" headline ghost through behind
 *    the links. On an OLED phone this reads as a rendering fault, not a design.
 *
 * 2. The close button rendered as a 90x42 slab in the theme's colour instead
 *    of the intended 46x42 cream button. Cause: the theme's base.css styles
 *    `button[type="button"]`, which is specificity (0,1,1) and beats the
 *    single class `.pw-mobile-menu-toggle` at (0,1,0). Same family of bug as
 *    the `*`-has-zero-specificity trap on azwebcorp. Fixed by matching
 *    `button.pw-mobile-menu-toggle` (0,1,1) AND !important, so it wins on both
 *    specificity and cascade order regardless of which stylesheet loads last.
 *
 * 3. The logo sat BEHIND the panel and ghosted through at 2%, so the open menu
 *    looked broken rather than branded. Now drawn inside the panel as a ::before.
 *
 * 4. `justify-content: center` with nine items left a void at the top and
 *    pushed the CTA into the content showing through at the bottom. Now
 *    top-aligned under the brand mark, and scrollable on short viewports.
 *
 * 5. Two hamburgers were stacked - the theme's own `.hfe-nav-menu__toggle`
 *    (Header Footer Elementor) sat underneath ours and could peek out at the
 *    edge once ours was resized. Hidden on mobile.
 */

defined('ABSPATH') || exit;

add_action('wp_head', static function (): void {

    // Same asset the site header and footer already use.
    $logo = 'https://prestigewindowsaz.com/wp-content/uploads/2026/08/prestige-logo-transparent.png';
    ?>
<style id="pw-mobile-menu-polish">
@media (max-width: 1024px) {

    /* ---- Header logo: size and alignment (client request, 26 Aug) ----
       Two problems. The logo sat flush at x=0 while the menu button had an
       18px inset, so the header looked lopsided; and it was too small.

       `width` alone does NOT work here. The theme pins the logo with
       `max-width: 100px; width: 100px` in post-196.css, repeated at five
       breakpoints. Setting only width leaves max-width capping it at 100px
       and the change silently does nothing - which is exactly what happened
       on the first attempt. Both properties have to be overridden.

       The header's height follows the logo, so growing it to 116px moves the
       logo's centre to y=58 and the button has to come down to match. The
       button is position:fixed inside a transformed ancestor, so its centre
       lands at top+33 rather than top+23 - measured, not assumed. top:25px
       puts both centres on 58. */
    .wdt-logo-container {
        padding-left: 18px !important;   /* mirrors the button's 18px right inset */
    }
    .wdt-logo-container img {
        width: 116px !important;
        max-width: 116px !important;
        height: auto !important;
    }
    body:not(.pw-menu-open) button.pw-mobile-menu-toggle {
        top: 25px !important;
    }

    /* The theme's own toggle, stacked underneath ours. */
    .hfe-nav-menu__toggle { display: none !important; }

    /* (0,1,1) to match the theme's button[type="button"], plus !important so
       stylesheet order cannot decide this. */
    button.pw-mobile-menu-toggle {
        position: fixed !important;
        z-index: 100001 !important;
        top: 20px !important;
        right: 18px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 46px !important;
        height: 46px !important;
        min-width: 0 !important;
        padding: 0 !important;
        border: 1px solid #c59b5f !important;
        border-radius: 2px !important;
        background: rgba(11, 13, 14, .92) !important;
        color: #e8dcc8 !important;
        font-family: inherit !important;
        font-size: 22px !important;
        font-weight: 400 !important;
        line-height: 1 !important;
        letter-spacing: 0 !important;
        text-transform: none !important;
        box-shadow: none !important;
    }
    button.pw-mobile-menu-toggle:hover,
    button.pw-mobile-menu-toggle:focus,
    button.pw-mobile-menu-toggle:active {
        background: rgba(197, 155, 95, .16) !important;
        border-color: #d6ae70 !important;
        color: #d6ae70 !important;
        outline: none !important;
    }

    /* Fully opaque. This is the actual bug the client reported. */
    .pw-mobile-menu-panel {
        background: #0b0d0e !important;
        justify-content: flex-start !important;
        gap: 0 !important;
        padding: 96px 28px 44px !important;
        overflow-y: auto !important;
        overscroll-behavior: contain !important;
        -webkit-overflow-scrolling: touch;
    }

    /* Brand mark inside the panel, so the open menu is branded rather than a
       list floating on black. */
    .pw-mobile-menu-panel::before {
        content: "";
        flex: 0 0 auto;
        display: block;
        width: 88px;
        height: 88px;
        margin: 0 auto 22px;
        background: url('<?php echo esc_url($logo); ?>') center center / contain no-repeat;
    }

    .pw-mobile-menu-panel a {
        flex: 0 0 auto;
        padding: 12px 4px !important;
        font-size: 22px !important;
        line-height: 1.25 !important;
        text-decoration: none !important;
    }

    /* Separator between the nav links, but not above the CTA. */
    .pw-mobile-menu-panel a + a:not(:last-child) {
        border-top: 1px solid rgba(197, 155, 95, .16) !important;
    }
    .pw-mobile-menu-panel a { width: 100%; text-align: center; }

    /* The CTA. font-size and letter-spacing are restated here because the
       generic `a` rule above carries !important and would otherwise stretch
       the base 14px button styling up to 22px, which is what made this render
       oversized and full-width. */
    .pw-mobile-menu-panel a:last-child {
        width: auto !important;
        max-width: 100%;
        margin-top: 26px !important;
        padding: 14px 30px !important;
        background: transparent !important;
        border: 1px solid #c59b5f !important;
        border-radius: 2px !important;
        font-family: inherit !important;
        font-size: 13px !important;
        font-weight: 700 !important;
        letter-spacing: .14em !important;
        text-transform: uppercase !important;
        white-space: nowrap;
    }
    .pw-mobile-menu-panel a:last-child + a { border-top: 0 !important; }
    .pw-mobile-menu-panel a:last-child:hover,
    .pw-mobile-menu-panel a:last-child:focus {
        background: rgba(197, 155, 95, .14) !important;
    }
}

/* Shorter viewports: tighten up so the CTA stays above the fold.
   Threshold is 740px, not 620px - a 360x640 Android measured a CTA bottom edge
   of 732px against a 640px viewport, i.e. it fell off-screen. Verified at both
   390x844 (full treatment) and 360x640 (compact). */
@media (max-width: 1024px) and (max-height: 740px) {
    .pw-mobile-menu-panel::before {
        width: 58px;
        height: 58px;
        margin-bottom: 12px;
    }
    .pw-mobile-menu-panel { padding: 74px 24px 28px !important; }
    .pw-mobile-menu-panel a { padding: 8px 4px !important; font-size: 18px !important; }
    .pw-mobile-menu-panel a:last-child {
        margin-top: 16px !important;
        padding: 12px 24px !important;
        font-size: 12px !important;
    }
}

/* Landscape phone: no room for a brand mark at all. */
@media (max-width: 1024px) and (max-height: 500px) {
    .pw-mobile-menu-panel::before { display: none; }
    .pw-mobile-menu-panel { padding-top: 64px !important; }
}
</style>
    <?php
}, 200);   // after prestige-home-polish.php, which prints at 100
