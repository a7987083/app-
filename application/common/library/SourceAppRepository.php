<?php

namespace app\common\library;

use think\Cache;
use think\Db;

/**
 * Shared read cache for the public software-source App rows.
 *
 * The public protocol still returns the full App set. This cache only removes
 * repeated MySQL work while keeping the client wire format unchanged.
 */
class SourceAppRepository
{
    const CACHE_KEY = 'zonoe_source_app_rows_v1';
    const CACHE_TTL = 15;

    protected static $lastSource = 'none';

    public static function rows($useCache = true)
    {
        if ($useCache) {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_array($cached)) {
                self::$lastSource = 'cache';
                return $cached;
            }
        }

        $rows = Db::table('fa_category')
            ->field(implode(',', SourceAppRecord::publicSourceColumns()))
            ->where('status', 'normal')
            ->order('weigh desc')
            ->select();
        $rows = is_array($rows) ? $rows : [];
        Cache::set(self::CACHE_KEY, $rows, self::CACHE_TTL);
        self::$lastSource = 'db';
        return $rows;
    }

    public static function lastSource()
    {
        return self::$lastSource;
    }

    public static function forget()
    {
        Cache::rm(self::CACHE_KEY);
        self::$lastSource = 'none';
    }
}
