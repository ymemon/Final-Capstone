<?php
/**
 * Plugin Name: PV Logo Aspect Fix
 * Description: Stops the header logo being horizontally stretched.
 * Version: 1.0.0
 *
 * Mike Bustard, 26 January 2026, item 1 of his fix list: "Page header has the
 * PV logo stretched wide." Still unfixed on 28 August 2026 — the oldest
 * outstanding request in the whole thread, seven months old.
 *
 * Measured on the live 1440px header:
 *   source   logopv-2-140x81.png   140 x 81   ratio 1.728
 *   rendered                        98 x 50   ratio 1.960
 * So it is squeezed ~13% horizontally. Both width and height are being forced,
 * which is what distorts it — an image given two fixed dimensions cannot keep
 * its shape.
 *
 * The fix frees the width and keeps the height, so the header band is
 * unchanged and the logo simply becomes ~86px wide instead of 98px.
 *
 * `object-fit: contain` is added as a belt-and-braces: if any other rule ever
 * forces both dimensions again, the image will letterbox inside its box rather
 * than silently distort. Distortion is the failure mode that goes unnoticed
 * for seven months; empty space is the one somebody reports.
 *
 * Safe at the mu-plugins root: pure hook registration.
 */

defined('ABSPATH') || exit;

add_action('wp_head', static function (): void {
    ?>
<style id="pv-logo-aspect" data-noptimize="1">
img.custom-logo,
.custom-logo-link img,
header img.custom-logo {
    width: auto !important;
    height: 50px !important;
    max-width: 100% !important;
    object-fit: contain !important;
}

/* Smaller screens: same principle, proportionally smaller. */
@media (max-width: 1024px) {
    img.custom-logo,
    .custom-logo-link img,
    header img.custom-logo {
        height: 44px !important;
    }
}
@media (max-width: 600px) {
    img.custom-logo,
    .custom-logo-link img,
    header img.custom-logo {
        height: 38px !important;
    }
}
</style>
    <?php
}, 200);
