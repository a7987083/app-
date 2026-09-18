<?php

namespace app\common\library;

class IpaMetadataNormalizer
{
    public static function normalize(array $parsed)
    {
        $out = [
            'package_name' => self::scalar(isset($parsed['name']) ? $parsed['name'] : ''),
            'bundle_id' => self::scalar(isset($parsed['bundle_id']) ? $parsed['bundle_id'] : ''),
            'package_version' => self::scalar(isset($parsed['version']) ? $parsed['version'] : ''),
            'package_build' => self::scalar(isset($parsed['build']) ? $parsed['build'] : ''),
            'minimum_ios' => self::scalar(isset($parsed['minimum_ios']) ? $parsed['minimum_ios'] : ''),
            'executable' => self::scalar(isset($parsed['executable']) ? $parsed['executable'] : ''),
        ];

        $normalized = [
            'name' => $out['package_name'],
            'bundle_id' => $out['bundle_id'],
            'version' => $out['package_version'],
            'build' => $out['package_build'],
            'minimum_ios' => $out['minimum_ios'],
            'executable' => $out['executable'],
            'development_region' => self::scalar(isset($parsed['development_region']) ? $parsed['development_region'] : ''),
            'localizations' => self::stringList(isset($parsed['localizations']) ? $parsed['localizations'] : []),
            'device_family' => isset($parsed['device_family']) && is_array($parsed['device_family']) ? array_values($parsed['device_family']) : [],
            'required_capabilities' => isset($parsed['required_capabilities']) ? $parsed['required_capabilities'] : [],
            'url_schemes' => self::stringList(isset($parsed['url_schemes']) ? $parsed['url_schemes'] : []),
            'query_schemes' => self::stringList(isset($parsed['query_schemes']) ? $parsed['query_schemes'] : []),
            'ats' => isset($parsed['ats']) && is_array($parsed['ats']) ? $parsed['ats'] : [],
            'architectures' => self::stringList(isset($parsed['architectures']) ? $parsed['architectures'] : []),
            'icons' => isset($parsed['icons']) && is_array($parsed['icons']) ? $parsed['icons'] : [],
            'primary_icon' => isset($parsed['primary_icon']) && is_array($parsed['primary_icon']) ? $parsed['primary_icon'] : null,
            'extensions' => self::stringList(isset($parsed['extensions']) ? $parsed['extensions'] : []),
            'frameworks' => self::stringList(isset($parsed['frameworks']) ? $parsed['frameworks'] : []),
            'dylibs' => self::stringList(isset($parsed['dylibs']) ? $parsed['dylibs'] : []),
            'swift' => !empty($parsed['swift']),
            'provisioning' => isset($parsed['provisioning']) && is_array($parsed['provisioning']) ? $parsed['provisioning'] : [],
            'payload_app_path' => self::scalar(isset($parsed['payload_app_path']) ? $parsed['payload_app_path'] : ''),
            'info_plist_path' => self::scalar(isset($parsed['info_plist_path']) ? $parsed['info_plist_path'] : ''),
            '_parser' => [
                'version' => IpaFoundation::PARSER_VERSION,
                'range_bytes' => isset($parsed['range_bytes']) ? (int)$parsed['range_bytes'] : 0,
                'range_requests' => isset($parsed['range_requests']) ? (int)$parsed['range_requests'] : 0,
                'range_limit' => isset($parsed['range_limit']) ? (int)$parsed['range_limit'] : 0,
            ],
        ];

        $confidence = [
            'bundle_id' => $out['bundle_id'] !== '' ? 'exact' : 'fallback',
            'version' => $out['package_version'] !== '' ? 'exact' : 'fallback',
            'build' => $out['package_build'] !== '' ? 'exact' : 'fallback',
            'minimum_ios' => $out['minimum_ios'] !== '' ? 'exact' : 'fallback',
            'executable' => $out['executable'] !== '' ? 'exact' : 'fallback',
            'name' => $out['package_name'] !== '' ? 'derived' : 'fallback',
            'primary_icon' => !empty($normalized['primary_icon']) ? 'derived' : 'fallback',
            'architectures' => !empty($normalized['architectures']) ? 'derived' : 'fallback',
        ];

        return ['columns' => $out, 'normalized' => $normalized, 'confidence' => $confidence];
    }

    protected static function scalar($value)
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    protected static function stringList($value)
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $item = trim((string)$item);
            if ($item !== '' && !in_array($item, $out, true)) {
                $out[] = $item;
            }
        }
        return $out;
    }
}
