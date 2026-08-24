<?php
/**
 * AZ Web Corp — sitewide dark brand theme.
 *
 * The hero on every page already used the black/gold gradient, but the body
 * underneath it stayed on the theme's white, so each page changed identity a
 * screen down. This carries the hero palette through the whole page.
 *
 * TWO RULES THAT MATTER IF YOU EDIT THIS
 *
 * 1. Neutralise `background-color`, never `background`. The shorthand also
 *    clears `background-image`, which is where every hero gradient and every
 *    photographic section background lives — using it turns the heroes off.
 *
 * 2. A background and its text have to move together. Most of the white
 *    blocks here hold near-black text; darkening the block alone does not
 *    make a page dark, it makes the text disappear. Every background rule
 *    below has a matching colour rule, and `survey.js` exists to prove it
 *    across all 34 pages rather than by spot-checking a few.
 *
 * Header and footer are deliberately untouched: they are already dark and are
 * owned by azw-global-header-contrast.php.
 *
 * @package AZWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_head', 'azw_dark_theme_css', 99 );

function azw_dark_theme_css() {
	if ( is_admin() ) {
		return;
	}
	?>
<style id="azw-dark-theme" data-noptimize="1">
	:root{
		--azw-ink:#ffffff;
		--azw-body:#d6dce4;
		--azw-dim:#aab2bd;
		--azw-gold:#e6b84d;
		--azw-gold-soft:#f5d47d;
		--azw-line:rgba(230,184,77,.24);
		--azw-card:rgba(255,255,255,.045);
		--azw-card-2:rgba(255,255,255,.07);
	}

	/* ---- the ground ---------------------------------------------------- */
	body{
		background:linear-gradient(135deg,#050608,#111823 60%,#30240a) fixed !important;
		color:var(--azw-body);
	}

	/* Theme chrome between <body> and the content, all of which paints white
	   on at least one template. */
	body #page,
	body #content,
	body .site-content,
	body .site-main,
	body main,
	body #primary,
	body .content-area,
	body .entry-content,
	body .wrap-detail-page,
	body article.page,
	body article.post,
	body .theiaStickySidebar{
		background-color:transparent !important;
	}

	/* ---- page builder sections ------------------------------------------
	   background-COLOR only. The shorthand would take background-image with
	   it and every hero gradient on the site is a background-image. */
	body .elementor-section,
	body .elementor-container,
	body .elementor-widget-wrap,
	body .elementor-column-wrap,
	body .elementor-element-populated{
		background-color:transparent !important;
	}

	/* Hand-built section families used across the older pages.
	   Matched on the class prefix rather than listed one by one: these were
	   written over several rounds (azwc-plans, azwc-confidence, azwc-explain,
	   azwc-faq, azwc-product-nav, azseo-section...) and enumerating them meant
	   finding a new white band on every page that had not been opened yet.
	   Restricted to <section> and <nav> so it cannot swallow the cards, which
	   are <article>/<div> and are given a surface just below. */
	body section[class*="azwc-"],
	body section[class*="azseo-"],
	body nav[class*="azwc-"],
	body .azwc,
	body .azwc-sec,
	body .azwc-soft,
	body .azseo-section,
	body .azseo-section--light,
	body .azseo-cta{
		background-color:transparent !important;
	}

	/* ---- cards ----------------------------------------------------------
	   These were white panels doing real work - they still need to read as
	   raised surfaces, so they get the translucent card treatment rather
	   than disappearing into the page. */
	body .azseo-compare-card,
	body .azseo-score-point,
	body .azseo-faq,
	body .azwc-card,
	body li.azw-rel-item,
	body details,
	body article.azwc-feature,
	body article.azwc-plan,
	body article.card,
	body article.value,
	body div.stat,
	body div.stat-row,
	body div.azwc-comparison,
	body div.azwc-contactbox,
	body article.azseo-check-card,
	body div.azseo-compare-label,
	body div.azwc-note{
		background-color:var(--azw-card) !important;
		border:1px solid var(--azw-line) !important;
		border-radius:12px;
	}

	/* Wrappers AROUND those cards. Carding both would stack two tints and two
	   borders, so the container simply steps out of the way. */
	body aside.azw-related,
	body section.azwc-section,
	body section.section{
		background-color:transparent !important;
		border:0 !important;
	}
</style>
	<?php
}

/**
 * Colours, emitted last.
 *
 * These live in wp_footer rather than wp_head for one concrete reason: the
 * hand-built product pages carry their own <style> block INSIDE the post
 * content, which the browser sees after anything printed in the head. Their
 * rules and ours kept landing on identical specificity - `article.azwc-plan h3`
 * against `body [class] h3`, both !important - and a tie is broken by source
 * order, so theirs won and plan headings stayed near-black on the gradient.
 *
 * Chasing that with ever-longer selectors is a race with no finish line.
 * Printing after the content settles it once, for every page, whatever
 * private class names it happens to use.
 *
 * The backgrounds stay in the head deliberately: those must land before first
 * paint or the page flashes white on the way in. Text colour arriving a beat
 * later is not visible in the same way.
 */
add_action( 'wp_footer', 'azw_dark_theme_type_css', 999 );

function azw_dark_theme_type_css() {
	if ( is_admin() ) {
		return;
	}
	?>
<style id="azw-dark-theme-type" data-noptimize="1">
	/* ---- type ------------------------------------------------------------
	   Broad on purpose. The dark values come from the theme, from Elementor
	   per-element styles and from three generations of hand-written page
	   markup; there is no single selector that catches them, and anything
	   left behind is invisible rather than merely off-brand. Brand-coloured
	   UI is exempted directly below. */
	body h1, body h2, body h3, body h4, body h5, body h6,
	body .elementor-heading-title,
	body .page-title, body .entry-title{
		color:var(--azw-ink) !important;
	}
	body p, body li, body dd, body dt, body td, body th,
	body blockquote, body figcaption, body label, body span, body summary,
	body strong, body b, body em, body i,
	body div.azwc-scope,
	body .elementor-text-editor, body .elementor-widget-text-editor,
	body .elementor-icon-list-text, body .elementor-image-box-description,
	body .elementor-testimonial-content{
		color:var(--azw-body) !important;
	}

	/* Specificity booster.
	   Several of the product pages carry their own `!important` colour rules
	   written with CLASS selectors - `.azwc-heading{color:#1b2940!important}`
	   and similar. A class (0,1,0) outranks the element selectors above
	   (0,0,2) no matter how many !importants are involved, so those headings
	   stayed near-black on the gradient. Matching through any classed
	   ancestor, or on the element's own class, lifts these to (0,1,2) and
	   settles it without naming each page's private class list. */
	body [class] h1, body [class] h2, body [class] h3,
	body [class] h4, body [class] h5, body [class] h6,
	body h1[class], body h2[class], body h3[class],
	body h4[class], body h5[class], body h6[class]{
		color:var(--azw-ink) !important;
	}
	body [class] p, body [class] li, body [class] span, body [class] strong,
	body [class] summary, body [class] dd, body [class] dt,
	body p[class], body li[class], body span[class], body summary[class]{
		color:var(--azw-body) !important;
	}
	body small, body .elementor-image-box-description small{
		color:var(--azw-dim) !important;
	}

	/* ---- things that paint their own background --------------------------
	   These need their own foreground stated, or the rules above write light
	   text onto a gold button.

	   Do NOT use `color:revert` here. revert goes back to the USER-AGENT
	   default, not to the theme's value - it put link purple (#9e9eff) on the
	   Elementor buttons, .btn-primary and every gold CTA on the site. State
	   the colour. */
	body .azwc-btn,
	body .btn-primary,
	body .elementor-button,
	body .wp-block-button__link,
	body button[type="submit"],
	body input[type="submit"]{
		color:#161208 !important;
	}
	/* Note the element names rather than `*`.
	   The universal selector contributes NOTHING to specificity, so
	   `body .elementor-button *` scores (0,1,1) and lost to the booster's
	   `body [class] span` at (0,1,2) - which is how light grey ended up on
	   the gold "Get a Quote" button. Naming the element makes it (0,1,2) and
	   adding the anchor makes it (0,1,3), which settles it outright. */
	body a.azwc-btn span, body a.azwc-btn strong, body a.azwc-btn b,
	body a.btn span, body a.btn strong,
	body a.elementor-button span, body a.elementor-button strong,
	body .elementor-button span.elementor-button-text,
	body a.wp-block-button__link span,
	body button.azwc-btn span, body button.azwc-tab span{
		color:inherit !important;
	}
	/* Ghost variants sit on the dark page rather than on gold. Note the two
	   naming schemes: .azwc-secondary is a gold button, plain .secondary is
	   the outlined one - they are not the same component. */
	body .azwc-btn.secondary,
	body a.btn-ghost,
	body a.btn-ghost span,
	body a.btn-ghost strong{
		color:#ffffff !important;
	}

	/* The audit tool and its modal carry a complete palette already; these
	   restate the few values the broad type rules above would flatten. */
	body #azwc-audit .azwc-sub,
	body #azwc-audit .azwc-check p,
	body #azwc-audit .azwc-action p,
	body #azwc-audit .azwc-plain,
	body #azwc-fu-modal .azwc-fu-lede,
	body #azwc-fu-modal .azwc-fu-note{
		color:var(--azw-body) !important;
	}
	body #azwc-audit .azwc-items a,
	body #azwc-fu-modal .azwc-fu-picked,
	body #azwc-fu-modal .azwc-fu-picked b,
	body #azwc-fu-modal .azwc-fu-back{
		color:var(--azw-gold-soft) !important;
	}
	body #azwc-audit .azwc-action-num{
		color:#161208 !important;
	}
	body .azwc-fu-card.is-primary b{ color:#161208 !important; }
	body .azwc-fu-card.is-primary span{ color:#4a3d18 !important; }

	/* ---- ID-scoped pages -------------------------------------------------
	   The security and SSL pages wrap their content in #azwc-ss and colour it
	   with 67 ID-scoped rules, most of them !important:

	       #azwc-ss .azwc-heading{color:#1b2940!important}
	       #azwc-ss .azwc-plan h3{color:#10131a!important}

	   An id outranks any number of classes, so no amount of
	   `body [class][class] h2` answers it - the only thing that beats an id
	   is a selector carrying the same id. Moving this stylesheet later in the
	   document does not help either, because this is a specificity loss and
	   not a tie. #primary is the theme's own content wrapper and does the
	   same thing on a smaller scale.

	   :is() takes the specificity of its most specific argument, so each of
	   these lands at (1,1,2) - one id, one class, two elements - which clears
	   the (1,1,1) and (1,1,0) rules above without naming any of them. */
	body #azwc-ss :is([class]) :is(h1,h2,h3,h4,h5,h6),
	body #azwc-ss :is(h1,h2,h3,h4,h5,h6)[class],
	body #azwc-ss .azwc-heading,
	body #primary :is([class]) :is(h1,h2,h3,h4,h5,h6),
	body #primary :is(h1,h2,h3,h4,h5,h6)[class]{
		color:var(--azw-ink) !important;
	}
	body #azwc-ss :is([class]) :is(p,li,span,strong,b,em,dd,dt,td,th,summary,div,figcaption),
	body #azwc-ss :is(p,li,span,strong,summary,div)[class],
	body #primary :is([class]) :is(p,li,span,strong,b,em,dd,dt,summary),
	body #primary :is(p,li,span,strong,summary)[class]{
		color:var(--azw-body) !important;
	}
	/* Buttons inside an id-scoped wrapper.
	   The #primary rule above is (1,1,2) and the plain button exemption is
	   (0,1,2), so the id rule was repainting button labels #d6dce4 - light
	   grey on gold. Restated here with the id present so the two are
	   comparable, plus an element in the selector to win the tie outright. */
	body #primary a.elementor-button,
	body #primary a.elementor-button span,
	body #primary a.elementor-button strong,
	body #primary a.azwc-btn,
	body #primary a.azwc-btn span,
	body #primary a.azwc-btn strong,
	body #primary a.btn-primary,
	body #primary a.btn-primary span,
	body #primary button[type="submit"],
	body #azwc-ss a.elementor-button span{
		color:#161208 !important;
	}
	body #primary a.btn-ghost,
	body #primary a.btn-ghost span,
	body #primary a.azwc-btn.secondary{
		color:#ffffff !important;
	}

	/* Restore the ones inside #azwc-ss that legitimately sit on gold. */
	body #azwc-ss .azwc-btn,
	body #azwc-ss a.azwc-btn span,
	body #azwc-ss a.azwc-btn strong,
	body #azwc-ss .azwc-popular,
	body #azwc-ss button.azwc-tab.is-active{
		color:#161208 !important;
	}
	body #azwc-ss .azwc-btn.secondary{
		color:var(--azw-gold-soft) !important;
	}
	/* Inside #azwc-ss the call-to-action links are styled as gold buttons
	   without carrying .azwc-btn - the product nav's current item, and the
	   anchors in the closing grid. Colouring every anchor here gold put gold
	   on gold at 1.14:1, so link colour is applied only to anchors that are
	   NOT one of those. */
	body #azwc-ss .azwc-shell > a,
	body #azwc-ss .azwc-final-grid a,
	body #azwc-ss a.active{
		color:#161208 !important;
	}
	/* Only the CURRENT product-nav item is filled gold. The rest sit on the
	   gradient, so darkening the whole nav swapped one contrast failure for
	   another - #161208 on the gradient at 1.05:1. */
	body #azwc-ss .azwc-product-nav a:not(.active){
		color:var(--azw-gold-soft) !important;
	}
	body #azwc-ss p a,
	body #azwc-ss li a{
		color:var(--azw-gold-soft) !important;
	}

	/* Badges that keep a gold fill of their own need the dark ink back - the
	   broad span rule above had been writing #d6dce4 onto gold. */
	body span.azwc-popular,
	body .azwc-badge,
	body .is-popular .azwc-popular{
		color:#161208 !important;
	}
	/* Tabs and pills that paint themselves. */
	body button.azwc-tab,
	body button.azwc-tld-slider__item,
	body a.azseo-button,
	body a.button-primary,
	body a.header-btn-1{
		color:#161208 !important;
	}

	/* Eyebrow text - the small caps line above a heading. The palette calls
	   for gold here, and these were #9b711b, a deep gold chosen for white
	   cards that reads as brown on the gradient. */
	body .azwc-kicker,
	body .azseo-kicker,
	body [class*="kicker"],
	body [class*="eyebrow"]{
		color:var(--azw-gold-soft) !important;
	}
	body .azwc-note,
	body div.azwc-note{
		color:var(--azw-dim) !important;
	}

	/* The comparison labels on the audit page ("AFFORDABLE SEO" against
	   "CHEAP SEO SHORTCUTS"). Both were picked to sit on white cards - one
	   near-black, one a dark rust - and neither survives the gradient. The
	   good/risk distinction is worth keeping, so they get the palette's own
	   green and red rather than being flattened to one colour. */
	body .azseo-compare-label,
	body .azseo-compare-label span,
	body .azseo-compare-label strong{
		color:var(--azw-body) !important;
	}
	body .azseo-compare-card--good .azseo-compare-label,
	body .azseo-compare-card--good .azseo-compare-label span{
		color:#4cc98a !important;
	}
	body .azseo-compare-card--risk .azseo-compare-label,
	body .azseo-compare-card--risk .azseo-compare-label span{
		color:#ef8f8f !important;
	}

	/* Validation tabs on the SSL page: unclassed <span> pills painted white.
	   Nothing selects them by name, so they are reached through the parent. */
	body .azwc-validation-tabs > span,
	body .azwc-validation-tabs > a,
	body .azwc-validation-tabs > button{
		background-color:var(--azw-card-2) !important;
		border:1px solid var(--azw-line) !important;
		color:var(--azw-body) !important;
	}
	body .azwc-validation-tabs > .active,
	body .azwc-validation-tabs > .is-active{
		background-color:var(--azw-gold) !important;
		color:#161208 !important;
	}
	body button.azwc-tab:not(.is-active):not(.active){
		color:var(--azw-body) !important;
	}

	/* ---- links ---------------------------------------------------------- */
	/* .azwc-btn has to be excluded explicitly: it is a gold CTA that carries
	   neither .btn nor .elementor-button, so it was picking up link gold and
	   rendering gold-on-gold at 1.29:1. */
	body .entry-content a:not(.elementor-button):not(.btn):not(.azwc-btn):not([class*="button"]),
	body .azwc a:not(.elementor-button):not(.btn):not(.azwc-btn),
	body .azseo-section a:not(.elementor-button):not(.btn):not(.azwc-btn),
	/* The interlinking block's links were #9b711b - a deep gold picked for
	   white cards, and far too dark once the card went translucent. */
	body a.azw-rel-link,
	body .azw-related a{
		color:var(--azw-gold-soft) !important;
	}

	/* ---- form fields ----------------------------------------------------- */
	/* Not scoped to .entry-content: several forms on this site sit outside it
	   (the domain search bar, the quick bar, Fluent Forms in a widget), and a
	   white field is the most obvious light patch left on a dark page. These
	   values match what the audit tool sets for its own inputs, so the two
	   rules agree wherever they overlap. */
	body input:not([type="submit"]):not([type="button"]):not([type="checkbox"]):not([type="radio"]):not([type="range"]),
	body textarea,
	body select,
	body .ff-el-form-control{
		background-color:rgba(255,255,255,.06) !important;
		border-color:rgba(255,255,255,.22) !important;
		color:#ffffff !important;
	}
	body input::placeholder,
	body textarea::placeholder,
	body .ff-el-form-control::placeholder{
		color:#98a1ad !important;
	}

	/* ---- separators ------------------------------------------------------ */
	body hr,
	body .elementor-divider-separator,
	body table, body th, body td{
		border-color:var(--azw-line) !important;
	}

	/* Native widgets (date pickers, scrollbars) should render dark too. */
	body{color-scheme:dark;}
</style>
	<?php
}
