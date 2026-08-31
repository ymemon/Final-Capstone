<?php
/**
 * Plugin Name: AZW Audit Results Noindex
 * Description: Keeps dynamic SEO audit result URLs out of search while preserving followed links.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

add_filter('rank_math/frontend/robots', static function (array $robots): array {
    if (!is_page('seo-audit-results')) {
        return $robots;
    }

    unset($robots['index'], $robots['noindex'], $robots['nofollow'], $robots['follow']);
    $robots['noindex'] = 'noindex';
    $robots['follow']  = 'follow';
    return $robots;
});
