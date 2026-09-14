<?php
/**
 * Plugin Name: Prestige Area Served
 * Description: Adds areaServed to the GeneralContractor/Organization node in Rank Math's JSON-LD graph. The site previously published no service-area signal at all, which is the single biggest local-SEO gap: search engines had no machine-readable statement of where this business works. Coverage is statewide Arizona, with the named cities as the markets actively targeted.
 * Version: 1.0.0
 *
 * Safe at the mu-plugins ROOT: this file is pure hook registration. It calls
 * no template function and does nothing at include time, so it cannot fatal
 * during WP-CLI bootstrap the way a stray template file would.
 *
 * Confirmed with the client 2026-08-26: statewide AZ, concentrated in the
 * Scottsdale / East Valley corridor. "Biltmore" is a district of Phoenix, not
 * a municipality, so it is represented by the Phoenix City node rather than
 * invented as a city of its own.
 */

defined('ABSPATH') || exit;

add_filter('rank_math/json_ld', function (array $data, $jsonld) {

    $state = [
        '@type' => 'State',
        'name'  => 'Arizona',
    ];

    // Statewide first - it is the actual coverage. The cities are additive
    // emphasis for the markets being targeted, not a narrowing of it.
    $areas = [$state];

    foreach (['Scottsdale', 'Phoenix', 'Gilbert', 'Chandler', 'Queen Creek'] as $city) {
        $areas[] = [
            '@type'            => 'City',
            'name'             => $city,
            'containedInPlace' => $state,
        ];
    }

    foreach ($data as $key => $node) {
        $type  = $node['@type'] ?? null;
        $types = is_array($type) ? $type : [$type];

        // Attach to the business entity only - not to WebPage/WebSite/Place.
        if (array_intersect($types, ['GeneralContractor', 'LocalBusiness', 'Organization'])) {
            // Never clobber an areaServed that Rank Math or a future Local SEO
            // config starts emitting on its own; this filter is a fallback for
            // the field being absent, not an override of a real setting.
            if (empty($data[$key]['areaServed'])) {
                $data[$key]['areaServed'] = $areas;
            }
        }
    }

    return $data;
}, 1000, 2);
