<?php

namespace app\common\library;

use think\Db;
use RuntimeException;

/**
 * Phase 20.7.2 recovery layer.
 * Keeps the original failed/interrupted audit record immutable until a retry
 * succeeds, then marks it superseded and links it to the recovery operation.
 */
class IpaGovernanceRecoveryService extends IpaGovernanceService
{
    const DEFAULT_STALE_SECONDS = 300;

    public static function markInterrupted($staleSeconds=self::DEFAULT_STALE_SECONDS)
    {
        $staleSeconds = max(60, min(86400, (int)$staleSeconds));
        $cutoff = time() - $staleSeconds;
        $rows = Db::name('ipa_operation_log')
            ->where('state', 'running')
            ->where('operation_type', 'like', 'governance_%')
            ->where('updatetime', '<', $cutoff)
            ->field('id,operation_id,error_message')
            ->select();

        $count = 0;
        foreach ((array)$rows as $row) {
            $message = trim((string)$row['error_message']);
            if ($message === '') {
                $message = 'operation heartbeat expired; marked interrupted by recovery scan';
            }
            $now = time();
            $updated = Db::name('ipa_operation_log')
                ->where('id', (int)$row['id'])
                ->where('state', 'running')
                ->update([
                    'state'=>'interrupted',
                    'error_message'=>$message,
                    'finished_at'=>$now,
                    'updatetime'=>$now,
                ]);
            if ($updated) $count++;
        }
        return ['interrupted'=>$count, 'cutoff'=>$cutoff, 'stale_seconds'=>$staleSeconds];
    }

    public static function retry($operationId, $adminId=0)
    {
        $operationId = trim((string)$operationId);
        if ($operationId === '') throw new RuntimeException('operation_id 不能为空');

        $source = Db::name('ipa_operation_log')->where('operation_id', $operationId)->find();
        if (!$source) throw new RuntimeException('原治理操作不存在');
        if (!in_array((string)$source['state'], ['failed','interrupted'], true)) {
            throw new RuntimeException('只有 failed / interrupted 操作允许重试');
        }
        if (strpos((string)$source['operation_type'], 'governance_') !== 0) {
            throw new RuntimeException('该操作不是治理修复操作');
        }

        $before = json_decode(isset($source['before_json']) ? $source['before_json'] : '', true);
        if (!is_array($before)) throw new RuntimeException('原操作缺少可恢复计划');
        $issueId = isset($before['issue_id']) ? (int)$before['issue_id'] : 0;
        $mode = isset($before['mode']) ? trim((string)$before['mode']) : '';
        if ($issueId <= 0 || $mode === '') throw new RuntimeException('原操作缺少 issue_id / mode，无法安全重试');

        // Never replay the stale plan. Re-preview against current DB/OpenList metadata.
        $plan = IpaGovernanceService::preview($issueId, $mode);
        $idem = IpaFoundation::idempotencyKey('governance_retry', [
            'retry_of'=>$operationId,
            'plan_hash'=>$plan['plan_hash'],
        ]);
        $existing = Db::name('ipa_operation_log')->where('idempotency_key', $idem)->find();
        if ($existing && (string)$existing['state'] === 'success') {
            return ['replayed'=>true, 'operation_id'=>$existing['operation_id'], 'verified'=>true];
        }
        if ($existing && (string)$existing['state'] === 'running') {
            throw new RuntimeException('该恢复操作正在执行，请勿重复提交');
        }

        $now = time();
        $retryBefore = $plan;
        $retryBefore['retry_of'] = $operationId;
        if ($existing) {
            $retryOperationId = (string)$existing['operation_id'];
            Db::name('ipa_operation_log')->where('id', (int)$existing['id'])->update([
                'state'=>'running',
                'plan_hash'=>$plan['plan_hash'],
                'before_json'=>json_encode($retryBefore, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'after_json'=>'{}',
                'result_json'=>'{}',
                'error_message'=>'',
                'admin_id'=>(int)$adminId,
                'started_at'=>$now,
                'finished_at'=>0,
                'updatetime'=>$now,
            ]);
        } else {
            $retryOperationId = substr(hash('sha256', $idem.'|'.uniqid('', true)), 0, 36);
            Db::name('ipa_operation_log')->insert([
                'operation_id'=>$retryOperationId,
                'idempotency_key'=>$idem,
                'operation_type'=>'governance_retry_'.$plan['action'],
                'category_id'=>$plan['category_id'],
                'metadata_id'=>$plan['metadata_id'],
                'plan_hash'=>$plan['plan_hash'],
                'state'=>'running',
                'before_json'=>json_encode($retryBefore, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'after_json'=>'{}',
                'result_json'=>'{}',
                'error_message'=>'',
                'admin_id'=>(int)$adminId,
                'started_at'=>$now,
                'finished_at'=>0,
                'createtime'=>$now,
                'updatetime'=>$now,
            ]);
        }

        try {
            if ($plan['action'] === 'category_update') self::applyCategoryUpdate($plan);
            elseif ($plan['action'] === 'openlist_path') self::applyOpenListPath($plan);
            else throw new RuntimeException('unsupported governance recovery action');

            $verified = self::verifyPlan($plan);
            if (!$verified) throw new RuntimeException('恢复执行后验证失败');

            $finished = time();
            Db::name('ipa_operation_log')->where('operation_id', $retryOperationId)->update([
                'state'=>'success',
                'after_json'=>json_encode(['verified'=>true,'retry_of'=>$operationId], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'result_json'=>json_encode(['verified'=>true,'retry_of'=>$operationId], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'finished_at'=>$finished,
                'updatetime'=>$finished,
            ]);
            Db::name('ipa_operation_log')->where('operation_id', $operationId)->update([
                'state'=>'superseded',
                'result_json'=>json_encode(['recovered_by'=>$retryOperationId], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'updatetime'=>$finished,
            ]);
            Db::name('ipa_governance_issue')->where('id', $issueId)->update([
                'state'=>'resolved',
                'last_verified_at'=>$finished,
                'updatetime'=>$finished,
            ]);
            return ['operation_id'=>$retryOperationId, 'retry_of'=>$operationId, 'verified'=>true];
        } catch (\Exception $e) {
            $finished = time();
            Db::name('ipa_operation_log')->where('operation_id', $retryOperationId)->update([
                'state'=>'failed',
                'error_message'=>mb_substr($e->getMessage(), 0, 2000, 'UTF-8'),
                'finished_at'=>$finished,
                'updatetime'=>$finished,
            ]);
            throw $e;
        }
    }
}
