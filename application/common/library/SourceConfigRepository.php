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
     * Convert legacy config rows to name => runtime value.
     *
     * opencry historically behaved as a boolean. Phase 18.2 extends its stored
     * value to 0=off, 1=normal, 2=V2 while keeping existing callers compatible:
     * both encrypted modes are exposed as runtime value "1" here. Call
     * rawValue()/rawValueFromRows() when the exact selected mode is required.
     *
     * Keep this translation standalone: legacy unit tests load this class
     * without the framework autoloader.
     */
    public static function mapRows(array $rows)
    {
        $values = [];
        foreach ($rows as $row) {
            if (!isset($row['name'])) {
                continue;
            }
            $name = (string)$row['name'];
            $value = array_key_exists('value', $row) ? $row['value'] : null;
            if ($name === 'opencry' && (string)$value === '2') {
                $value = '1';
            }
            $values[$name] = $value;
        }
        return $values;
    }

    /**
     * Read one exact stored value from an already-fetched row set.
     */
    public static function rawValueFromRows(array $rows, $name, $default = null)
    {
        $name = (string)$name;
        foreach ($rows as $row) {
            if (isset($row['name']) && (string)$row['name'] === $name) {
                return array_key_exists('value', $row) ? $row['value'] : $default;
            }
        }
        return $default;
    }

    /**
     * Read one exact stored value without runtime compatibility normalization.
     */
    public static function rawValue($name, $default = null, $useCache = true)
    {
        return self::rawValueFromRows(self::rows($useCache), $name, $default);
    }

    /**
     * Return all current runtime-compatible config values keyed by name.
     */
    public static function values($useCache = true)
    {
        return self::mapRows(self::rows($useCache));
    }

    /**
     * Read one runtime-compatible value while preserving null-on-missing.
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
