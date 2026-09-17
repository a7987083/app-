<?php

namespace app\common\library;

use think\Cache;
use think\Db;

/**
 * Shared read cache for the public software-source App rows.
 *
 * The public protocol still returns the full App set. This cache only removes
 * repeated MySQL work while keeping the client wire format unchanged.
 *
 * Cache is strictly an optimization: cache read/write/remove failures must
 * never make the public software-source endpoint unavailable.
 */
class SourceAppRepository
{
    const CACHE_KEY = 'zonoe_source_app_rows_v1';
    const GENERATION_KEY = 'zonoe_source_app_generation_v1';
    const CACHE_TTL = 120;

    protected static $lastSource = 'none';

    public static function rows($useCache = true)
    {
        $cacheReadFailed = false;
        if ($useCache) {
            try {
                $cached = Cache::get(self::CACHE_KEY);
                if (is_array($cached)) {
                    self::$lastSource = 'cache';
                    return $cached;
                }
            } catch (\Throwable $e) {
                $cacheReadFailed = true;
                self::logCacheFailure('read', $e);
            }
        }

        // Database remains the source of truth. Cache failure must never stop
        // this query or alter the public protocol response.
        $rows = Db::table('fa_category')
            ->field(implode(',', SourceAppRecord::publicSourceColumns()))
            ->where('status', 'normal')
            ->order('weigh desc')
            ->select();
        $rows = is_array($rows) ? $rows : [];

        $cacheWriteFailed = false;
        try {
            $stored = Cache::set(self::CACHE_KEY, $rows, self::CACHE_TTL);
            if ($stored === false) {
                $cacheWriteFailed = true;
                error_log('[SourceAppRepository] cache write returned false; continuing with database rows');
            }
        } catch (\Throwable $e) {
            $cacheWriteFailed = true;
            self::logCacheFailure('write', $e);
        }

        if ($cacheWriteFailed) {
            self::$lastSource = 'db-cache-write-failed';
        } elseif ($cacheReadFailed) {
            self::$lastSource = 'db-cache-read-failed';
        } else {
            self::$lastSource = 'db';
        }
        return $rows;
    }

    public static function lastSource()
    {
        return self::$lastSource;
    }

    /**
     * A cheap cross-worker generation token. It complements SourceChangeLog's
     * revision and guarantees mapped/body caches are invalidated even if the
     * revision table is temporarily unavailable.
     */
    public static function generation()
    {
        try {
            $cached = Cache::get(self::GENERATION_KEY);
            if (is_array($cached) && isset($cached['value']) && $cached['value'] !== '') {
                return (string)$cached['value'];
            }
            $value = 'g' . str_replace('.', '', uniqid('', true));
            Cache::set(self::GENERATION_KEY, ['value' => $value], 0);
            return $value;
        } catch (\Throwable $e) {
            self::logCacheFailure('generation-read', $e);
            return '0';
        }
    }

    public static function forget()
    {
        try {
            Cache::rm(self::CACHE_KEY);
            $value = 'g' . str_replace('.', '', uniqid('', true));
            Cache::set(self::GENERATION_KEY, ['value' => $value], 0);
        } catch (\Throwable $e) {
            self::logCacheFailure('remove', $e);
        }
        self::$lastSource = 'none';
    }

    protected static function logCacheFailure($operation, \Throwable $e)
    {
        error_log('[SourceAppRepository] cache ' . $operation . ' failed; fail-open to database: ' . $e->getMessage());
    }
}
