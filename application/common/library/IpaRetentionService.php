<?php

namespace app\common\library;

use think\Db;
use RuntimeException;

/** Phase 20.7.3 conservative retention cleanup with preview-first semantics. */
class IpaRetentionService
{
    const DEFAULT_DAYS = 90;
    const MIN_DAYS = 7;
    const MAX_DAYS = 3650;
    const MAX_DELETE_PER_TABLE = 1000;

    public static function normalizeDays($days)
    {
        return max(self::MIN_DAYS, min(self::MAX_DAYS, (int)$days));
    }

    public static function preview($days=self::DEFAULT_DAYS)
    {
        $days=self::normalizeDays($days);
        $cutoff=time()-$days*86400;
        $taskIds=Db::name('ipa_scan_task')
            ->where('state','in',['success','cancelled'])
            ->where('updatetime','<',$cutoff)
            ->order('id','asc')
            ->limit(self::MAX_DELETE_PER_TABLE)
            ->column('id');
        $taskIds=array_values(array_map('intval',(array)$taskIds));

        $operationIds=Db::name('ipa_operation_log')
            ->where('state','in',['success','superseded'])
            ->where('updatetime','<',$cutoff)
            ->order('id','asc')
            ->limit(self::MAX_DELETE_PER_TABLE)
            ->column('id');
        $operationIds=array_values(array_map('intval',(array)$operationIds));

        $itemCount=0;
        if($taskIds)$itemCount=(int)Db::name('ipa_scan_task_item')->where('task_id','in',$taskIds)->count();

        return [
            'days'=>$days,
            'cutoff'=>$cutoff,
            'task_ids'=>$taskIds,
            'operation_log_ids'=>$operationIds,
            'task_count'=>count($taskIds),
            'task_item_count'=>$itemCount,
            'operation_log_count'=>count($operationIds),
            'protected_states'=>['running','failed','interrupted','queued','retrying'],
            'capped'=>count($taskIds)>=self::MAX_DELETE_PER_TABLE||count($operationIds)>=self::MAX_DELETE_PER_TABLE,
        ];
    }

    public static function apply($days,$expectedHash)
    {
        $plan=self::preview($days);
        $hash=IpaFoundation::planHash([
            'days'=>$plan['days'],
            'cutoff'=>$plan['cutoff'],
            'task_ids'=>$plan['task_ids'],
            'operation_log_ids'=>$plan['operation_log_ids'],
        ]);
        if(!hash_equals((string)$hash,(string)$expectedHash))throw new RuntimeException('Retention 计划已变化，请重新预览');

        Db::startTrans();
        try{
            $deletedItems=0;$deletedTasks=0;$deletedLogs=0;
            if($plan['task_ids']){
                $deletedItems=(int)Db::name('ipa_scan_task_item')->where('task_id','in',$plan['task_ids'])->delete();
                $deletedTasks=(int)Db::name('ipa_scan_task')->where('id','in',$plan['task_ids'])->where('state','in',['success','cancelled'])->delete();
            }
            if($plan['operation_log_ids']){
                $deletedLogs=(int)Db::name('ipa_operation_log')->where('id','in',$plan['operation_log_ids'])->where('state','in',['success','superseded'])->delete();
            }
            Db::commit();
            return ['plan_hash'=>$hash,'deleted_tasks'=>$deletedTasks,'deleted_task_items'=>$deletedItems,'deleted_operation_logs'=>$deletedLogs];
        }catch(\Exception $e){Db::rollback();throw $e;}
    }

    public static function previewWithHash($days=self::DEFAULT_DAYS)
    {
        $plan=self::preview($days);
        $plan['plan_hash']=IpaFoundation::planHash([
            'days'=>$plan['days'],
            'cutoff'=>$plan['cutoff'],
            'task_ids'=>$plan['task_ids'],
            'operation_log_ids'=>$plan['operation_log_ids'],
        ]);
        return $plan;
    }
}
