<?php
/**
 * Plugin Name: Prestige Page Schema (Breadcrumbs + Service)
 * Description: Adds BreadcrumbList to inner pages and Service nodes to the service pages, tied to the existing Organization entity.
 * Version: 1.0.0
 *
 * WHY
 * Every inner page on this site emitted exactly one thing: WebPage. No
 * breadcrumbs, no Service nodes. For comparison, everythingit.ie emits
 * BreadcrumbList + ProfessionalService on every page and Service on its
 * location pages. That gap matters for answer engines twice over:
 *
 *   - BreadcrumbList tells an engine where a page sits in the site, which is
 *     what lets it describe the page in context rather than in isolation.
 *   - Service ties "what is offered" to the business entity and to a service
 *     area, which is precisely the question a local answer engine is asked.
 *
 * Descriptions are taken from each page's own Rank Math meta description
 * rather than written here, so the schema restates what the site already says
 * instead of introducing a second, drifting source of truth. Pages without one
 * get no description rather than an invented one.
 *
 * Everything attaches to the EXISTING entities by @id
 * (#organization, #webpage) rather than declaring new ones - a second
 * Organization node with a different @id is the collision that had to be
 * cleaned up on azwebcorp's own page schema.
 *
 * Safe at the mu-plugins root: pure hook registration.
 */

defined('ABSPATH') || exit;

/** slug => service name. Only pages that genuinely describe a service. */
const PRESTIGE_SERVICES = [
    'products'             => 'Window and Door Supply',
    'window-configurator'  => 'Custom Window Configuration',
    'custom-glass-options' => 'Energy-Efficient Glazing Options',
];

add_filter('rank_math/json_ld', function (array $data, $jsonld) {

    if (is_front_page() || !is_page()) {
        return $data;
    }

    $post = get_post();
    if (!$post) {
        return $data;
    }

    $home = home_url('/');
    $org  = $home . '#organization';

    // ---- BreadcrumbList ------------------------------------------------
    $crumbs = [[
        '@type'    => 'ListItem',
        'position' => 1,
        'name'     => 'Home',
        'item'     => $home,
    ]];

    // Walk real page ancestors so a nested page is described correctly.
    $ancestors = array_reverse(get_post_ancestors($post));
    $pos = 1;
    foreach ($ancestors as $aid) {
        $crumbs[] = [
            '@type'    => 'ListItem',
            'position' => ++$pos,
            'name'     => get_the_title($aid),
            'item'     => get_permalink($aid),
        ];
    }
    $crumbs[] = [
        '@type'    => 'ListItem',
        'position' => ++$pos,
        'name'     => get_the_title($post),
        'item'     => get_permalink($post),
    ];

    $data['prestige_breadcrumb'] = [
        '@type'           => 'BreadcrumbList',
        '@id'             => trailingslashit(get_permalink($post)) . '#breadcrumb',
        'itemListElement' => $crumbs,
    ];

    // ---- Service -------------------------------------------------------
    $slug = $post->post_name;
    if (isset(PRESTIGE_SERVICES[$slug])) {

        $state = ['@type' => 'State', 'name' => 'Arizona'];
        $areas = [$state];
        foreach (['Scottsdale', 'Phoenix', 'Gilbert', 'Chandler', 'Queen Creek'] as $c) {
            $areas[] = [
                '@type'            => 'City',
                'name'             => $c,
                'containedInPlace'  => $state,
            ];
        }

        $service = [
            '@type'       => 'Service',
            '@id'         => trailingslashit(get_permalink($post)) . '#service',
            'name'        => PRESTIGE_SERVICES[$slug],
            'serviceType' => PRESTIGE_SERVICES[$slug],
            'provider'    => ['@id' => $org],
            'areaServed'  => $areas,
            'url'         => get_permalink($post),
        ];

        // Reuse the page's own meta description. No fallback prose - an
        // invented description is worse than none.
        $desc = get_post_meta($post->ID, 'rank_math_description', true);
        if (is_string($desc) && trim($desc) !== '') {
            $service['description'] = wp_strip_all_tags($desc);
        }

        $data['prestige_service'] = $service;
    }

    return $data;
}, 1002, 2);
