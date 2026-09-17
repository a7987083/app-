<?php

namespace app\common\library;

use think\Cache;
use think\Db;

/**
 * Monotonic change log for public-source mutations.
 *
 * The revision is retained after the public V3 protocol is retired because it
 * is useful server-side for safe Legacy /appstore cache invalidation.
 * Logging is fail-open: a missing/temporarily unavailable change-log table
 * must never break legacy /appstore administration or delivery.
 */
class SourceChangeLog
{
    const TABLE = 'fa_source_change';
    const REVISION_CACHE_KEY = 'zonoe_source_revision_v1';
    const REVISION_CACHE_TTL = 3600;

    public static function available()
    {
        try {
            $rows = Db::query("SHOW TABLES LIKE 'fa_source_change'");
            return !empty($rows);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function record($appId, $action = 'update')
    {
        $appId = (int)$appId;
        if ($appId <= 0) {
            return 0;
        }
        $action = self::normalizeAction($action);

        try {
            $ok = Db::table(self::TABLE)->insert([
                'app_id' => $appId,
                'action' => $action,
                'changed_at' => time(),
            ]);
            if ((int)$ok !== 1) {
                return 0;
            }
            $row = Db::table(self::TABLE)
                ->where('app_id', $appId)
                ->order('revision desc')
                ->field('revision')
                ->find();
            $revision = $row && isset($row['revision']) ? (int)$row['revision'] : 0;
            self::rememberRevision($revision);
            return $revision;
        } catch (\Throwable $e) {
            self::forgetRevisionCache();
            self::logFailure('record', $e);
            return 0;
        }
    }

    public static function recordMany(array $appIds, $action = 'update')
    {
        $action = self::normalizeAction($action);
        $rows = [];
        $seen = [];
        $now = time();
        foreach ($appIds as $appId) {
            $appId = (int)$appId;
            if ($appId <= 0 || isset($seen[$appId])) {
                continue;
            }
            $seen[$appId] = true;
            $rows[] = [
                'app_id' => $appId,
                'action' => $action,
                'changed_at' => $now,
            ];
        }
        if (!$rows) {
            return 0;
        }

        try {
            $inserted = (int)Db::table(self::TABLE)->insertAll($rows);
            self::forgetRevisionCache();
            return $inserted;
        } catch (\Throwable $e) {
            self::forgetRevisionCache();
            self::logFailure('recordMany', $e);
            return 0;
        }
    }

    public static function currentRevision()
    {
        try {
            $cached = Cache::get(self::REVISION_CACHE_KEY);
            if (is_array($cached) && array_key_exists('revision', $cached)) {
                return max(0, (int)$cached['revision']);
            }
        } catch (\Throwable $e) {
            // Cache is an optimization only; continue to the database.
        }

        try {
            $row = Db::table(self::TABLE)
                ->order('revision desc')
                ->field('revision')
                ->find();
            $revision = $row && isset($row['revision']) ? (int)$row['revision'] : 0;
            self::rememberRevision($revision);
            return $revision;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Return the oldest client revision that can still be resumed with delta.
     * Retained for change-log maintenance/backward compatibility even though
     * the public V3 endpoints are no longer exposed.
     */
    public static function minDeltaSince()
    {
        try {
            $row = Db::table(self::TABLE)
                ->order('revision asc')
                ->field('revision')
                ->find();
            if (!$row || !isset($row['revision'])) {
                return self::currentRevision();
            }
            return max(0, (int)$row['revision'] - 1);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function revisionWindow()
    {
        return [
            'min_since' => self::minDeltaSince(),
            'current_revision' => self::currentRevision(),
        ];
    }

    public static function changesSince($since, $limit)
    {
        $since = max(0, (int)$since);
        $limit = max(1, (int)$limit);
        try {
            $rows = Db::table(self::TABLE)
                ->where('revision', '>', $since)
                ->field('revision,app_id,action,changed_at')
                ->order('revision asc')
                ->limit($limit)
                ->select();
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            self::logFailure('changesSince', $e);
            return [];
        }
    }

    protected static function rememberRevision($revision)
    {
        try {
            Cache::set(self::REVISION_CACHE_KEY, ['revision' => max(0, (int)$revision)], self::REVISION_CACHE_TTL);
        } catch (\Throwable $e) {
            // Ignore cache failures; currentRevision() remains DB-backed.
        }
    }

    protected static function forgetRevisionCache()
    {
        try {
            Cache::rm(self::REVISION_CACHE_KEY);
        } catch (\Throwable $e) {
            // Ignore cache failures; next generation token still prevents stale Legacy cache reuse.
        }
    }

    protected static function normalizeAction($action)
    {
        $action = strtolower(trim((string)$action));
        return in_array($action, ['add', 'update', 'delete'], true) ? $action : 'update';
    }

    protected static function logFailure($operation, \Throwable $e)
    {
        error_log('[SourceChangeLog] ' . $operation . ' failed; legacy source remains available: ' . $e->getMessage());
    }
}
