<?php

namespace app\common\library;

use think\Db;

/**
 * AppStore V3 server-side synchronization service.
 *
 * V3 is additive: legacy /appstore remains unchanged. Initial synchronization
 * is cursor-paged by id for stability; later synchronization consumes the
 * monotonic SourceChangeLog and emits upserts/deletions only.
 */
class SourceSyncV3
{
    const DEFAULT_LIMIT = 200;
    const MAX_LIMIT = 500;
    const DEFAULT_DELTA_LIMIT = 200;
    const MAX_DELTA_LIMIT = 1000;

    public static function meta()
    {
        $count = (int)Db::table('fa_category')->where('status', 'normal')->count();
        return [
            'revision' => SourceChangeLog::currentRevision(),
            'app_count' => $count,
            'delta_available' => SourceChangeLog::available() ? 1 : 0,
            'default_limit' => self::DEFAULT_LIMIT,
            'max_limit' => self::MAX_LIMIT,
            'default_delta_limit' => self::DEFAULT_DELTA_LIMIT,
            'max_delta_limit' => self::MAX_DELTA_LIMIT,
        ];
    }

    public static function page($afterId, $limit, $mode, array $sourceAccess)
    {
        $afterId = max(0, (int)$afterId);
        $limit = self::normalizeLimit($limit, self::DEFAULT_LIMIT, self::MAX_LIMIT);
        $fields = self::sourceFields();

        $rows = Db::table('fa_category')
            ->field(implode(',', $fields))
            ->where('status', 'normal')
            ->where('id', '>', $afterId)
            ->order('id asc')
            ->limit($limit + 1)
            ->select();
        $rows = is_array($rows) ? array_values($rows) : [];

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }
        $items = self::mapRows($rows, $mode, $sourceAccess);
        $nextAfterId = $afterId;
        if ($rows) {
            $last = $rows[count($rows) - 1];
            $nextAfterId = (int)SourceAppRecord::value($last, 'id', $afterId);
        }

        return [
            'revision' => SourceChangeLog::currentRevision(),
            'apps' => $items,
            'paging' => [
                'after_id' => $afterId,
                'next_after_id' => $nextAfterId,
                'limit' => $limit,
                'has_more' => $hasMore ? 1 : 0,
            ],
        ];
    }

    public static function delta($since, $limit, $mode, array $sourceAccess)
    {
        $since = max(0, (int)$since);
        $limit = self::normalizeLimit($limit, self::DEFAULT_DELTA_LIMIT, self::MAX_DELTA_LIMIT);
        $raw = SourceChangeLog::changesSince($since, $limit + 1);
        $hasMore = count($raw) > $limit;
        if ($hasMore) {
            array_pop($raw);
        }

        $nextSince = $since;
        $latest = [];
        foreach ($raw as $change) {
            $revision = isset($change['revision']) ? (int)$change['revision'] : 0;
            $appId = isset($change['app_id']) ? (int)$change['app_id'] : 0;
            if ($revision > $nextSince) {
                $nextSince = $revision;
            }
            if ($appId > 0) {
                $latest[$appId] = [
                    'revision' => $revision,
                    'app_id' => $appId,
                    'action' => isset($change['action']) ? (string)$change['action'] : 'update',
                ];
            }
        }

        if ($latest) {
            uasort($latest, function ($a, $b) {
                return $a['revision'] === $b['revision'] ? 0 : ($a['revision'] < $b['revision'] ? -1 : 1);
            });
        }

        $rowsById = [];
        if ($latest) {
            $ids = array_keys($latest);
            $rows = Db::table('fa_category')
                ->field(implode(',', self::sourceFields()))
                ->where('id', 'in', $ids)
                ->where('status', 'normal')
                ->select();
            foreach ((array)$rows as $row) {
                $id = (int)SourceAppRecord::value($row, 'id', 0);
                if ($id > 0) {
                    $rowsById[$id] = $row;
                }
            }
        }

        $upserts = [];
        $deleted = [];
        foreach ($latest as $appId => $change) {
            if (isset($rowsById[$appId])) {
                $mapped = self::mapRows([$rowsById[$appId]], $mode, $sourceAccess);
                if ($mapped) {
                    $item = $mapped[0];
                    $item['revision'] = (int)$change['revision'];
                    $upserts[] = $item;
                }
            } else {
                $deleted[] = [
                    'id' => (int)$appId,
                    'revision' => (int)$change['revision'],
                ];
            }
        }

        return [
            'since' => $since,
            'next_since' => $nextSince,
            'current_revision' => SourceChangeLog::currentRevision(),
            'has_more' => $hasMore ? 1 : 0,
            'upserts' => $upserts,
            'deleted' => $deleted,
        ];
    }

    protected static function sourceFields()
    {
        $fields = SourceAppRecord::publicSourceColumns();
        $fields[] = SourceAppRecord::column('weight');
        return array_values(array_unique($fields));
    }

    protected static function mapRows(array $rows, $mode, array $sourceAccess)
    {
        $rows = array_values($rows);
        $mapped = array_values(AppStorePayload::apps($rows, $mode, $sourceAccess));
        $result = [];
        foreach ($rows as $index => $row) {
            if (!isset($mapped[$index])) {
                continue;
            }
            $item = $mapped[$index];
            $item['id'] = (int)SourceAppRecord::value($row, 'id', 0);
            $item['weigh'] = (int)SourceAppRecord::value($row, 'weight', 0);
            $result[] = $item;
        }
        return $result;
    }

    protected static function normalizeLimit($value, $default, $max)
    {
        $value = (int)$value;
        if ($value <= 0) {
            $value = (int)$default;
        }
        return min((int)$max, max(1, $value));
    }
}
