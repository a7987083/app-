<?php

namespace app\common\library;

use think\Cache;
use think\Db;

/**
 * Read fa_config once per cache window and expose focused source/runtime views.
 *
 * Keep the raw row shape available because AppStorePayload::siteInfo() is part
 * of the compatibility contract and expects the legacy config rows.
 */
class SourceConfigRepository
{
    const CACHE_KEY = 'zonoe_source_config_rows_v1';
    const CACHE_TTL = 60;

    /**
     * Return all fa_config rows using a short shared cache.
     */
    public static function rows($useCache = true)
    {
        if ($useCache) {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $rows = Db::name('config')->select();
        $rows = is_array($rows) ? $rows : [];
        Cache::set(self::CACHE_KEY, $rows, self::CACHE_TTL);
        return $rows;
    }

    /**
     * Convert legacy config rows to name => value without changing values.
     */
    public static function mapRows(array $rows)
    {
        $values = [];
        foreach ($rows as $row) {
            if (isset($row['name'])) {
                $values[$row['name']] = isset($row['value']) ? $row['value'] : null;
            }
        }
        return $values;
    }

    /**
     * Return all current config values keyed by name.
     */
    public static function values($useCache = true)
    {
        return self::mapRows(self::rows($useCache));
    }

    /**
     * Read one value while preserving the old null-on-missing behavior.
     */
    public static function get($name, $default = null, $useCache = true)
    {
        $values = self::values($useCache);
        return array_key_exists($name, $values) ? $values[$name] : $default;
    }

    /**
     * Project selected config names into a new output-key map.
     * Example: ['payURL' => 'pay'] => ['pay' => '...'].
     */
    public static function project(array $rows, array $map)
    {
        $values = self::mapRows($rows);
        $result = [];
        foreach ($map as $sourceName => $targetName) {
            if (array_key_exists($sourceName, $values)) {
                $result[$targetName] = $values[$sourceName];
            }
        }
        return $result;
    }

    /**
     * Invalidate immediately after an admin config write.
     */
    public static function forget()
    {
        Cache::rm(self::CACHE_KEY);
    }
}
