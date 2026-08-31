<?php
/**
 * Plugin Name: AZW Author Archive Noindex
 * Description: Prevents duplicate single-author archive pages from competing with original posts and service pages.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

add_filter('rank_math/frontend/robots', static function (array $robots): array {
    if (!is_author()) {
        return $robots;
    }
    unset($robots['index'], $robots['noindex'], $robots['nofollow'], $robots['follow']);
    $robots['noindex'] = 'noindex';
    $robots['follow']  = 'follow';
    return $robots;
});
