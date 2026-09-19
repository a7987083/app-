<?php

namespace app\common\library;

use think\Db;
use RuntimeException;

/** Phase 20.7.4 ignore / ignore_until lifecycle management. */
class IpaGovernanceLifecycleService
{
    const MAX_BATCH = 100;

    public static function normalizeIds(array $ids)
    {
        $out=[];
        foreach($ids as $id){$id=(int)$id;if($id>0)$out[$id]=$id;}
        $out=array_values($out);sort($out,SORT_NUMERIC);
        if(!$out)throw new RuntimeException('请选择至少一条治理异常');
        if(count($out)>self::MAX_BATCH)throw new RuntimeException('单次最多处理 '.self::MAX_BATCH.' 条');
        return $out;
    }

    public static function listIgnored($limit=100)
    {
        $limit=max(1,min(500,(int)$limit));$now=time();
        $rows=Db::name('ipa_governance_issue')->where('state','ignored')->order('ignore_until','asc')->limit($limit)->select();
        foreach((array)$rows as &$row){
            $row['ignore_until_text']=!empty($row['ignore_until'])?date('Y-m-d H:i:s',(int)$row['ignore_until']):'';
            $row['remaining_seconds']=max(0,(int)$row['ignore_until']-$now);
            $row['expired']=(int)$row['ignore_until']>0&&(int)$row['ignore_until']<=$now;
        }unset($row);return $rows;
    }

    public static function stats()
    {
        $now=time();
        return [
            'ignored'=>(int)Db::name('ipa_governance_issue')->where('state','ignored')->count(),
            'expired'=>(int)Db::name('ipa_governance_issue')->where('state','ignored')->where('ignore_until','>',0)->where('ignore_until','<=',$now)->count(),
        ];
    }

    public static function ignoreBatch(array $ids,$days,$adminId=0)
    {
        $ids=self::normalizeIds($ids);$days=max(1,min(365,(int)$days));$until=time()+$days*86400;
        $rows=Db::name('ipa_governance_issue')->where('id','in',$ids)->where('state','in',['open','ignored'])->field('id,state,ignore_until')->select();
        if(!$rows)throw new RuntimeException('所选异常当前不可忽略');
        $actualIds=[];foreach((array)$rows as $row)$actualIds[]=(int)$row['id'];
        Db::name('ipa_governance_issue')->where('id','in',$actualIds)->update(['state'=>'ignored','ignore_until'=>$until,'updatetime'=>time()]);
        self::audit('governance_ignore_batch',$actualIds,['days'=>$days,'ignore_until'=>$until],$adminId);
        return ['updated'=>count($actualIds),'ignore_until'=>$until,'ignore_until_text'=>date('Y-m-d H:i:s',$until)];
    }

    public static function unignoreBatch(array $ids,$adminId=0)
    {
        $ids=self::normalizeIds($ids);
        $rows=Db::name('ipa_governance_issue')->where('id','in',$ids)->where('state','ignored')->column('id');
        $actualIds=array_values(array_map('intval',(array)$rows));
        if(!$actualIds)return ['updated'=>0];
        Db::name('ipa_governance_issue')->where('id','in',$actualIds)->update(['state'=>'open','ignore_until'=>0,'updatetime'=>time()]);
        self::audit('governance_unignore_batch',$actualIds,[],$adminId);
        return ['updated'=>count($actualIds)];
    }

    public static function sweepExpired($adminId=0)
    {
        $now=time();
        $ids=Db::name('ipa_governance_issue')->where('state','ignored')->where('ignore_until','>',0)->where('ignore_until','<=',$now)->limit(self::MAX_BATCH)->column('id');
        $ids=array_values(array_map('intval',(array)$ids));
        if(!$ids)return ['reopened'=>0];
        Db::name('ipa_governance_issue')->where('id','in',$ids)->update(['state'=>'open','ignore_until'=>0,'updatetime'=>$now]);
        self::audit('governance_ignore_expiry_sweep',$ids,[],$adminId);
        return ['reopened'=>count($ids)];
    }

    protected static function audit($type,array $ids,array $extra,$adminId)
    {
        $now=time();$payload=array_merge(['issue_ids'=>$ids],$extra);$planHash=IpaFoundation::planHash($payload);
        $idem=IpaFoundation::idempotencyKey($type,['plan_hash'=>$planHash,'at'=>$now]);
        Db::name('ipa_operation_log')->insert([
            'operation_id'=>substr(hash('sha256',$idem.'|'.uniqid('',true)),0,36),
            'idempotency_key'=>$idem,'operation_type'=>$type,'category_id'=>0,'metadata_id'=>0,'plan_hash'=>$planHash,
            'state'=>'success','before_json'=>'{}','after_json'=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'result_json'=>json_encode(['updated'=>count($ids)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'error_message'=>'',
            'admin_id'=>(int)$adminId,'started_at'=>$now,'finished_at'=>$now,'createtime'=>$now,'updatetime'=>$now,
        ]);
    }
}
