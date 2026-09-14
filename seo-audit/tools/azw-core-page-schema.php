<?php
/**
 * Plugin Name: AZW Core Page Schema
 * Description: Evidence-based schema for core pages that otherwise emit no JSON-LD.
 */
add_action('wp_head', function () {
    if (!is_page()) return;
    $slug = get_post_field('post_name');
    $url  = get_permalink();
    $org  = home_url('/#organization');
    $pages = [
        'web-development' => ['Service','Arizona Web Development & Web Design','WordPress websites, eCommerce stores, custom web applications, and ongoing website support for Arizona businesses and nationwide clients.','Web development and web design'],
        'business-email' => ['Service','Business Email & Microsoft 365','Professional business email plans using a company domain, plus Microsoft 365 email and productivity options.','Business email services'],
        'domain-registration' => ['Service','Domain Registration','Domain search, registration, DNS, nameserver, hosting, SSL, and professional email configuration assistance.','Domain registration services'],
        'ssl-certificates' => ['Service','SSL Certificates','SSL certificate options and implementation support for Arizona business websites.','SSL certificate services'],
        'website-backup' => ['Service','Website Backup Services','Website backup and recovery planning for Arizona businesses.','Website backup services'],
        'website-security-firewall' => ['Service','Website Security & Firewall','Website security and web application firewall services for Arizona businesses.','Website security and firewall services'],
        'arizona-seo-services' => ['Service','Arizona SEO Services','Technical SEO, local SEO, on-page optimization, content planning, and implementation for Arizona businesses and nationwide clients.','Search engine optimization services'],
        'arizona-digital-marketing' => ['Service','Arizona Digital Marketing','Digital marketing strategy and implementation for Arizona businesses, including SEO, content, analytics, and conversion-focused website improvements.','Digital marketing services'],
        'hire-our-services' => ['CollectionPage','Hire AZWebCorp','AZWebCorp web development, SEO, digital marketing, hosting, website security, and ongoing support services.',''],
        'hosting-domains' => ['CollectionPage','Hosting, Domains & Email','AZWebCorp hosting, domain registration, business email, SSL, website security, and related online-business services.',''],
        'about-azwebcorp' => ['AboutPage','About AZWebCorp','Company information about AZWebCorp, an Arizona web development, SEO, hosting, and digital-services provider.',''],
        'contact-us' => ['ContactPage','Contact AZWebCorp','Contact AZWebCorp in Gilbert, Arizona about web development, SEO, hosting, domains, email, and website support.',''],
        'our-featured-projects' => ['CollectionPage','AZWebCorp Featured Projects','A collection of selected AZWebCorp web development and digital projects.',''],
    ];
    if (!isset($pages[$slug])) return;
    [$type,$name,$description,$service_type] = $pages[$slug];
    $graph = [];

    /*
     * A Service is NOT a web page - it is the thing the page is *about*.
     *
     * This previously emitted a single hybrid node typed `Service` while
     * claiming the `#webpage` @id. The consequence was that the service pages
     * - the most commercially important URLs on the site - ended up with no
     * WebPage entity at all: the BreadcrumbList had nothing to attach to, and
     * the WebPage -> isPartOf WebSite -> about Organization chain simply did
     * not exist there. CollectionPage/AboutPage/ContactPage are genuine
     * WebPage subtypes, so those keep using `#webpage` directly.
     */
    $is_service = ($type === 'Service');

    $page = [
        '@type' => $is_service ? 'WebPage' : $type,
        '@id' => $url . '#webpage',
        'url' => $url,
        'name' => $name,
        'description' => $description,
        'isPartOf' => ['@id' => home_url('/#website')],
        'about' => ['@id' => $org],
        'breadcrumb' => ['@id' => $url . '#breadcrumb'],
    ];
    if ($is_service) {
        $page['mainEntity'] = ['@id' => $url . '#service'];
    }
    $graph[] = $page;

    if ($is_service) {
        $graph[] = [
            '@type' => 'Service',
            '@id' => $url . '#service',
            'name' => $name,
            'description' => $description,
            'serviceType' => $service_type,
            'provider' => ['@id' => $org],
            'areaServed' => [['@type'=>'State','name'=>'Arizona'],['@type'=>'Country','name'=>'United States']],
            'mainEntityOfPage' => ['@id' => $url . '#webpage'],
        ];
    }
    $graph[] = [
        '@type'=>'BreadcrumbList','@id'=>$url.'#breadcrumb',
        'itemListElement'=>[
            ['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>home_url('/')],
            ['@type'=>'ListItem','position'=>2,'name'=>$name,'item'=>$url],
        ],
    ];
    $faqs = [
      'web-development' => [
        ['What kind of websites does AZWebCorp build?','WordPress websites, eCommerce stores, lead-generation sites, service-business sites, membership platforms, portals, custom applications, and related solutions.'],
        ['Is AZWebCorp a WordPress development company?','Yes. WordPress is the primary website-development platform, while custom development remains available when the project genuinely requires it.'],
        ['Do you only work with Arizona businesses?','No. AZWebCorp is based in Arizona and also works with businesses throughout the United States.'],
        ['Can you redesign an existing website?','Yes. We can redesign, rebuild, migrate, or improve an existing website while considering usability, technical performance, SEO, content structure, and redirects.'],
        ['Can you maintain a website you did not build?','Yes. We can assess an existing WordPress site or web application and determine whether we can take it over for ongoing support.'],
      ],
      'domain-registration' => [
        ['How much does a domain cost?','Eligible generic domains such as .com, .net, and .info are currently shown at $11.99 per year. Premium names, specialty extensions, registry pricing, selected terms, taxes, and fees may differ.'],
        ['Will my domain renew at the same price?','Eligible generic domains may renew at the advertised sale price, but checkout and product terms should always be reviewed because premium names, specialty extensions, registry pricing, taxes, and fees can differ.'],
        ['What is the difference between a domain and hosting?','A domain is your web address. Hosting stores the website files and makes the site available online.'],
        ['Can I transfer an existing domain?','In most cases, yes, subject to registrar requirements, account verification, lock status, authorization codes, and timing restrictions.'],
        ['Can AZWebCorp help connect my domain?','Yes. We can help with DNS, nameservers, hosting, WordPress, SSL, professional email, and related configuration.'],
      ],
      'business-email' => [
        ['Can I use my own domain for email?','Yes. These plans are designed for professional email addresses using your business domain.'],
        ['Which plan should I choose?','If you mainly need branded email, compare Titan Professional Email. If you also need Microsoft Office apps and Microsoft productivity tools, compare Microsoft 365.'],
        ['Can AZWebCorp help set up DNS and mail records?','Yes. We can help with DNS, MX records, nameservers, domain connections, WordPress, SSL, and related configuration.'],
        ['Are the displayed prices final?','These are the supplied current storefront prices. Product eligibility, taxes, fees, promotions, and final checkout amounts can vary, so checkout remains authoritative.'],
      ],
    ];
    if (isset($faqs[$slug])) {
        $entities=[]; foreach($faqs[$slug] as $qa){$entities[]=['@type'=>'Question','name'=>$qa[0],'acceptedAnswer'=>['@type'=>'Answer','text'=>$qa[1]]];}
        $graph[]=['@type'=>'FAQPage','@id'=>$url.'#faq','mainEntity'=>$entities];
    }
    echo '<script type="application/ld+json">'.wp_json_encode(['@context'=>'https://schema.org','@graph'=>$graph], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).'</script>';
}, 30);
