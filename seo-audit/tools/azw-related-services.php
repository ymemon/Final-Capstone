<?php
/**
 * Plugin Name: AZW Related Services Interlinks
 * Description: Adds a contextual related-services block to the hosting, domain and email pages so the cluster is properly interlinked.
 * Version: 1.0.0
 * Author: AZWebCorp
 *
 * ---------------------------------------------------------------------------
 * WHY A PLUGIN RATHER THAN EDITING THE PAGES
 *
 * A 2026-08-23 link audit of the cluster found the hosting pages
 * (hosting-domains, web-hosting, web-hosting-plus, wordpress-hosting,
 * vps-hosting) already well connected to each other, but the domain/email/SSL
 * side stranded: business-email and ssl had ZERO outbound links to siblings,
 * domain-transfer and website-builder linked only to hosting-domains, and
 * nothing linked to domain-registration at all.
 *
 * The pages are built inconsistently - some render from _elementor_data, some
 * from post_content, and ssl has almost no body content - so hand-editing prose
 * in each would mean five different edit strategies against live content, with
 * a real chance of corrupting an Elementor blob. Appending through the_content
 * is format-agnostic, centrally editable, and removable by deleting one file.
 *
 * Anchor text is descriptive rather than "click here" - it is the main on-page
 * signal about what the target page covers.
 * ---------------------------------------------------------------------------
 */

defined('ABSPATH') || exit;

/** slug => [label, short description] for every page in the cluster. */
function azw_rel_catalog() {
    return array(
        'hosting-domains'     => array('Hosting &amp; domains overview', 'Compare every hosting and domain option in one place.'),
        'web-hosting'         => array('cPanel web hosting',            'Standard shared hosting with cPanel access.'),
        'web-hosting-plus'    => array('Web Hosting Plus',              'More resources for busier sites.'),
        'wordpress-hosting'   => array('Managed WordPress hosting',     'WordPress-tuned hosting with updates handled.'),
        'vps-hosting'         => array('VPS hosting',                   'Dedicated resources when shared hosting is not enough.'),
        'domain-registration' => array('Domain registration',           'Search and register a new domain name.'),
        'domain-transfer'     => array('Domain transfer',               'Move an existing domain across without downtime.'),
        'business-email'      => array('Business email hosting',        'Professional mail on your own domain.'),
        'ssl'                 => array('SSL certificates',              'Encrypt traffic and clear browser warnings.'),
        'website-backup'      => array('Website backup',                'Automated backups and restore points.'),
        'website-builder'     => array('Website builder',               'Build it yourself with a drag-and-drop editor.'),
    );
}

/**
 * Which pages each page should point at.
 *
 * Chosen so every entry is a genuine next step for someone on that page, not a
 * reciprocal-link dump: a visitor registering a domain plausibly needs hosting
 * and email; someone on SSL is securing a site they are already hosting.
 */
function azw_rel_map() {
    return array(
        'business-email'      => array('domain-registration', 'wordpress-hosting', 'web-hosting', 'hosting-domains'),
        'ssl'                 => array('web-hosting', 'wordpress-hosting', 'website-backup', 'hosting-domains'),
        'domain-transfer'     => array('domain-registration', 'business-email', 'wordpress-hosting', 'hosting-domains'),
        'website-builder'     => array('web-hosting', 'domain-registration', 'business-email', 'hosting-domains'),
        'domain-registration' => array('business-email', 'domain-transfer', 'wordpress-hosting', 'ssl'),
        'website-backup'      => array('wordpress-hosting', 'web-hosting', 'ssl', 'hosting-domains'),
        'wordpress-hosting'   => array('domain-registration', 'business-email', 'ssl', 'website-backup'),
        'web-hosting'         => array('domain-registration', 'business-email', 'ssl', 'wordpress-hosting'),
        'web-hosting-plus'    => array('domain-registration', 'business-email', 'ssl', 'wordpress-hosting'),
        'vps-hosting'         => array('domain-registration', 'business-email', 'ssl', 'wordpress-hosting'),
        'hosting-domains'     => array('domain-registration', 'domain-transfer', 'business-email', 'ssl'),
    );
}

function azw_rel_render($slug) {
    $map = azw_rel_map();
    $cat = azw_rel_catalog();
    if (empty($map[$slug])) {
        return '';
    }

    $items = '';
    foreach ($map[$slug] as $target) {
        if ($target === $slug || empty($cat[$target])) {
            continue;
        }
        list($label, $desc) = $cat[$target];
        $items .= sprintf(
            '<li class="azw-rel-item"><a class="azw-rel-link" href="%s">%s</a><span class="azw-rel-desc">%s</span></li>',
            esc_url(home_url('/' . $target . '/')),
            $label,           // catalogue text is ours, already entity-safe
            esc_html($desc)
        );
    }
    if ($items === '') {
        return '';
    }

    return '<aside class="azw-related" aria-labelledby="azw-related-heading">'
        . '<h2 id="azw-related-heading" class="azw-rel-heading">Related hosting &amp; domain services</h2>'
        . '<ul class="azw-rel-grid">' . $items . '</ul>'
        . '</aside>'
        . azw_rel_styles();
}

function azw_rel_styles() {
    static $done = false;
    if ($done) {
        return '';
    }
    $done = true;
    // data-noptimize: Autoptimize otherwise folds inline CSS into a cached
    // aggregate, which delays every edit behind a bundle rebuild.
    return '<style id="azw-related-css" data-noptimize="1">'
        . '.azw-related{max-width:1080px;margin:44px auto;padding:28px;border:1px solid #e3e8ed;border-radius:14px;background:#fbfcfd}'
        . '.azw-related .azw-rel-heading{margin:0 0 18px;font-size:20px;line-height:1.25;color:#111823}'
        . '.azw-rel-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(228px,1fr));gap:14px;margin:0;padding:0;list-style:none}'
        . '.azw-rel-item{padding:16px 18px;background:#fff;border:1px solid #e8edf2;border-radius:11px}'
        . '.azw-rel-link{display:block;font-weight:700;font-size:15px;color:#9b711b;text-decoration:none}'
        . '.azw-rel-link:hover,.azw-rel-link:focus{color:#7d5a12;text-decoration:underline}'
        . '.azw-rel-desc{display:block;margin-top:5px;font-size:13px;line-height:1.5;color:#5d6875}'
        . '@media(prefers-color-scheme:dark){}'
        . '</style>';
}

/**
 * Append to the main content only.
 *
 * Priority 1000, not 20: azwc_ss_replace_content() (SSL page) and a closure in
 * azwebcorp-domain-search.php (domain-registration) both hook the_content at
 * 999 and REPLACE the content wholesale, which silently discarded an earlier
 * append on exactly those two pages. 1000 lands after both, and still before
 * Elementor Pro's theme-builder wrapper at 9999999.
 */
add_filter('the_content', static function ($content) {
    if (is_admin() || !is_singular('page') || !in_the_loop() || !is_main_query()) {
        return $content;
    }
    $post = get_post();
    if (!$post) {
        return $content;
    }
    $block = azw_rel_render($post->post_name);
    if ($block === '') {
        return $content;
    }
    // Never double-append if something else re-runs the filter.
    if (strpos($content, 'azw-related') !== false) {
        return $content;
    }
    return $content . $block;
}, 1000);
