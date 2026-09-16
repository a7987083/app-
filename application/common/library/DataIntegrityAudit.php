<?php

namespace app\common\library;

use think\Db;

/**
 * Read-only production data-integrity audit.
 *
 * The audit reports suspicious data and schema state only. It never deletes,
 * rewrites or repairs production records automatically.
 */
class DataIntegrityAudit
{
    const RETENTION_DAYS = 180;

    public static function snapshot($now = null)
    {
        AuthorizationSchema::ensure();
        $now = $now === null ? time() : (int)$now;
        $retentionBefore = $now - self::RETENTION_DAYS * 86400;
        $errors = [];

        $duplicateSummary = self::safeRows(
            "SELECT COUNT(*) AS groups_count, COALESCE(SUM(t.c - 1),0) AS extra_rows " .
            "FROM (SELECT COUNT(*) AS c FROM `fa_kami` WHERE TRIM(COALESCE(`kami`,''))<>'' GROUP BY `kami` HAVING COUNT(*) > 1) t",
            [],
            $errors,
            'duplicate_summary'
        );
        $duplicateExamples = self::safeRows(
            "SELECT `kami`, COUNT(*) AS c, MIN(`id`) AS first_id, MAX(`id`) AS last_id " .
            "FROM `fa_kami` WHERE TRIM(COALESCE(`kami`,''))<>'' GROUP BY `kami` HAVING COUNT(*) > 1 " .
            "ORDER BY c DESC, first_id ASC LIMIT 20",
            [],
            $errors,
            'duplicate_examples'
        );
        $duplicates = isset($duplicateSummary[0]) ? $duplicateSummary[0] : [];
        $duplicateGroups = isset($duplicates['groups_count']) ? (int)$duplicates['groups_count'] : 0;
        $duplicateExtraRows = isset($duplicates['extra_rows']) ? (int)$duplicates['extra_rows'] : 0;

        $cards = [
            'total' => self::safeCount("SELECT COUNT(*) AS c FROM `fa_kami`", [], $errors, 'cards_total'),
            'unused' => self::safeCount("SELECT COUNT(*) AS c FROM `fa_kami` WHERE `jh`=0", [], $errors, 'cards_unused'),
            'activated' => self::safeCount("SELECT COUNT(*) AS c FROM `fa_kami` WHERE `jh`=1", [], $errors, 'cards_activated'),
            'active' => self::safeCount("SELECT COUNT(*) AS c FROM `fa_kami` WHERE `jh`=1 AND `endtime`>?", [$now], $errors, 'cards_active'),
            'expired' => self::safeCount("SELECT COUNT(*) AS c FROM `fa_kami` WHERE `jh`=1 AND `endtime`>0 AND `endtime`<=?", [$now], $errors, 'cards_expired'),
            'scope_source' => self::safeCount("SELECT COUNT(*) AS c FROM `fa_kami` WHERE `card_scope`=1", [], $errors, 'scope_source'),
            'scope_verify' => self::safeCount("SELECT COUNT(*) AS c FROM `fa_kami` WHERE `card_scope`=2", [], $errors, 'scope_verify'),
            'scope_apps' => self::safeCount("SELECT COUNT(*) AS c FROM `fa_kami` WHERE `card_scope`=3", [], $errors, 'scope_apps'),
            'duplicate_groups' => $duplicateGroups,
            'duplicate_extra_rows' => $duplicateExtraRows,
            'duplicate_examples' => $duplicateExamples,
            'blank_codes' => self::safeCount("SELECT COUNT(*) AS c FROM `fa_kami` WHERE TRIM(COALESCE(`kami`,''))=''", [], $errors, 'blank_codes'),
            'invalid_jh' => self::safeCount("SELECT COUNT(*) AS c FROM `fa_kami` WHERE `jh` IS NULL OR `jh` NOT IN (0,1)", [], $errors, 'invalid_jh'),
            'invalid_scope' => self::safeCount("SELECT COUNT(*) AS c FROM `fa_kami` WHERE `card_scope` IS NULL OR `card_scope` NOT IN (1,2,3)", [], $errors, 'invalid_scope'),
            'invalid_duration_type' => self::safeCount("SELECT COUNT(*) AS c FROM `fa_kami` WHERE `kmyp` IS NULL OR `kmyp` NOT IN (1,2,3,4,5)", [], $errors, 'invalid_duration_type'),
            'activated_missing_udid' => self::safeCount("SELECT COUNT(*) AS c FROM `fa_kami` WHERE `jh`=1 AND TRIM(COALESCE(`udid`,''))=''", [], $errors, 'activated_missing_udid'),
            'unused_bound_udid' => self::safeCount("SELECT COUNT(*) AS c FROM `fa_kami` WHERE `jh`=0 AND TRIM(COALESCE(`udid`,''))<>''", [], $errors, 'unused_bound_udid'),
            'activated_missing_usetime' => self::safeCount("SELECT COUNT(*) AS c FROM `fa_kami` WHERE `jh`=1 AND COALESCE(`usetime`,0)<=0", [], $errors, 'activated_missing_usetime'),
            'activated_missing_endtime' => self::safeCount("SELECT COUNT(*) AS c FROM `fa_kami` WHERE `jh`=1 AND COALESCE(`endtime`,0)<=0", [], $errors, 'activated_missing_endtime'),
            'end_before_use' => self::safeCount("SELECT COUNT(*) AS c FROM `fa_kami` WHERE `jh`=1 AND `usetime`>0 AND `endtime`>0 AND `endtime`<=`usetime`", [], $errors, 'end_before_use'),
            'active_invalid_udid' => self::safeCount(
                "SELECT COUNT(*) AS c FROM `fa_kami` WHERE `jh`=1 AND `endtime`>? AND TRIM(COALESCE(`udid`,''))<>'' AND CHAR_LENGTH(`udid`) NOT IN (25,40)",
                [$now],
                $errors,
                'active_invalid_udid'
            ),
            'quota_out_of_range' => self::safeCount("SELECT COUNT(*) AS c FROM `fa_kami` WHERE `transfer_count`>1000000", [], $errors, 'quota_out_of_range'),
        ];

        $uniqueIndex = self::kamiUniqueIndexState($errors);
        $cards['unique_index_present'] = $uniqueIndex['present'];
        $cards['unique_index_name'] = $uniqueIndex['name'];
        $cards['unique_index_ready'] = $duplicateGroups === 0 && $cards['blank_codes'] === 0;

        $mappings = [
            'total' => self::safeCount("SELECT COUNT(*) AS c FROM `fa_kami_app`", [], $errors, 'mapping_total'),
            'duplicate_pairs' => self::safeCount(
                "SELECT COUNT(*) AS c FROM (SELECT `kami_id`,`app_id` FROM `fa_kami_app` GROUP BY `kami_id`,`app_id` HAVING COUNT(*)>1) t",
                [],
                $errors,
                'mapping_duplicate_pairs'
            ),
            'orphan_kami' => self::safeCount(
                "SELECT COUNT(*) AS c FROM `fa_kami_app` m LEFT JOIN `fa_kami` k ON k.`id`=m.`kami_id` WHERE k.`id` IS NULL",
                [],
                $errors,
                'mapping_orphan_kami'
            ),
            'orphan_app' => self::safeCount(
                "SELECT COUNT(*) AS c FROM `fa_kami_app` m LEFT JOIN `fa_category` a ON a.`id`=m.`app_id` WHERE a.`id` IS NULL",
                [],
                $errors,
                'mapping_orphan_app'
            ),
            'wrong_scope' => self::safeCount(
                "SELECT COUNT(*) AS c FROM `fa_kami_app` m INNER JOIN `fa_kami` k ON k.`id`=m.`kami_id` WHERE k.`card_scope`<>3",
                [],
                $errors,
                'mapping_wrong_scope'
            ),
            'app_scope_without_mapping' => self::safeCount(
                "SELECT COUNT(*) AS c FROM `fa_kami` k LEFT JOIN `fa_kami_app` m ON m.`kami_id`=k.`id` WHERE k.`card_scope`=3 AND m.`id` IS NULL",
                [],
                $errors,
                'app_scope_without_mapping'
            ),
        ];

        $logs = [
            'authorization_event_orphans' => self::safeCount(
                "SELECT COUNT(*) AS c FROM `fa_authorization_event` e LEFT JOIN `fa_kami` k ON k.`id`=e.`kami_id` WHERE e.`kami_id`>0 AND k.`id` IS NULL",
                [],
                $errors,
                'authorization_event_orphans'
            ),
            'transfer_log_orphans' => self::safeCount(
                "SELECT COUNT(*) AS c FROM `fa_card_transfer_log` t LEFT JOIN `fa_kami` k ON k.`id`=t.`kami_id` WHERE t.`kami_id`>0 AND k.`id` IS NULL",
                [],
                $errors,
                'transfer_log_orphans'
            ),
            'successful_same_udid_transfer' => self::safeCount(
                "SELECT COUNT(*) AS c FROM `fa_card_transfer_log` WHERE `status`=1 AND TRIM(COALESCE(`old_udid`,''))<>'' AND `old_udid`=`new_udid`",
                [],
                $errors,
                'successful_same_udid_transfer'
            ),
        ];

        $blacklist = [
            'active_duplicate_udid_groups' => self::safeCount(
                "SELECT COUNT(*) AS c FROM (SELECT `udid` FROM `fa_black` WHERE TRIM(COALESCE(`udid`,''))<>'' AND (`endtime`=0 OR `endtime`>?) GROUP BY `udid` HAVING COUNT(*)>1) t",
                [$now],
                $errors,
                'blacklist_active_duplicate_udid_groups'
            ),
            'invalid_time_range' => self::safeCount(
                "SELECT COUNT(*) AS c FROM `fa_black` WHERE `endtime`>0 AND `addtime`>0 AND `endtime`<=`addtime`",
                [],
                $errors,
                'blacklist_invalid_time_range'
            ),
        ];

        $critical = $cards['duplicate_extra_rows']
            + $cards['blank_codes']
            + $cards['invalid_jh']
            + $cards['invalid_scope']
            + $cards['activated_missing_udid']
            + $mappings['orphan_kami']
            + $mappings['orphan_app']
            + $mappings['wrong_scope'];
        $warnings = $cards['invalid_duration_type']
            + $cards['unused_bound_udid']
            + $cards['activated_missing_usetime']
            + $cards['activated_missing_endtime']
            + $cards['end_before_use']
            + $cards['active_invalid_udid']
            + $cards['quota_out_of_range']
            + $mappings['duplicate_pairs']
            + $mappings['app_scope_without_mapping']
            + $logs['authorization_event_orphans']
            + $logs['transfer_log_orphans']
            + $logs['successful_same_udid_transfer']
            + $blacklist['active_duplicate_udid_groups']
            + $blacklist['invalid_time_range'];

        return [
            'generated_at' => $now,
            'health' => [
                'status' => $critical > 0 ? 'critical' : ($warnings > 0 || $errors ? 'warning' : 'ok'),
                'critical_count' => $critical,
                'warning_count' => $warnings,
                'query_errors' => count($errors),
            ],
            'cards' => $cards,
            'mappings' => $mappings,
            'logs' => $logs,
            'blacklist' => $blacklist,
            'unbind' => [
                'stable_semantics' => 'active_entitlements_only',
                'description' => '换绑仅迁移旧UDID当前有效（jh=1 且 endtime>当前时间）的授权行；过期历史卡保持原UDID不变。',
            ],
            'retention' => [
                'days' => self::RETENTION_DAYS,
                'before' => $retentionBefore,
                'expired_blacklist' => self::safeCount("SELECT COUNT(*) AS c FROM `fa_black` WHERE `endtime`>0 AND `endtime`<=?", [$now], $errors, 'retention_expired_blacklist'),
                'old_authorization_events' => self::safeCount("SELECT COUNT(*) AS c FROM `fa_authorization_event` WHERE `addtime`>0 AND `addtime`<?", [$retentionBefore], $errors, 'retention_authorization_events'),
                'old_transfer_logs' => self::safeCount("SELECT COUNT(*) AS c FROM `fa_card_transfer_log` WHERE `addtime`>0 AND `addtime`<?", [$retentionBefore], $errors, 'retention_transfer_logs'),
                'mode' => 'audit_only',
            ],
            'errors' => $errors,
        ];
    }

    protected static function safeCount($sql, array $bind, array &$errors, $label)
    {
        $rows = self::safeRows($sql, $bind, $errors, $label);
        return isset($rows[0]['c']) ? (int)$rows[0]['c'] : 0;
    }

    protected static function safeRows($sql, array $bind, array &$errors, $label)
    {
        try {
            $rows = Db::query($sql, $bind);
            return is_array($rows) ? $rows : [];
        } catch (\Exception $e) {
            $errors[] = [
                'check' => $label,
                'message' => $e->getMessage(),
            ];
            return [];
        }
    }

    protected static function kamiUniqueIndexState(array &$errors)
    {
        $rows = self::safeRows('SHOW INDEX FROM `fa_kami`', [], $errors, 'kami_unique_index');
        $indexes = [];
        foreach ($rows as $row) {
            $name = isset($row['Key_name']) ? (string)$row['Key_name'] : '';
            if ($name === '') {
                continue;
            }
            if (!isset($indexes[$name])) {
                $indexes[$name] = [
                    'unique' => isset($row['Non_unique']) && (int)$row['Non_unique'] === 0,
                    'columns' => [],
                ];
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
