<?php

namespace app\common\library;

use think\Db;
use RuntimeException;
use Throwable;

class IpaScanService
{
    const STALE_SECONDS = 600;
    const TASK_ITEM_RETENTION_DAYS = 90;

    public static function createTask($sourceId,$triggerType='manual',$adminId=0,$forceRefresh=false)
    {
        $source=IpaSourceConfig::first(false);
        if (!$source) throw new RuntimeException('请先配置 OpenList');
        if (empty($source['enabled'])) throw new RuntimeException('OpenList 已停用');
        $mysqlSources=IpaMysqlSourceService::all(true,true);
        if (!$mysqlSources) throw new RuntimeException('没有启用的 MySQL 软件源，请先添加并启用软件源');
        $now=time();
        $taskKey=hash('sha256',implode('|',['openlist',$triggerType,$now,microtime(true),mt_rand()]));
        return (int)Db::name('ipa_scan_task')->insertGetId([
            'task_key'=>$taskKey,'trigger_type'=>(string)$triggerType,'source_key'=>'openlist',
            'state'=>'queued','stage'=>'queued','cursor_json'=>json_encode(['source_id'=>(int)$source['id'],'force_refresh'=>(bool)$forceRefresh],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'progress_current'=>0,'progress_total'=>0,'retry_count'=>0,'created_by'=>(int)$adminId,'createtime'=>$now,'updatetime'=>$now,
        ]);
    }

    public static function runTask($taskId)
    {
        $taskId=(int)$taskId;
        $task=Db::name('ipa_scan_task')->where('id',$taskId)->find();
        if (!$task) throw new RuntimeException('Scan task not found');
        if (in_array($task['state'],['success','cancelled'],true)) return $task;
        $cursor=json_decode(isset($task['cursor_json'])?$task['cursor_json']:'',true);$cursor=is_array($cursor)?$cursor:[];
        $forceRefresh=!empty($cursor['force_refresh']);
        $source=IpaSourceConfig::first(true);
        if (!$source) {self::failTask($taskId,'source_not_found','请先配置 OpenList');throw new RuntimeException('请先配置 OpenList');}
        if (empty($source['enabled'])) {self::failTask($taskId,'source_disabled','OpenList 已停用');throw new RuntimeException('OpenList 已停用');}
        $now=time();
        Db::name('ipa_scan_task')->where('id',$taskId)->update(['state'=>'running','stage'=>'read_references','started_at'=>$task['started_at']?$task['started_at']:$now,'heartbeat_at'=>$now,'updatetime'=>$now,'error_code'=>'','error_message'=>'']);
        try {
            // Strict work-set mode: MySQL software-source bt1a references are
            // authoritative. OpenList is only the file/MD5/Range provider.
            $listed=IpaReferenceDiscoveryService::listReferencedRemoteFiles($source,$forceRefresh);
            $reference=isset($listed['reference'])&&is_array($listed['reference'])?$listed['reference']:[];
            $sourceCount=isset($reference['sources'])?(int)$reference['sources']:0;
            if($sourceCount<=0)throw new RuntimeException('没有启用的 MySQL 软件源，请先添加并启用软件源');
            $expectedPaths=isset($reference['paths'])?(array)$reference['paths']:[];
            $sourceErrors=isset($reference['errors'])?(array)$reference['errors']:[];
            if($sourceErrors){
                $messages=[];foreach($sourceErrors as $error){$messages[]=isset($error['message'])?(string)$error['message']:'读取软件源失败';}
                throw new RuntimeException('MySQL 软件源读取失败：'.implode('；',$messages));
            }
            $listed['cache_hit']=((int)$listed['cache_refreshes']===0&&(int)$listed['cache_hits']>0);
            $localRows=[];
            if($expectedPaths)$localRows=Db::name('ipa_metadata')->where('source_key','openlist')->where('remote_path','in',$expectedPaths)->select();

            $remoteRows=[];
            foreach((array)$listed['files'] as $row){$row['source_key']='openlist';$remoteRows[]=$row;}
            Db::name('ipa_scan_task')->where('id',$taskId)->update(['stage'=>'compare_metadata','progress_current'=>0,'progress_total'=>count($expectedPaths),'heartbeat_at'=>time(),'updatetime'=>time()]);
            $plan=IpaScanPlanner::plan($remoteRows,is_array($localRows)?$localRows:[]);
            self::applyPlan($taskId,$source,$plan,$expectedPaths,true);
            $summary=$plan['summary'];
            $summary['missing']=max((int)$summary['missing'],count($expectedPaths)-count($remoteRows));
            $summary['reference_mode']=true;
            $summary['database_sources']=$sourceCount;
            $summary['database_refs']=isset($reference['refs'])?count((array)$reference['refs']):0;
            $summary['ignored_refs']=isset($reference['ignored'])?(int)$reference['ignored']:0;
            $summary['source_errors']=[];
            $summary['cache_hits']=(int)$listed['cache_hits'];$summary['cache_refreshes']=(int)$listed['cache_refreshes'];
            $summary['directories']=isset($listed['directories'])?(int)$listed['directories']:0;
            $summary['scan_path']=$source['scan_path'];$summary['force_refresh']=$forceRefresh;$summary['cache_hit']=!empty($listed['cache_hit']);$summary['discovery_completed_at']=time();
            Db::name('ipa_scan_task')->where('id',$taskId)->update(['state'=>'success','stage'=>'discovery_complete','progress_current'=>count($expectedPaths),'progress_total'=>count($expectedPaths),'cursor_json'=>json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'heartbeat_at'=>time(),'finished_at'=>time(),'updatetime'=>time()]);
            IpaSourceConfig::updateState(['last_scan_at'=>time(),'last_health'=>'ok','last_checked_at'=>time()]);
            self::pruneTaskItems(self::TASK_ITEM_RETENTION_DAYS);IpaMetadataPayloadStore::compactLegacy(50);
            return Db::name('ipa_scan_task')->where('id',$taskId)->find();
        }catch(\Exception $e){self::failTask($taskId,'scan_failed',$e->getMessage());throw $e;}
        catch(Throwable $e){self::failTask($taskId,'scan_failed',$e->getMessage());throw $e;}
    }

    protected static function applyPlan($taskId,array $source,array $plan,array $expectedPaths=[],$authoritative=false)
    {
        $now=time();Db::startTrans();
        try{
            // Only after every configured MySQL source was read successfully do
            // we reconcile the work-set. This avoids orphaning healthy rows on a
            // temporary database outage.
            if($authoritative)Db::name('ipa_metadata')->where('source_key','openlist')->update(['referenced'=>0,'updatetime'=>$now]);
            foreach($plan['new'] as $item){
                $remote=$item['remote'];
                $metadataId=Db::name('ipa_metadata')->insertGetId(['source_key'=>'openlist','remote_path'=>$remote['remote_path'],'remote_path_hash'=>$remote['remote_path_hash'],'file_name'=>$remote['file_name'],'public_url'=>$remote['public_url'],'file_size'=>$remote['file_size'],'remote_mtime'=>$remote['remote_mtime'],'etag'=>$remote['etag'],'md5'=>$remote['md5'],'parse_state'=>'pending','parser_version'=>IpaFoundation::PARSER_VERSION,'referenced'=>1,'needs_reparse'=>0,'last_seen_at'=>$now,'createtime'=>$now,'updatetime'=>$now]);
                self::insertTaskItem($taskId,$metadataId,$remote,'new');
            }
            foreach($plan['changed'] as $item){
                $remote=$item['remote'];$local=$item['local'];$metadataId=(int)$local['id'];
                $patch=['remote_path'=>$remote['remote_path'],'file_name'=>$remote['file_name'],'public_url'=>$remote['public_url'],'file_size'=>$remote['file_size'],'remote_mtime'=>$remote['remote_mtime'],'etag'=>$remote['etag'],'md5'=>$remote['md5'],'referenced'=>1,'last_seen_at'=>$now,'updatetime'=>$now];
                if(isset($local['parse_state'])&&$local['parse_state']==='parsing'){$patch['needs_reparse']=1;}else{$patch['parse_state']='pending';$patch['needs_reparse']=0;$patch['parse_error']='';$patch['parsed_at']=0;}
                Db::name('ipa_metadata')->where('id',$metadataId)->update($patch);
                self::insertTaskItem($taskId,$metadataId,$remote,'changed',['old_fingerprint'=>$item['old_fingerprint'],'new_fingerprint'=>$item['new_fingerprint']]);
            }
            foreach($plan['unchanged'] as $item){$local=$item['local'];$remote=$item['remote'];Db::name('ipa_metadata')->where('id',(int)$local['id'])->update(['public_url'=>$remote['public_url'],'referenced'=>1,'last_seen_at'=>$now,'updatetime'=>$now]);}
            foreach($plan['missing'] as $item){$local=$item['local'];$remote=['remote_path_hash'=>$local['remote_path_hash'],'remote_path'=>$local['remote_path']];Db::name('ipa_metadata')->where('id',(int)$local['id'])->update(['referenced'=>1,'updatetime'=>$now]);self::insertTaskItem($taskId,(int)$local['id'],$remote,'missing',['last_seen_at'=>isset($local['last_seen_at'])?(int)$local['last_seen_at']:0],'success','missing');}
            Db::commit();
        }catch(\Exception $e){Db::rollback();throw $e;}catch(Throwable $e){Db::rollback();throw $e;}
    }

    protected static function insertTaskItem($taskId,$metadataId,array $remote,$discoveryType,array $extra=[],$state='queued',$stage='parser_pending')
    {
        $itemKey=hash('sha256',implode('|',[(int)$taskId,isset($remote['remote_path_hash'])?$remote['remote_path_hash']:'',$discoveryType]));
        $result=array_merge(['discovery_type'=>$discoveryType,'remote_path'=>isset($remote['remote_path'])?$remote['remote_path']:''],$extra);
        Db::name('ipa_scan_task_item')->insert(['task_id'=>(int)$taskId,'metadata_id'=>(int)$metadataId,'item_key'=>$itemKey,'state'=>$state,'stage'=>$stage,'retry_after'=>0,'retry_count'=>0,'result_json'=>json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'createtime'=>time(),'updatetime'=>time()]);
    }

    public static function pruneTaskItems($retentionDays=self::TASK_ITEM_RETENTION_DAYS){$cutoff=time()-max(1,(int)$retentionDays)*86400;return Db::name('ipa_scan_task_item')->where('state','success')->where('updatetime','<',$cutoff)->delete();}

    public static function markStaleInterrupted($staleSeconds=self::STALE_SECONDS)
    {
        $cutoff=time()-max(60,(int)$staleSeconds);$now=time();$count=0;
        // A never-started queued task has heartbeat_at=0. The old code skipped
        // it forever, which is the direct cause of queued/0/0 tombstones.
        $count+=(int)Db::name('ipa_scan_task')->where('state','queued')->where('heartbeat_at',0)->where('createtime','<',$cutoff)->update(['state'=>'interrupted','stage'=>'interrupted','updatetime'=>$now]);
        $count+=(int)Db::name('ipa_scan_task')->where('state','in',['queued','running','retrying'])->where('heartbeat_at','>',0)->where('heartbeat_at','<',$cutoff)->update(['state'=>'interrupted','stage'=>'interrupted','updatetime'=>$now]);
        return $count;
    }

    public static function nextPendingTask(){return Db::name('ipa_scan_task')->where('state','in',['queued','interrupted','retrying'])->order('id','asc')->find();}
    public static function createDueScheduledTasks(){ $source=IpaSourceConfig::first(false);$created=[];if(!$source||empty($source['enabled'])||empty($source['schedule_enabled']))return $created;if(!IpaMysqlSourceService::all(true,true))return $created;$interval=max(5,(int)$source['interval_minutes'])*60;if(!empty($source['last_scan_at'])&&(time()-(int)$source['last_scan_at'])<$interval)return $created;$created[]=self::createTask((int)$source['id'],'schedule',0,false);return $created; }

    public static function spawn($taskId)
    {
        if(!defined('ROOT_PATH')||!function_exists('exec'))return false;
        $think=ROOT_PATH.'think';if(!is_file($think))return false;
        $php=self::cliPhpBinary();if($php==='')return false;
        $output=[];$code=0;
        @exec('nohup '.escapeshellarg($php).' '.escapeshellarg($think).' ipa:scan --task='.(int)$taskId.' >/dev/null 2>&1 & echo $!',$output,$code);
        $pid=isset($output[0])?(int)$output[0]:0;
        if($code!==0||$pid<=0)return false;
        usleep(120000);
        $row=Db::name('ipa_scan_task')->where('id',(int)$taskId)->find();
        return $row&&in_array($row['state'],['queued','running','success','failed'],true);
    }

    protected static function cliPhpBinary()
    {
        $candidates=['/usr/bin/php','/usr/local/bin/php'];
        if(defined('PHP_BINARY')&&PHP_BINARY)$candidates[]=(string)PHP_BINARY;
        foreach(array_unique($candidates) as $candidate){if(!is_file($candidate)||!is_executable($candidate))continue;$base=strtolower(basename($candidate));if(strpos($base,'fpm')!==false||strpos($base,'cgi')!==false)continue;return $candidate;}
        return '';
    }

    protected static function failTask($taskId,$code,$message){Db::name('ipa_scan_task')->where('id',(int)$taskId)->update(['state'=>'failed','stage'=>'failed','error_code'=>(string)$code,'error_message'=>mb_substr((string)$message,0,2000,'UTF-8'),'heartbeat_at'=>time(),'finished_at'=>time(),'updatetime'=>time()]);}
}
