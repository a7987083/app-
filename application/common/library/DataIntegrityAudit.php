<?php

namespace app\common\library;

use think\Db;

/**
 * Phase 15 production data-integrity audit.
 *
 * This class is intentionally read-only. It reports duplicate/invalid card
 * records and long-term retention candidates, but does not mutate production
 * data or create indexes. A UNIQUE index may only be considered after the
 * production audit is reviewed.
 */
class DataIntegrityAudit
{
    const RETENTION_DAYS = 180;

    public static function snapshot($now = null)
    {
        AuthorizationSchema::ensure();
        $now = $now === null ? time() : (int)$now;
        $retentionBefore = $now - self::RETENTION_DAYS * 86400;

        $duplicateSummary = Db::query(
            "SELECT COUNT(*) AS groups_count, COALESCE(SUM(t.c - 1),0) AS extra_rows " .
            "FROM (SELECT COUNT(*) AS c FROM `fa_kami` WHERE TRIM(`kami`)<>'' GROUP BY `kami` HAVING COUNT(*) > 1) t"
        );
        $duplicateExamples = Db::query(
            "SELECT `kami`, COUNT(*) AS c, MIN(`id`) AS first_id, MAX(`id`) AS last_id " .
            "FROM `fa_kami` WHERE TRIM(`kami`)<>'' GROUP BY `kami` HAVING COUNT(*) > 1 " .
            "ORDER BY c DESC, first_id ASC LIMIT 20"
        );

        $blankCodes = (int)Db::table('fa_kami')->whereRaw("TRIM(`kami`)='' OR `kami` IS NULL")->count();
        $activatedMissingUdid = (int)Db::table('fa_kami')->where('jh', 1)->where('udid', '')->count();
        $activeInvalidUdid = (int)Db::table('fa_kami')
            ->where('jh', 1)
            ->where('endtime', '>', $now)
            ->where('udid', '<>', '')
            ->whereRaw('CHAR_LENGTH(`udid`) NOT IN (25,40)')
            ->count();
        $quotaOutOfRange = (int)Db::table('fa_kami')->where('transfer_count', '>', 1000000)->count();

        $duplicates = isset($duplicateSummary[0]) ? $duplicateSummary[0] : [];
        $duplicateGroups = isset($duplicates['groups_count']) ? (int)$duplicates['groups_count'] : 0;
        $duplicateExtraRows = isset($duplicates['extra_rows']) ? (int)$duplicates['extra_rows'] : 0;

        $uniqueIndex = self::kamiUniqueIndexState();

        return [
            'generated_at' => $now,
            'cards' => [
                'duplicate_groups' => $duplicateGroups,
                'duplicate_extra_rows' => $duplicateExtraRows,
                'duplicate_examples' => $duplicateExamples,
                'blank_codes' => $blankCodes,
                'activated_missing_udid' => $activatedMissingUdid,
                'active_invalid_udid' => $activeInvalidUdid,
                'quota_out_of_range' => $quotaOutOfRange,
                'unique_index_present' => $uniqueIndex['present'],
                'unique_index_name' => $uniqueIndex['name'],
                'unique_index_ready' => $duplicateGroups === 0 && $blankCodes === 0,
            ],
            'unbind' => [
                'stable_semantics' => 'active_entitlements_only',
                'description' => '换绑仅迁移旧UDID当前有效（jh=1 且 endtime>当前时间）的授权行；过期历史卡保持原UDID不变。',
            ],
            'retention' => [
                'days' => self::RETENTION_DAYS,
                'before' => $retentionBefore,
                'expired_blacklist' => (int)Db::table('fa_black')->where('endtime', '>', 0)->where('endtime', '<=', $now)->count(),
                'old_authorization_events' => (int)Db::table('fa_authorization_event')->where('addtime', '>', 0)->where('addtime', '<', $retentionBefore)->count(),
                'old_transfer_logs' => (int)Db::table('fa_card_transfer_log')->where('addtime', '>', 0)->where('addtime', '<', $retentionBefore)->count(),
                'mode' => 'audit_only',
            ],
        ];
    }

    protected static function kamiUniqueIndexState()
    {
        $rows = Db::query('SHOW INDEX FROM `fa_kami`');
        $indexes = [];
        foreach ($rows as $row) {
            $name = isset($row['Key_name']) ? (string)$row['Key_name'] : '';
            if ($name === '') {
                continue;
            }
            if (!isset($indexes[$name])) {
                $indexes[$name] = ['unique' => isset($row['Non_unique']) && (int)$row['Non_unique'] === 0, 'columns' => []];
            }
            $indexes[$name]['columns'][] = isset($row['Column_name']) ? (string)$row['Column_name'] : '';
        }
        foreach ($indexes as $name => $index) {
            if ($index['unique'] && count($index['columns']) === 1 && $index['columns'][0] === 'kami') {
                return ['present' => true, 'name' => $name];
            }
        }
        return ['present' => false, 'name' => ''];
    }
}
