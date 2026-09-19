<?php

namespace app\common\library;

use think\Db;
use RuntimeException;

/**
 * Phase 20.7 production governance helpers.
 *
 * Batch operations intentionally reuse Phase 20.6 preview/apply so every item
 * keeps its own stale-plan check, idempotency key, verification and audit log.
 */
class IpaGovernanceBatchService
{
    const MAX_BATCH = 100;

    public static function normalizeIssueIds(array $issueIds)
    {
        $out = [];
        foreach ($issueIds as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $out[$id] = $id;
            }
        }
        $out = array_values($out);
        if (!$out) {
            throw new RuntimeException('请选择至少一条治理异常');
        }
        if (count($out) > self::MAX_BATCH) {
            throw new RuntimeException('单次批量治理最多处理 '.self::MAX_BATCH.' 条');
        }
        sort($out, SORT_NUMERIC);
        return $out;
    }

    public static function defaultModeForIssue(array $issue)
    {
        $type = isset($issue['issue_type']) ? (string)$issue['issue_type'] : '';
        if ($type === 'metadata_mismatch' || $type === 'version_mismatch') {
            return 'ipa_to_db';
        }
        if ($type === 'path_mismatch') {
            // Batch mode only takes the lower-risk direction: update DB from
            // the already observed OpenList public URL. Moving remote files
            // remains an explicit single-item operation.
            return 'openlist_to_db';
        }
        return '';
    }

    public static function preview(array $issueIds)
    {
        $ids = self::normalizeIssueIds($issueIds);
        $items = [];
        $skipped = [];

        foreach ($ids as $id) {
            $issue = Db::name('ipa_governance_issue')->where('id', $id)->find();
            if (!$issue || (string)$issue['state'] !== 'open') {
                $skipped[] = ['issue_id'=>$id, 'reason'=>'治理异常不存在或已不再处于 open 状态'];
                continue;
            }
            $mode = self::defaultModeForIssue($issue);
            if ($mode === '') {
                $skipped[] = ['issue_id'=>$id, 'reason'=>'该异常类型不允许批量自动修复'];
                continue;
            }
            try {
                $plan = IpaGovernanceService::preview($id, $mode);
                $items[] = [
                    'issue_id'=>$id,
                    'issue_type'=>(string)$issue['issue_type'],
                    'mode'=>$mode,
                    'plan_hash'=>(string)$plan['plan_hash'],
                    'action'=>(string)$plan['action'],
                    'payload'=>$plan['payload'],
                ];
            } catch (\Exception $e) {
                $skipped[] = ['issue_id'=>$id, 'reason'=>$e->getMessage()];
            }
        }

        if (!$items) {
            throw new RuntimeException('所选异常没有可安全批量执行的修复项');
        }

        $batch = ['issue_ids'=>$ids, 'items'=>$items];
        return [
            'batch_hash'=>IpaFoundation::planHash($batch),
            'items'=>$items,
            'skipped'=>$skipped,
            'selected_count'=>count($ids),
            'applicable_count'=>count($items),
            'skipped_count'=>count($skipped),
        ];
    }

    public static function apply(array $issueIds, $expectedBatchHash, $adminId=0)
    {
        $preview = self::preview($issueIds);
        if (!hash_equals((string)$preview['batch_hash'], (string)$expectedBatchHash)) {
            throw new RuntimeException('批量治理计划已变化，请重新预览');
        }

        $success = [];
        $failed = [];
        foreach ($preview['items'] as $item) {
            try {
                $result = IpaGovernanceService::apply(
                    (int)$item['issue_id'],
                    (string)$item['mode'],
                    (string)$item['plan_hash'],
                    (int)$adminId
                );
                $success[] = [
                    'issue_id'=>(int)$item['issue_id'],
                    'operation_id'=>isset($result['operation_id']) ? $result['operation_id'] : '',
                    'replayed'=>!empty($result['replayed']),
                    'verified'=>!empty($result['verified']),
                ];
            } catch (\Exception $e) {
                $failed[] = [
                    'issue_id'=>(int)$item['issue_id'],
                    'error'=>$e->getMessage(),
                ];
            }
        }

        return [
            'batch_hash'=>$preview['batch_hash'],
            'success'=>$success,
            'failed'=>$failed,
            'skipped'=>$preview['skipped'],
            'success_count'=>count($success),
            'failed_count'=>count($failed),
            'skipped_count'=>count($preview['skipped']),
        ];
    }

    public static function failedOperations($limit=100)
    {
        $limit = max(1, min(500, (int)$limit));
        $rows = Db::name('ipa_operation_log')
            ->where('state', 'failed')
            ->where('operation_type', 'like', 'governance_%')
            ->order('id', 'desc')
            ->limit($limit)
            ->select();

        foreach ((array)$rows as &$row) {
            $before = json_decode(isset($row['before_json']) ? $row['before_json'] : '', true);
            $before = is_array($before) ? $before : [];
            $row['issue_id'] = isset($before['issue_id']) ? (int)$before['issue_id'] : 0;
            $row['mode'] = isset($before['mode']) ? (string)$before['mode'] : '';
            $row['started_at_text'] = !empty($row['started_at']) ? date('Y-m-d H:i:s', (int)$row['started_at']) : '';
            $row['finished_at_text'] = !empty($row['finished_at']) ? date('Y-m-d H:i:s', (int)$row['finished_at']) : '';
            unset($row['before_json'], $row['after_json'], $row['result_json']);
        }
        unset($row);
        return $rows;
    }

    public static function failureStats()
    {
        return [
            'failed'=>(int)Db::name('ipa_operation_log')
                ->where('state', 'failed')
                ->where('operation_type', 'like', 'governance_%')
                ->count(),
        ];
    }
}
