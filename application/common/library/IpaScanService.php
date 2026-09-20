<?php

namespace app\common\library;

use think\Db;
use RuntimeException;
use Throwable;

/**
 * IPA discovery service.
 *
 * 2026091918: align the scan model with the old OpenList service. A scan is a
 * point-in-time JSON snapshot; fa_ipa_metadata remains the current parsed
 * metadata index, but discovery no longer depends on relational work-set flags
 * (referenced / needs_reparse) or per-file scan task rows.
 */
class IpaScanService
{
    const STALE_SECONDS = 600;

    public static function createTask($sourceId,$triggerType='manual',$adminId=0,$forceRefresh=false)
    {
        $source=IpaSourceConfig::first(false);
        if(!$source)throw new RuntimeException('请先配置 OpenList');
        if(empty($source['enabled']))throw new RuntimeException('OpenList 已停用');
        if(!IpaMysqlSourceService::all(true,true))throw new RuntimeException('没有启用的 MySQL 软件源，请先添加并启用软件源');
        $now=time();$taskKey=hash('sha256',implode('|',['openlist',$triggerType,$now,microtime(true),mt_rand()]));
        return (int)Db::name('ipa_scan_task')->insertGetId([
            'task_key'=>$taskKey,'trigger_type'=>(string)$triggerType,'source_key'=>'openlist','state'=>'queued','stage'=>'queued',
            'cursor_json'=>json_encode(['source_id'=>(int)$source['id'],'force_refresh'=>(bool)$forceRefresh],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'progress_current'=>0,'progress_total'=>0,'retry_count'=>0,'created_by'=>(int)$adminId,'createtime'=>$now,'updatetime'=>$now,
        ]);
    }

    public static function runTask($taskId)
    {
        $taskId=(int)$taskId;$task=Db::name('ipa_scan_task')->where('id',$taskId)->find();
        if(!$task)throw new RuntimeException('Scan task not found');
        if(in_array($task['state'],['success','cancelled'],true))return $task;
        $cursor=json_decode(isset($task['cursor_json'])?$task['cursor_json']:'',true);$cursor=is_array($cursor)?$cursor:[];$forceRefresh=!empty($cursor['force_refresh']);
        $source=IpaSourceConfig::first(true);
        if(!$source){self::failTask($taskId,'source_not_found','请先配置 OpenList');throw new RuntimeException('请先配置 OpenList');}
        if(empty($source['enabled'])){self::failTask($taskId,'source_disabled','OpenList 已停用');throw new RuntimeException('OpenList 已停用');}
        $now=time();Db::name('ipa_scan_task')->where('id',$taskId)->update(['state'=>'running','stage'=>'read_references','started_at'=>$task['started_at']?$task['started_at']:$now,'heartbeat_at'=>$now,'updatetime'=>$now,'error_code'=>'','error_message'=>'']);
        try{
            $listed=IpaReferenceDiscoveryService::listReferencedRemoteFiles($source,$forceRefresh);
            $reference=isset($listed['reference'])&&is_array($listed['reference'])?$listed['reference']:[];$sourceCount=isset($reference['sources'])?(int)$reference['sources']:0;
            if($sourceCount<=0)throw new RuntimeException('没有启用的 MySQL 软件源，请先添加并启用软件源');
            $expectedPaths=isset($reference['paths'])?array_values((array)$reference['paths']):[];$sourceErrors=isset($reference['errors'])?(array)$reference['errors']:[];
            if($sourceErrors){$messages=[];foreach($sourceErrors as $error)$messages[]=isset($error['message'])?(string)$error['message']:'读取软件源失败';throw new RuntimeException('MySQL 软件源读取失败：'.implode('；',$messages));}
            $listed['cache_hit']=((int)$listed['cache_refreshes']===0&&(int)$listed['cache_hits']>0);
            $localRows=[];if($expectedPaths)$localRows=Db::name('ipa_metadata')->where('source_key','openlist')->where('remote_path','in',$expectedPaths)->select();
            $remoteRows=[];foreach((array)$listed['files'] as $row){$row['source_key']='openlist';$remoteRows[]=$row;}
            Db::name('ipa_scan_task')->where('id',$taskId)->update(['stage'=>'compare_metadata','progress_current'=>0,'progress_total'=>count($expectedPaths),'heartbeat_at'=>time(),'updatetime'=>time()]);
            $plan=IpaScanPlanner::plan($remoteRows,is_array($localRows)?$localRows:[]);
            $applied=self::applyPlan($source,$plan);
            $summary=$plan['summary'];$summary['missing']=max((int)$summary['missing'],count($expectedPaths)-count($remoteRows));$summary['reference_mode']=true;
            $summary['database_sources']=$sourceCount;$summary['database_refs']=isset($reference['refs'])?count((array)$reference['refs']):0;$summary['ignored_refs']=isset($reference['ignored'])?(int)$reference['ignored']:0;$summary['source_errors']=[];
            $summary['cache_hits']=(int)$listed['cache_hits'];$summary['cache_refreshes']=(int)$listed['cache_refreshes'];$summary['directories']=isset($listed['directories'])?(int)$listed['directories']:0;
            $summary['scan_path']=$source['scan_path'];$summary['force_refresh']=$forceRefresh;$summary['cache_hit']=!empty($listed['cache_hit']);$summary['discovery_completed_at']=time();
            // The authoritative work-set is the JSON snapshot, matching the old project model.
            $summary['current_paths']=$expectedPaths;
            $summary['parse_ids']=$applied['parse_ids'];
            $summary['new_ids']=$applied['new_ids'];
            $summary['changed_ids']=$applied['changed_ids'];
            $summary['snapshot_version']=1;
            $summary['workset_hash']=hash('sha256',json_encode($expectedPaths,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            Db::name('ipa_scan_task')->where('id',$taskId)->update(['state'=>'success','stage'=>'discovery_complete','progress_current'=>count($expectedPaths),'progress_total'=>count($expectedPaths),'cursor_json'=>json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'heartbeat_at'=>time(),'finished_at'=>time(),'updatetime'=>time()]);
            IpaSourceConfig::updateState(['last_scan_at'=>time(),'last_health'=>'ok','last_checked_at'=>time()]);IpaMetadataPayloadStore::compactLegacy(50);
            return Db::name('ipa_scan_task')->where('id',$taskId)->find();
        }catch(\Exception $e){self::failTask($taskId,'scan_failed',$e->getMessage());throw $e;}catch(Throwable $e){self::failTask($taskId,'scan_failed',$e->getMessage());throw $e;}
    }

    protected static function applyPlan(array $source,array $plan)
    {
        $now=time();$parseIds=[];$newIds=[];$changedIds=[];Db::startTrans();
        try{
            foreach((array)$plan['new'] as $item){
                $remote=$item['remote'];
                $metadataId=(int)Db::name('ipa_metadata')->insertGetId([
                    'source_key'=>'openlist','remote_path'=>$remote['remote_path'],'remote_path_hash'=>$remote['remote_path_hash'],'file_name'=>$remote['file_name'],'public_url'=>$remote['public_url'],
                    'file_size'=>$remote['file_size'],'remote_mtime'=>$remote['remote_mtime'],'etag'=>$remote['etag'],'md5'=>$remote['md5'],'parse_state'=>'pending','parser_version'=>IpaFoundation::PARSER_VERSION,
                    'last_seen_at'=>$now,'createtime'=>$now,'updatetime'=>$now
                ]);
                $parseIds[]=$metadataId;$newIds[]=$metadataId;
            }
            foreach((array)$plan['changed'] as $item){
                $remote=$item['remote'];$local=$item['local'];$metadataId=(int)$local['id'];
                $patch=['remote_path'=>$remote['remote_path'],'file_name'=>$remote['file_name'],'public_url'=>$remote['public_url'],'file_size'=>$remote['file_size'],'remote_mtime'=>$remote['remote_mtime'],'etag'=>$remote['etag'],'md5'=>$remote['md5'],'last_seen_at'=>$now,'updatetime'=>$now];
                // If parsing is already in flight, leave the claim intact. The parser compares
                // the source fingerprint before commit and converts a stale parse back to pending.
                if(!isset($local['parse_state'])||$local['parse_state']!=='parsing'){$patch['parse_state']='pending';$patch['parse_error']='';$patch['parsed_at']=0;}
                Db::name('ipa_metadata')->where('id',$metadataId)->update($patch);$parseIds[]=$metadataId;$changedIds[]=$metadataId;
            }
            foreach((array)$plan['unchanged'] as $item){$local=$item['local'];$remote=$item['remote'];Db::name('ipa_metadata')->where('id',(int)$local['id'])->update(['public_url'=>$remote['public_url'],'last_seen_at'=>$now,'updatetime'=>$now]);}
            // Missing entries are represented by the scan JSON snapshot. Do not mutate or delete
            // historical/current metadata here; bindings and parsed metadata remain stable.
            Db::commit();
        }catch(\Exception $e){Db::rollback();throw $e;}catch(Throwable $e){Db::rollback();throw $e;}
        return ['parse_ids'=>array_values(array_unique($parseIds)),'new_ids'=>array_values(array_unique($newIds)),'changed_ids'=>array_values(array_unique($changedIds))];
    }

    public static function snapshot($taskId)
    {
        $row=Db::name('ipa_scan_task')->where('id',(int)$taskId)->find();if(!$row)return [];$json=json_decode(isset($row['cursor_json'])?$row['cursor_json']:'',true);return is_array($json)?$json:[];
    }

    public static function latestSnapshot()
    {
        $row=Db::name('ipa_scan_task')->where('state','success')->order('id','desc')->find();if(!$row)return [];$json=json_decode(isset($row['cursor_json'])?$row['cursor_json']:'',true);return is_array($json)?$json:[];
    }

    public static function isPathCurrent($path,array $snapshot=null)
    {
        if($snapshot===null)$snapshot=self::latestSnapshot();$paths=isset($snapshot['current_paths'])&&is_array($snapshot['current_paths'])?$snapshot['current_paths']:[];return in_array((string)$path,$paths,true);
    }

    public static function markStaleInterrupted($staleSeconds=self::STALE_SECONDS)
    {
        $cutoff=time()-max(60,(int)$staleSeconds);$now=time();$count=0;
        $count+=(int)Db::name('ipa_scan_task')->where('state','queued')->where('heartbeat_at',0)->where('createtime','<',$cutoff)->update(['state'=>'interrupted','stage'=>'interrupted','updatetime'=>$now]);
        $count+=(int)Db::name('ipa_scan_task')->where('state','in',['queued','running','retrying'])->where('heartbeat_at','>',0)->where('heartbeat_at','<',$cutoff)->update(['state'=>'interrupted','stage'=>'interrupted','updatetime'=>$now]);return $count;
    }

    public static function nextPendingTask(){return Db::name('ipa_scan_task')->where('state','in',['queued','interrupted','retrying'])->order('id','asc')->find();}
    public static function createDueScheduledTasks(){ $source=IpaSourceConfig::first(false);$created=[];if(!$source||empty($source['enabled'])||empty($source['schedule_enabled']))return $created;if(!IpaMysqlSourceService::all(true,true))return $created;$interval=max(5,(int)$source['interval_minutes'])*60;if(!empty($source['last_scan_at'])&&(time()-(int)$source['last_scan_at'])<$interval)return $created;$created[]=self::createTask((int)$source['id'],'schedule',0,false);return $created; }

    public static function spawn($taskId)
    {
        IpaWorkerService::enqueueScan((int)$taskId,0);return true;
    }

    // Kept as a no-op compatibility hook for callers from pre-1918 code.
    public static function pruneTaskItems($retentionDays=90){return 0;}

    protected static function failTask($taskId,$code,$message){Db::name('ipa_scan_task')->where('id',(int)$taskId)->update(['state'=>'failed','stage'=>'failed','error_code'=>(string)$code,'error_message'=>mb_substr((string)$message,0,2000,'UTF-8'),'heartbeat_at'=>time(),'finished_at'=>time(),'updatetime'=>time()]);}
}
