<?php

namespace app\common\library;

use think\Config;
use think\Db;

/** Maintains ipa_metadata as a bounded active work-set. */
class IpaMetadataWorksetService
{
    public static function invalidateIfNoEnabledSources()
    {
        if(IpaMysqlSourceService::all(false,true))return 0;
        return (int)Db::name('ipa_metadata')->where('source_key','openlist')->where('referenced',1)->update(['referenced'=>0,'updatetime'=>time()]);
    }

    /**
     * Remove unreferenced, unbound work rows. Successful rows are copied to
     * the durable MD5 cache first. parsing rows are never touched.
     */
    public static function pruneUnreferenced($limit=10000)
    {
        $limit=max(1,min(50000,(int)$limit));$deleted=0;$cached=0;
        while($deleted<$limit){
            $ids=self::candidateIds(min(500,$limit-$deleted));
            if(!$ids)break;
            $rows=Db::name('ipa_metadata')->where('id','in',$ids)->select();
            foreach((array)$rows as $row){if(isset($row['parse_state'])&&$row['parse_state']==='success'){if(IpaParseCache::remember($row))$cached++;}}
            Db::startTrans();
            try{
                Db::name('ipa_scan_task_item')->where('metadata_id','in',$ids)->delete();
                $count=(int)Db::name('ipa_metadata')->where('id','in',$ids)->where('referenced',0)->delete();
                Db::commit();$deleted+=$count;
            }catch(\Exception $e){Db::rollback();throw $e;}catch(\Throwable $e){Db::rollback();throw $e;}
            if($count===0)break;
        }
        return ['deleted'=>$deleted,'cached'=>$cached];
    }

    public static function orphanStats()
    {
        $rows=Db::name('ipa_metadata')->field('parse_state,COUNT(*) AS total')->where('source_key','openlist')->where('referenced',0)->group('parse_state')->select();
        $out=['total'=>0,'pending'=>0,'success'=>0,'failed'=>0,'parsing'=>0];
        foreach((array)$rows as $row){$state=isset($row['parse_state'])?(string)$row['parse_state']:'';$count=isset($row['total'])?(int)$row['total']:0;$out['total']+=$count;if(isset($out[$state]))$out[$state]+=$count;}
        return $out;
    }

    protected static function candidateIds($limit)
    {
        $prefix=(string)Config::get('database.prefix');
        $prefix=preg_replace('/[^A-Za-z0-9_]/','',$prefix);
        $meta='`'.$prefix.'ipa_metadata`';$binding='`'.$prefix.'ipa_binding`';
        $sql="SELECT m.id FROM {$meta} m LEFT JOIN {$binding} b ON b.metadata_id=m.id WHERE m.source_key='openlist' AND m.referenced=0 AND m.parse_state IN ('pending','success','failed') AND b.id IS NULL ORDER BY m.id ASC LIMIT ".(int)$limit;
        $rows=Db::query($sql);$ids=[];foreach((array)$rows as $row){if(!empty($row['id']))$ids[]=(int)$row['id'];}return $ids;
    }
}
