<?php
/**
 * Plugin Name: AZW FAQ Schema
 * Description: Emits FAQPage structured data on pages that carry a genuine FAQ in question-headings but have no FAQPage markup. Built at runtime from the page's own content.
 * Version: 1.0.0
 *
 * WHY
 * /seo-company-phoenix-az/ carries six real, substantive FAQ answers and had
 * no FAQPage markup, while the sibling service pages already had it. Answer
 * engines lean on FAQPage directly, so this was free ground being given away
 * on one of the strongest commercial pages on the site.
 *
 * WHY IT PARSES RATHER THAN HARDCODES
 * Pasting the Q&As into this file creates a second source of truth that rots
 * the first time the copy is edited, and Google treats markup that disagrees
 * with visible content as a structured-data violation. Reading the headings
 * out of post_content means the schema always matches the page.
 *
 * THE CTA TRAP
 * A naive "heading ending in ?" rule also catches call-to-action headings -
 * on this page, "Ready to Build Something Better?" - and publishing that as a
 * Question with the CTA blurb as its Answer is exactly the kind of junk markup
 * that gets structured data ignored. CTA-shaped headings are filtered out.
 *
 * Safe at the mu-plugins root: pure hook registration.
 */

defined('ABSPATH') || exit;

// Pages to process. Deliberately an explicit list rather than sitewide: the
// other service pages already emit FAQPage via Rank Math and a second node
// would duplicate them.
const AZW_FAQ_SLUGS = ['seo-company-phoenix-az'];

/** Heading text that looks like a call to action rather than a question. */
function azw_faq_is_cta(string $q): bool {
    $q = strtolower(trim($q));
    foreach (['ready to', 'want to', 'need help', 'shall we', 'let us', "let's"] as $p) {
        if (str_starts_with($q, $p)) {
            return true;
        }
    }
    return false;
}

add_filter('rank_math/json_ld', function (array $data, $jsonld) {

    $post = get_post();
    if (!$post || !in_array($post->post_name, AZW_FAQ_SLUGS, true)) {
        return $data;
    }

    // Never add a second FAQPage if one is already in the graph.
    foreach ($data as $node) {
        if (isset($node['@type']) && in_array('FAQPage', (array) $node['@type'], true)) {
            return $data;
        }
    }

    $html = apply_filters('the_content', $post->post_content);
    if (!$html) {
        return $data;
    }

    // Split on question-shaped headings, keeping the delimiters.
    $parts = preg_split(
        '/(<h[2-4][^>]*>[^<]{12,160}\?\s*<\/h[2-4]>)/i',
        $html, -1, PREG_SPLIT_DELIM_CAPTURE
    );
    if (!is_array($parts) || count($parts) < 3) {
        return $data;
    }

    $entities = [];
    for ($i = 1; $i < count($parts); $i += 2) {
        $q = trim(wp_strip_all_tags($parts[$i]));
        $body = $parts[$i + 1] ?? '';

        if ($q === '' || azw_faq_is_cta($q)) {
            continue;
        }

        preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $body, $m);
        $paras = [];
        foreach ($m[1] ?? [] as $p) {
            $p = trim(wp_strip_all_tags($p));
            if (mb_strlen($p) > 40) {
                $paras[] = $p;
            }
        }
        if (!$paras) {
            continue;
        }

        $entities[] = [
            '@type' => 'Question',
            'name'  => $q,
            'acceptedAnswer' => [
                '@type' => 'Answer',
                // Two paragraphs is enough for an answer snippet; the whole
                // section would bloat the page source for no benefit.
                'text'  => implode(' ', array_slice($paras, 0, 2)),
            ],
        ];
    }

    if (count($entities) < 2) {
        return $data;
    }

    $data['faqpage'] = [
        '@type'      => 'FAQPage',
        '@id'        => trailingslashit(get_permalink($post)) . '#faq',
        'mainEntity' => $entities,
    ];

    return $data;
}, 1001, 2);
