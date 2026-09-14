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

        // Service and city pages. Added because every one of these had zero
        // inbound internal links, and the ones with real content on them were
        // earning zero impressions as a result.
        'arizona-web-design'      => array('Arizona web design',        'How we approach design across the state.'),
        'web-development'         => array('Arizona web development',   'The engineering side of larger builds.'),
        'arizona-seo-services'    => array('SEO across Arizona',        'Technical and local search, statewide.'),
        'web-design-phoenix-az'   => array('Web design in Phoenix',     'What changes when you are selling into Phoenix.'),
        'phoenix-web-development' => array('Phoenix web development',   'Custom builds, integrations and speed work.'),
        'seo-company-phoenix-az'  => array('Phoenix SEO',               'Local search in the Valley&#039;s hardest market.'),
        'web-design-gilbert-az'   => array('Web design in Gilbert',     'Design from a studio in the same town.'),
        'seo-services-gilbert-az' => array('SEO in Gilbert',            'Local search for East Valley businesses.'),

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

        // The service/city cluster. Links flow FROM the pages that already have
        // authority INTO the ones with none - /arizona-seo-services/ carries
        // 7,447 impressions and was linking to nothing local at all.
        'arizona-web-design'      => array('web-design-phoenix-az', 'web-design-gilbert-az', 'phoenix-web-development', 'arizona-seo-services'),
        'arizona-seo-services'    => array('seo-company-phoenix-az', 'seo-services-gilbert-az', 'arizona-web-design', 'web-development'),
        'web-development'         => array('phoenix-web-development', 'arizona-web-design', 'seo-company-phoenix-az', 'wordpress-hosting'),
        'web-design-phoenix-az'   => array('phoenix-web-development', 'seo-company-phoenix-az', 'arizona-web-design', 'web-design-gilbert-az'),
        'phoenix-web-development' => array('web-design-phoenix-az', 'seo-company-phoenix-az', 'web-development', 'wordpress-hosting'),
        'seo-company-phoenix-az'  => array('arizona-seo-services', 'web-design-phoenix-az', 'seo-services-gilbert-az', 'phoenix-web-development'),
        'web-design-gilbert-az'   => array('seo-services-gilbert-az', 'arizona-web-design', 'web-design-phoenix-az', 'web-development'),
        'seo-services-gilbert-az' => array('arizona-seo-services', 'web-design-gilbert-az', 'seo-company-phoenix-az', 'arizona-web-design'),

        /*
         * 2026-08-25: authority injection for the city/service cluster.
         *
         * Google's URL Inspection API reported these pages as "Discovered -
         * currently not indexed" and, for several, "URL is unknown to Google"
         * with the XML sitemap as their ONLY referring URL - despite carrying
         * 1,100-2,400 words each. A sitemap entry alone is a weak discovery
         * signal; without real internal links Google decides they are not
         * worth crawling, which is exactly what happened for eight months.
         *
         * These four sources are the highest-authority pages on the site (the
         * homepage above all - it previously linked to 21 internal pages and
         * not one of them was in this cluster). Linking from here is the
         * cheapest available way to pass crawl priority to pages that already
         * have the content to earn rankings.
         *
         * NOTE: the homepage's slug is 'online-presence-solutions', not
         * 'home' - post 117, set as the static front page.
         */
        'online-presence-solutions' => array('arizona-web-design', 'web-design-phoenix-az', 'seo-company-phoenix-az', 'web-design-gilbert-az'),
        'about-azwebcorp'           => array('arizona-web-design', 'web-development', 'arizona-seo-services', 'web-design-gilbert-az'),
        'contact-us'                => array('web-design-phoenix-az', 'web-design-gilbert-az', 'seo-company-phoenix-az', 'phoenix-web-development'),
        'our-featured-projects'     => array('arizona-web-design', 'phoenix-web-development', 'web-design-phoenix-az', 'web-development'),

    );
}

/**
 * Heading for the block.
 *
 * The original text was hardcoded to "Related hosting & domain services",
 * which was accurate when this only covered the hosting cluster but reads as a
 * non-sequitur under a city or SEO page. Anything outside the hosting set gets
 * a neutral heading instead.
 */
function azw_rel_heading($slug) {
    $hosting = array(
        'hosting-domains', 'web-hosting', 'web-hosting-plus', 'wordpress-hosting',
        'vps-hosting', 'domain-registration', 'domain-transfer', 'business-email',
        'ssl', 'website-backup', 'website-builder',
    );

    return in_array($slug, $hosting, true)
        ? 'Related hosting &amp; domain services'
        : 'Explore more of what we do';
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
        . '<h2 id="azw-related-heading" class="azw-rel-heading">' . azw_rel_heading($slug) . '</h2>'
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
    /*
     * Palette matches the sitewide dark theme (azw-dark-theme.php). The
     * original values here were light (#fbfcfd panel, #fff cards) from before
     * the site went dark, which left a white slab at the bottom of every page
     * carrying this block - the same class of theme mismatch reported on the
     * marketing pages. Gold accent is #e6b84d, the brand value.
     */
    return '<style id="azw-related-css" data-noptimize="1">'
        . '.azw-related{max-width:1080px;margin:44px auto;padding:28px;border:1px solid rgba(230,184,77,.24);border-radius:14px;background:rgba(255,255,255,.03)}'
        . '.azw-related .azw-rel-heading{margin:0 0 18px;font-size:20px;line-height:1.25;color:#fff}'
        . '.azw-rel-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(228px,1fr));gap:14px;margin:0;padding:0;list-style:none}'
        . '.azw-rel-item{padding:16px 18px;background:rgba(255,255,255,.045);border:1px solid rgba(255,255,255,.10);border-radius:11px}'
        . '.azw-rel-link{display:block;font-weight:700;font-size:15px;color:#f5d47d;text-decoration:none}'
        . '.azw-rel-link:hover,.azw-rel-link:focus{color:#ffe8a8;text-decoration:underline}'
        . '.azw-rel-desc{display:block;margin-top:5px;font-size:13px;line-height:1.5;color:#aab2bd}'
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
