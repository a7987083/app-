<?php

namespace app\common\library;

use think\Db;
use RuntimeException;
use Throwable;

class IpaScanService
{
    const STALE_SECONDS = 600;

    public static function createTask($sourceId,$triggerType='manual',$adminId=0,$forceRefresh=false)
    {
        $source=IpaSourceConfig::first(false);
        if (!$source) throw new RuntimeException('IPA source not configured');
        if (empty($source['enabled'])) throw new RuntimeException('IPA source is disabled');
        $now=time();
        $taskKey=hash('sha256',implode('|',['openlist',$triggerType,$now,microtime(true),mt_rand()]));
        return (int)Db::name('ipa_scan_task')->insertGetId([
            'task_key'=>$taskKey,'trigger_type'=>(string)$triggerType,'source_key'=>'openlist',
            'state'=>'queued','stage'=>'queued','cursor_json'=>json_encode(['source_id'=>1,'force_refresh'=>(bool)$forceRefresh],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'progress_current'=>0,'progress_total'=>0,'retry_count'=>0,'created_by'=>(int)$adminId,'createtime'=>$now,'updatetime'=>$now,
        ]);
    }

    public static function runTask($taskId)
    {
        $taskId=(int)$taskId;
        $task=Db::name('ipa_scan_task')->where('id',$taskId)->find();
        if (!$task) throw new RuntimeException('Scan task not found');
        if (in_array($task['state'],['success','cancelled'],true)) return $task;
        $cursor=json_decode(isset($task['cursor_json'])?$task['cursor_json']:'',true); $cursor=is_array($cursor)?$cursor:[];
        $forceRefresh=!empty($cursor['force_refresh']);
        $source=IpaSourceConfig::first(true);
        if (!$source) { self::failTask($taskId,'source_not_found','IPA source not configured'); throw new RuntimeException('IPA source not configured'); }
        if (empty($source['enabled'])) { self::failTask($taskId,'source_disabled','IPA source is disabled'); throw new RuntimeException('IPA source is disabled'); }
        $now=time();
        Db::name('ipa_scan_task')->where('id',$taskId)->update(['state'=>'running','stage'=>'list_remote','started_at'=>$task['started_at']?$task['started_at']:$now,'heartbeat_at'=>$now,'updatetime'=>$now,'error_code'=>'','error_message'=>'']);
        try {
            $listed=null;
            if (!$forceRefresh) $listed=IpaInventoryCache::load('openlist',$source['scan_path'],isset($source['cache_ttl'])?(int)$source['cache_ttl']:1800);
            if (!$listed) {
                $client=IpaSourceConfig::clientFromRow($source);
                $listed=$client->listIpaFiles($source['scan_path'],$source['public_url_template'],$forceRefresh,true);
                $listed['cache_hit']=false;
                IpaInventoryCache::save('openlist',$source['scan_path'],$listed);
            }
            $remoteRows=[];
            foreach ($listed['files'] as $row) { $row['source_key']='openlist'; $remoteRows[]=$row; }
            Db::name('ipa_scan_task')->where('id',$taskId)->update(['stage'=>'compare_metadata','progress_current'=>0,'progress_total'=>count($remoteRows),'heartbeat_at'=>time(),'updatetime'=>time()]);
            $localRows=Db::name('ipa_metadata')->where('source_key','openlist')->select();
            $plan=IpaScanPlanner::plan($remoteRows,is_array($localRows)?$localRows:[]);
            self::applyPlan($taskId,$source,$plan);
            $summary=$plan['summary'];
            $summary['directories']=$listed['directories']; $summary['scan_path']=$source['scan_path']; $summary['force_refresh']=$forceRefresh; $summary['cache_hit']=!empty($listed['cache_hit']); $summary['discovery_completed_at']=time();
            Db::name('ipa_scan_task')->where('id',$taskId)->update(['state'=>'success','stage'=>'discovery_complete','progress_current'=>count($remoteRows),'progress_total'=>count($remoteRows),'cursor_json'=>json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'heartbeat_at'=>time(),'finished_at'=>time(),'updatetime'=>time()]);
            IpaSourceConfig::updateState(['last_scan_at'=>time(),'last_health'=>'ok','last_checked_at'=>time()]);
            self::pruneTaskItems();
            return Db::name('ipa_scan_task')->where('id',$taskId)->find();
        } catch (\Exception $e) { self::failTask($taskId,'scan_failed',$e->getMessage()); throw $e; }
        catch (Throwable $e) { self::failTask($taskId,'scan_failed',$e->getMessage()); throw $e; }
    }

    protected static function applyPlan($taskId,array $source,array $plan)
    {
        $now=time(); Db::startTrans();
        try {
            foreach ($plan['new'] as $item) {
                $remote=$item['remote'];
                $metadataId=Db::name('ipa_metadata')->insertGetId(['source_key'=>'openlist','remote_path'=>$remote['remote_path'],'remote_path_hash'=>$remote['remote_path_hash'],'file_name'=>$remote['file_name'],'public_url'=>$remote['public_url'],'file_size'=>$remote['file_size'],'remote_mtime'=>$remote['remote_mtime'],'etag'=>$remote['etag'],'md5'=>$remote['md5'],'parse_state'=>'pending','parser_version'=>IpaFoundation::PARSER_VERSION,'last_seen_at'=>$now,'createtime'=>$now,'updatetime'=>$now]);
                self::insertTaskItem($taskId,$metadataId,$remote,'new');
            }
            foreach ($plan['changed'] as $item) {
                $remote=$item['remote']; $local=$item['local']; $metadataId=(int)$local['id'];
                Db::name('ipa_metadata')->where('id',$metadataId)->update(['remote_path'=>$remote['remote_path'],'file_name'=>$remote['file_name'],'public_url'=>$remote['public_url'],'file_size'=>$remote['file_size'],'remote_mtime'=>$remote['remote_mtime'],'etag'=>$remote['etag'],'md5'=>$remote['md5'],'parse_state'=>'pending','parse_error'=>'','parsed_at'=>0,'last_seen_at'=>$now,'updatetime'=>$now]);
                self::insertTaskItem($taskId,$metadataId,$remote,'changed',['old_fingerprint'=>$item['old_fingerprint'],'new_fingerprint'=>$item['new_fingerprint']]);
            }
            foreach ($plan['unchanged'] as $item) {
                $local=$item['local']; $remote=$item['remote'];
                Db::name('ipa_metadata')->where('id',(int)$local['id'])->update(['public_url'=>$remote['public_url'],'last_seen_at'=>$now,'updatetime'=>$now]);
            }
            foreach ($plan['missing'] as $item) {
                $local=$item['local']; $remote=['remote_path_hash'=>$local['remote_path_hash'],'remote_path'=>$local['remote_path']];
                self::insertTaskItem($taskId,(int)$local['id'],$remote,'missing',['last_seen_at'=>isset($local['last_seen_at'])?(int)$local['last_seen_at']:0],'success','missing');
            }
            Db::commit();
        } catch (\Exception $e) { Db::rollback(); throw $e; }
        catch (Throwable $e) { Db::rollback(); throw $e; }
    }

    protected static function insertTaskItem($taskId,$metadataId,array $remote,$discoveryType,array $extra=[],$state='queued',$stage='parser_pending')
    {
        $itemKey=hash('sha256',implode('|',[(int)$taskId,isset($remote['remote_path_hash'])?$remote['remote_path_hash']:'',$discoveryType]));
        $result=array_merge(['discovery_type'=>$discoveryType,'remote_path'=>isset($remote['remote_path'])?$remote['remote_path']:''],$extra);
        Db::name('ipa_scan_task_item')->insert(['task_id'=>(int)$taskId,'metadata_id'=>(int)$metadataId,'item_key'=>$itemKey,'state'=>$state,'stage'=>$stage,'retry_after'=>0,'retry_count'=>0,'result_json'=>json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'createtime'=>time(),'updatetime'=>time()]);
    }

    /** Short-lived queue only: parsed/missing rows are not a permanent history store. */
    public static function pruneTaskItems($retentionDays=30)
    {
        $cutoff=time()-max(1,(int)$retentionDays)*86400;
        return Db::name('ipa_scan_task_item')->where('state','in',['success'])->where('updatetime','<',$cutoff)->delete();
    }

    public static function markStaleInterrupted($staleSeconds=self::STALE_SECONDS)
    {
        $cutoff=time()-max(60,(int)$staleSeconds);
        return Db::name('ipa_scan_task')->where('state','in',['queued','running','retrying'])->where('heartbeat_at','<',$cutoff)->where('heartbeat_at','>',0)->update(['state'=>'interrupted','stage'=>'interrupted','updatetime'=>time()]);
    }

    public static function nextPendingTask()
    {
        return Db::name('ipa_scan_task')->where('state','in',['queued','interrupted','retrying'])->order('id','asc')->find();
    }

    public static function createDueScheduledTasks()
    {
        $source=IpaSourceConfig::first(false);$created=[];
        if(!$source||empty($source['enabled'])||empty($source['schedule_enabled']))return $created;
        $interval=max(5,(int)$source['interval_minutes'])*60;
        if (!empty($source['last_scan_at']) && (time()-(int)$source['last_scan_at'])<$interval) return $created;
        $created[]=self::createTask(1,'schedule',0,false);
        return $created;
    }

    public static function spawn($taskId)
    {
        if (!defined('ROOT_PATH')) return false;
        $php=defined('PHP_BINARY')&&PHP_BINARY?PHP_BINARY:'php'; $think=ROOT_PATH.'think';
        if (!is_file($think)) return false;
        $command=escapeshellarg($php).' '.escapeshellarg($think).' ipa:scan --task='.(int)$taskId;
        if (stripos(PHP_OS,'WIN')===0) { @pclose(@popen('start /B '.$command,'r')); return true; }
        if (!function_exists('exec')) return false;
        @exec('nohup '.$command.' >/dev/null 2>&1 &'); return true;
    }

    protected static function failTask($taskId,$code,$message)
    {
        Db::name('ipa_scan_task')->where('id',(int)$taskId)->update(['state'=>'failed','stage'=>'failed','error_code'=>(string)$code,'error_message'=>mb_substr((string)$message,0,2000,'UTF-8'),'heartbeat_at'=>time(),'finished_at'=>time(),'updatetime'=>time()]);
    }
}
