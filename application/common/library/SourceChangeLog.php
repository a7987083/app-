<?php

namespace app\common\library;

use think\Db;

/**
 * Monotonic change log for AppStore V3 incremental synchronization.
 *
 * Each public-source mutation receives one unique AUTO_INCREMENT revision.
 * Logging is fail-open: a missing/temporarily unavailable change-log table
 * must never break legacy /appstore administration or delivery.
 */
class SourceChangeLog
{
    const TABLE = 'fa_source_change';

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
            return $row && isset($row['revision']) ? (int)$row['revision'] : 0;
        } catch (\Throwable $e) {
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
            return (int)Db::table(self::TABLE)->insertAll($rows);
        } catch (\Throwable $e) {
            self::logFailure('recordMany', $e);
            return 0;
        }
    }

    public static function currentRevision()
    {
        try {
            $row = Db::table(self::TABLE)
                ->order('revision desc')
                ->field('revision')
                ->find();
            return $row && isset($row['revision']) ? (int)$row['revision'] : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Return the oldest client revision that can still be resumed with delta.
     *
     * If the first retained change is revision N, a client at N-1 can consume
     * every retained change without a gap. This makes future change-log
     * retention safe: clients older than min_since must perform a full sync.
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
