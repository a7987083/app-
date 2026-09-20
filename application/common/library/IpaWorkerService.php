<?php

namespace app\common\library;

use think\Db;
use RuntimeException;
use Throwable;

class IpaWorkerService
{
    const WORKER_NAME = 'ipa-main';
    const HEARTBEAT_TTL = 30;
    const JOB_STALE_SECONDS = 1800;
    protected static $inlineDispatchRegistered = false;

    public static function enqueueScan($taskId, $adminId = 0)
    {
        $taskId=(int)$taskId;
        if($taskId<=0)throw new RuntimeException('无效扫描任务');
        $row=self::enqueue('scan',$taskId,['task_id'=>$taskId],$adminId,'scan:'.$taskId);
        self::dispatchAfterResponseIfNeeded();
        return $row;
    }

    public static function enqueueParse($taskId = 0, $adminId = 0)
    {
        $taskId=(int)$taskId;
        $key='parse:'.time().':'.sprintf('%06d',mt_rand(0,999999)).':'.($adminId?:0);
        $row=self::enqueue('parse',$taskId,['task_id'=>$taskId,'limit'=>1],$adminId,$key);
        self::dispatchAfterResponseIfNeeded();
        return $row;
    }

    protected static function enqueue($type,$refId,array $payload,$adminId,$jobKey)
    {
        $now=time();
        try{
            $id=Db::name('ipa_worker_job')->insertGetId([
                'job_key'=>(string)$jobKey,'job_type'=>(string)$type,'ref_id'=>(int)$refId,
                'payload_json'=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'state'=>'queued','worker_token'=>'','claimed_at'=>0,'heartbeat_at'=>0,
                'error_code'=>'','error_message'=>'','created_by'=>(int)$adminId,
                'started_at'=>0,'finished_at'=>0,'createtime'=>$now,'updatetime'=>$now,
            ]);
        }catch(\Exception $e){
            if($type==='scan'){
                $row=Db::name('ipa_worker_job')->where('job_key',(string)$jobKey)->find();
                if($row)return $row;
            }
            throw $e;
        }
        return Db::name('ipa_worker_job')->where('id',(int)$id)->find();
    }

    /**
     * PHP equivalent of the old Node service's setImmediate(runQueuedTask):
     * return the HTTP response first, then consume one durable job inside the
     * same PHP-FPM process. If a persistent worker is healthy, it owns the job
     * instead and this fallback is not registered.
     */
    public static function dispatchAfterResponseIfNeeded()
    {
        if(self::$inlineDispatchRegistered)return 'inline';
        $status=self::workerStatus(self::WORKER_NAME);
        if(!empty($status['online']))return 'persistent';
        self::$inlineDispatchRegistered=true;
        register_shutdown_function(function(){
            ignore_user_abort(true);
            if(function_exists('set_time_limit'))@set_time_limit(0);
            if(function_exists('fastcgi_finish_request'))@fastcgi_finish_request();
            try{IpaWorkerService::runLoop(true,1,'ipa-web-inline',false);}
            catch(\Exception $e){}
            catch(Throwable $e){}
        });
        return 'inline';
    }

    public static function workerStatus($name=self::WORKER_NAME)
    {
        $row=Db::name('ipa_worker_state')->where('worker_name',(string)$name)->find();
        $now=time();
        $online=$row&&!empty($row['heartbeat_at'])&&($now-(int)$row['heartbeat_at'])<=self::HEARTBEAT_TTL;
        $queued=(int)Db::name('ipa_worker_job')->where('state','in',['queued','interrupted'])->count();
        return [
            'online'=>(bool)$online,
            'state'=>$online?(isset($row['state'])?$row['state']:'idle'):'offline',
            'heartbeat_at'=>$row?(int)$row['heartbeat_at']:0,
            'current_job_id'=>$row?(int)$row['current_job_id']:0,
            'current_job_type'=>$row?(string)$row['current_job_type']:'',
            'pid'=>$row?(int)$row['pid']:0,
            'queued_jobs'=>$queued,
        ];
    }

    public static function runLoop($once=false,$sleepSeconds=2,$name=self::WORKER_NAME,$includeSchedule=true)
    {
        $sleepSeconds=max(1,min(30,(int)$sleepSeconds));
        $token=self::newToken();
        self::registerWorker($name,$token);
        $processed=0;
        try{
            while(true){
                self::heartbeat($name,$token,0,'','idle');
                self::recoverStaleJobs();
                if($includeSchedule)self::enqueueDueScheduledScans();
                $job=self::claimOne($token);
                if($job){
                    self::heartbeat($name,$token,(int)$job['id'],(string)$job['job_type'],'running');
                    self::runClaimedJob($job,$token);
                    $processed++;
                    self::heartbeat($name,$token,0,'','idle');
                    if($once)break;
                    continue;
                }
                if($once)break;
                sleep($sleepSeconds);
            }
        }catch(\Exception $e){
            self::heartbeat($name,$token,0,'','failed');
            throw $e;
        }catch(Throwable $e){
            self::heartbeat($name,$token,0,'','failed');
            throw $e;
        }
        self::heartbeat($name,$token,0,'','idle');
        return $processed;
    }

    public static function enqueueDueScheduledScans()
    {
        $created=IpaScanService::createDueScheduledTasks();
        foreach((array)$created as $taskId)self::enqueueScan((int)$taskId,0);
        return $created;
    }

    public static function recoverStaleJobs($staleSeconds=self::JOB_STALE_SECONDS)
    {
        $cutoff=time()-max(300,(int)$staleSeconds);
        return (int)Db::name('ipa_worker_job')
            ->where('state','running')->where('heartbeat_at','>',0)->where('heartbeat_at','<',$cutoff)
            ->update(['state'=>'interrupted','worker_token'=>'','claimed_at'=>0,'heartbeat_at'=>0,'updatetime'=>time()]);
    }

    protected static function claimOne($token)
    {
        for($attempt=0;$attempt<20;$attempt++){
            $candidate=Db::name('ipa_worker_job')->where('state','in',['queued','interrupted'])->order('id','asc')->find();
            if(!$candidate)return null;
            $now=time();
            $affected=Db::name('ipa_worker_job')->where('id',(int)$candidate['id'])
                ->where('state','in',['queued','interrupted'])
                ->update(['state'=>'running','worker_token'=>(string)$token,'claimed_at'=>$now,'heartbeat_at'=>$now,'started_at'=>$candidate['started_at']?(int)$candidate['started_at']:$now,'error_code'=>'','error_message'=>'','updatetime'=>$now]);
            if((int)$affected!==1)continue;
            return Db::name('ipa_worker_job')->where('id',(int)$candidate['id'])->find();
        }
        return null;
    }

    protected static function runClaimedJob(array $job,$token)
    {
        $id=(int)$job['id'];
        try{
            self::touchJob($id,$token);
            if($job['job_type']==='scan'){
                $result=IpaScanService::runTask((int)$job['ref_id']);
                $payload=['task_id'=>(int)$job['ref_id'],'state'=>isset($result['state'])?$result['state']:''];
            }elseif($job['job_type']==='parse'){
                $payloadIn=json_decode(isset($job['payload_json'])?$job['payload_json']:'',true);$payloadIn=is_array($payloadIn)?$payloadIn:[];
                $taskId=isset($payloadIn['task_id'])?(int)$payloadIn['task_id']:(int)$job['ref_id'];
                $payload=IpaParserService::parseBatch($taskId,1);
            }else{
                throw new RuntimeException('未知 IPA Worker job_type: '.$job['job_type']);
            }
            Db::name('ipa_worker_job')->where('id',$id)->where('worker_token',(string)$token)->update([
                'state'=>'success','heartbeat_at'=>time(),'finished_at'=>time(),'payload_json'=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'error_code'=>'','error_message'=>'','updatetime'=>time(),
            ]);
            return $payload;
        }catch(\Exception $e){self::failJob($id,$token,$e->getMessage());return null;}
        catch(Throwable $e){self::failJob($id,$token,$e->getMessage());return null;}
    }

    protected static function failJob($id,$token,$message)
    {
        Db::name('ipa_worker_job')->where('id',(int)$id)->where('worker_token',(string)$token)->update([
            'state'=>'failed','heartbeat_at'=>time(),'finished_at'=>time(),'error_code'=>'worker_job_failed','error_message'=>mb_substr((string)$message,0,2000,'UTF-8'),'updatetime'=>time(),
        ]);
    }

    public static function touchJob($id,$token)
    {
        return (int)Db::name('ipa_worker_job')->where('id',(int)$id)->where('state','running')->where('worker_token',(string)$token)->update(['heartbeat_at'=>time(),'updatetime'=>time()]);
    }

    protected static function registerWorker($name,$token)
    {
        $now=time();$row=Db::name('ipa_worker_state')->where('worker_name',(string)$name)->find();
        $data=['worker_token'=>(string)$token,'pid'=>function_exists('getmypid')?(int)getmypid():0,'state'=>'idle','current_job_id'=>0,'current_job_type'=>'','started_at'=>$now,'heartbeat_at'=>$now,'updatetime'=>$now];
        if($row)Db::name('ipa_worker_state')->where('id',(int)$row['id'])->update($data);
        else{ $data['worker_name']=(string)$name;Db::name('ipa_worker_state')->insert($data); }
    }

    protected static function heartbeat($name,$token,$jobId,$jobType,$state)
    {
        $row=Db::name('ipa_worker_state')->where('worker_name',(string)$name)->find();
        $data=['worker_token'=>(string)$token,'pid'=>function_exists('getmypid')?(int)getmypid():0,'state'=>(string)$state,'current_job_id'=>(int)$jobId,'current_job_type'=>(string)$jobType,'heartbeat_at'=>time(),'updatetime'=>time()];
        if($row)Db::name('ipa_worker_state')->where('id',(int)$row['id'])->update($data);
        else{ $data['worker_name']=(string)$name;$data['started_at']=time();Db::name('ipa_worker_state')->insert($data); }
    }

    protected static function newToken()
    {
        if(function_exists('random_bytes'))return bin2hex(random_bytes(16));
        return hash('sha256',uniqid('',true).mt_rand());
    }
}
