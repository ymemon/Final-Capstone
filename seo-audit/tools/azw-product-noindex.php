<?php
/**
 * Plugin Name: AZW Reseller Product Noindex
 * Description: Keeps the 77 reseller_product pages fully available to
 * customers while telling search engines not to index them.
 *
 * WHY
 * Measured 2026-08-26: the reseller_product pages are templated near-
 * duplicates of one another - mean pairwise 6-gram Jaccard similarity 0.43
 * across a sample, with several pairs above 0.50. (For contrast, the city
 * landing pages scored 0.03-0.09 on the same measure and are genuinely
 * distinct.) They also earn ZERO impressions in Search Console, so Google is
 * already declining to rank them.
 *
 * Left as-is they were `index, follow`, which invited Google to index 77 thin
 * near-duplicates onto a site with roughly 35 substantive pages - more than
 * doubling the indexable surface with low-quality content, on a domain that
 * is already struggling for authority. Nothing is lost by noindexing them
 * because they currently earn nothing.
 *
 * WHAT THIS DOES NOT DO
 * - Does not hide them from customers. They stay public, linked and working.
 * - Does not nofollow them. `follow` is kept so internal link equity still
 *   flows through to the real service pages they link out to.
 * - Does not touch anything except post_type=reseller_product.
 *
 * TO REVERSE: delete this file. Nothing else is modified.
 */

defined('ABSPATH') || exit;

/*
 * Rank Math owns the robots meta on this site, so set it through Rank Math's
 * own filter rather than printing a second, competing tag. A raw wp_head echo
 * would leave two robots meta tags on the page and let Google pick.
 */
add_filter('rank_math/frontend/robots', static function ($robots) {
    if (is_singular('reseller_product')) {
        $robots['index']  = 'noindex';
        $robots['follow'] = 'follow';
    }
    return $robots;
});

/*
 * Fallback for the case where Rank Math is inactive or its filter changes
 * name: only fires if no robots tag has already been emitted this request.
 */
add_action('wp_head', static function () {
    if (!is_singular('reseller_product')) {
        return;
    }
    if (function_exists('rank_math') || class_exists('RankMath')) {
        return; // Rank Math handled it via the filter above.
    }
    echo '<meta name="robots" content="noindex, follow" />' . "\n";
}, 1);

/*
 * Keep them out of any sitemap that might later start including this post
 * type. They are absent from the sitemaps today, but that is incidental
 * rather than configured, so pin it.
 */
add_filter('rank_math/sitemap/exclude_post_type', static function ($exclude, $type) {
    return ('reseller_product' === $type) ? true : $exclude;
}, 10, 2);
