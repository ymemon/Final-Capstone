<?php
/**
 * Plugin Name: Prestige FAQ Schema
 * Description: Emits FAQPage structured data on the FAQ page, built at runtime from the page's own accordion content so the markup can never drift from what visitors actually see.
 * Version: 1.0.0
 *
 * WHY
 * The FAQ page carries nine genuine questions and answers and had NO FAQPage
 * markup at all. Answer engines and AI assistants lean on FAQPage heavily -
 * it is one of the few schema types that maps directly onto "answer this
 * question" - so this is the highest-value AEO fix available on this site.
 *
 * WHY IT PARSES RATHER THAN HARDCODES
 * The obvious approach is to paste the nine Q&As into this file. That creates
 * a second source of truth which silently rots the first time anyone edits the
 * FAQ in Elementor, and stale FAQ markup is worse than none - Google treats a
 * mismatch between markup and visible content as a structured-data violation.
 * Reading the accordion items straight out of _elementor_data means the schema
 * always describes exactly what is on the page.
 *
 * Safe at the mu-plugins root: pure hook registration, no work at include time.
 *
 * Attaches to Rank Math's existing @graph rather than printing a second
 * <script> block, so the page keeps one coherent graph.
 */

defined('ABSPATH') || exit;

/**
 * Pull every {item_title, item_description} pair out of an Elementor blob.
 * Elementor nests widgets arbitrarily deep, so this walks the whole tree.
 */
function prestige_faq_collect(array $nodes, array &$out): void {
    foreach ($nodes as $node) {
        if (!is_array($node)) {
            continue;
        }
        if (isset($node['item_title'], $node['item_description'])) {
            $q = trim(wp_strip_all_tags((string) $node['item_title']));
            $a = trim(wp_strip_all_tags((string) $node['item_description']));
            // Skip anything still carrying a placeholder - never publish
            // "[CLIENT TO PROVIDE...]" into structured data.
            if ($q !== '' && $a !== '' && stripos($a, 'CLIENT TO PROVIDE') === false) {
                $out[] = [$q, $a];
            }
        }
        foreach ($node as $child) {
            if (is_array($child)) {
                prestige_faq_collect([$child], $out);
                // also descend into lists of children
                if (array_is_list($child)) {
                    prestige_faq_collect($child, $out);
                }
            }
        }
    }
}

add_filter('rank_math/json_ld', function (array $data, $jsonld) {

    if (!is_page('faq')) {
        return $data;
    }

    $raw = get_post_meta(get_the_ID(), '_elementor_data', true);
    if (!$raw) {
        return $data;
    }
    $tree = json_decode($raw, true);
    if (!is_array($tree)) {
        return $data;
    }

    $pairs = [];
    prestige_faq_collect($tree, $pairs);

    // De-duplicate: the walker can reach the same node by more than one path.
    $seen = [];
    $entities = [];
    foreach ($pairs as [$q, $a]) {
        $key = md5($q);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $entities[] = [
            '@type' => 'Question',
            'name'  => $q,
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => $a,
            ],
        ];
    }

    if (count($entities) < 2) {
        return $data;   // not a real FAQ - do not emit a hollow node
    }

    $data['faqpage'] = [
        '@type'      => 'FAQPage',
        '@id'        => trailingslashit(get_permalink()) . '#faq',
        'mainEntity' => $entities,
    ];

    return $data;
}, 1001, 2);   // after prestige-schema-fix (999) and prestige-area-served (1000)
