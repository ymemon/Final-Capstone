<?php
/**
 * Plugin Name: AZW Organization Geo
 * Description: Restores verified GeoCoordinates to Rank Math's existing AZWebCorp Organization entity.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

add_filter('rank_math/json_ld', static function (array $data): array {
    $enrich = static function (&$value) use (&$enrich): void {
        if (!is_array($value)) {
            return;
        }

        $types = $value['@type'] ?? [];
        $types = is_array($types) ? $types : [$types];
        $name  = isset($value['name']) ? strtolower(trim((string) $value['name'])) : '';

        if (
            in_array('Organization', $types, true)
            && in_array($name, ['azwebcorp', 'az web corp'], true)
        ) {
            $value['geo'] = [
                '@type'     => 'GeoCoordinates',
                'latitude'  => 33.3717,
                'longitude' => -111.7076,
            ];
        }

        foreach ($value as &$child) {
            $enrich($child);
        }
        unset($child);
    };

    $enrich($data);
    return $data;
}, 99, 1);
