<?php

namespace app\common\library\Ipa;

use think\Db;

class IpaScanService
{
    protected static $jobContext=0;

    public static function createJob($sourceId,$mode='incremental')
    {
        $sourceId=(int)$sourceId;
        $mode=in_array($mode,['incremental','full'],true)?$mode:'incremental';

        // Production safety: verify the source first, then make sure the scan worker can be
        // started before inserting a pending job. If Worker launch fails (PHP/open_basedir/
        // proc_open/etc.), the source is not left permanently locked by an orphan pending job.
        $enabledSource=Db::name('ipa_source')->where('id',$sourceId)->where('enabled',1)->find();
        if(!$enabledSource)throw new \InvalidArgumentException('OpenList 数据源不存在或已停用');
        IpaWorkerLauncher::ensureScanWorker();

        $now=time();
        Db::startTrans();
        try{
            $source=Db::name('ipa_source')->where('id',$sourceId)->where('enabled',1)->lock(true)->find();
            if(!$source)throw new \InvalidArgumentException('OpenList 数据源不存在或已停用');

            $activeJobIds=Db::name('ipa_scan_job')
                ->where('source_id',(int)$source['id'])
                ->where('status','in',['pending','running'])
                ->column('id');
            if($activeJobIds){
                if($mode!=='full')throw new \RuntimeException('该数据源已有扫描任务正在运行');
                Db::name('ipa_scan_job')->where('id','in',$activeJobIds)->update([
                    'status'=>'cancelled',
                    'finished_at'=>$now,
                    'updated_at'=>$now,
                ]);
                Db::name('ipa_scan_item')->where('job_id','in',$activeJobIds)->where('status','in',['pending','processing'])->update([
                    'status'=>'cancelled',
                    'worker_id'=>'',
                    'locked_at'=>0,
                    'updated_at'=>$now,
                ]);
            }

            $jobId=Db::name('ipa_scan_job')->insertGetId(['source_id'=>(int)$source['id'],'mode'=>$mode,'status'=>'pending','root_path'=>$source['root_path'],'created_at'=>$now,'updated_at'=>$now]);
            self::enqueueItem($jobId,(int)$source['id'],'directory',$source['root_path'],$now);Db::commit();return $jobId;
        }catch(\Exception $e){Db::rollback();throw $e;}
    }

    public static function claimOne($workerId)
    {
        $now=time();Db::startTrans();
        try{
            Db::name('ipa_scan_item')->where('status','processing')->where('locked_at','<',$now-300)->where('retry_count','<',5)->update(['status'=>'pending','worker_id'=>'','locked_at'=>0,'available_at'=>$now,'updated_at'=>$now]);
            $item=Db::name('ipa_scan_item')->where('status','pending')->where('available_at','<=',$now)->order('id asc')->lock(true)->find();
            if(!$item){Db::commit();return null;}
            $job=Db::name('ipa_scan_job')->where('id',(int)$item['job_id'])->find();
            if(!$job||in_array((string)$job['status'],['cancelled','completed','completed_with_errors'],true)){Db::name('ipa_scan_item')->where('id',$item['id'])->update(['status'=>'cancelled','worker_id'=>'','locked_at'=>0,'updated_at'=>$now]);Db::commit();return null;}
            Db::name('ipa_scan_item')->where('id',$item['id'])->update(['status'=>'processing','worker_id'=>$workerId,'locked_at'=>$now,'updated_at'=>$now]);
            Db::name('ipa_scan_job')->where('id',$item['job_id'])->where('status','pending')->update(['status'=>'running','worker_id'=>$workerId,'started_at'=>$now,'updated_at'=>$now]);
            Db::commit();$item['status']='processing';$item['worker_id']=$workerId;return $item;
        }catch(\Exception $e){Db::rollback();throw $e;}
    }

    public static function processItem(array $item,$tokenResolver=null)
    {
        self::assertJobActive((int)$item['job_id']);
        $source=Db::name('ipa_source')->where('id',(int)$item['source_id'])->find();
        if(!$source||!(int)$source['enabled'])throw new \RuntimeException('OpenList 数据源不可用');
        $token='';
        if(is_callable($tokenResolver)){
            $token=(string)call_user_func($tokenResolver,$source);
        }elseif(!empty($source['token_ciphertext'])){
            throw new \RuntimeException('Encrypted OpenList token requires a token resolver');
        }
        $client=new OpenListClient($source['base_url'],$token,$source['request_timeout']);
        self::assertJobActive((int)$item['job_id']);
        if($item['item_type']==='directory')self::processDirectory($client,$source,$item);else self::touchAssetFromFile($client,$source,$item['path']);
        self::assertJobActive((int)$item['job_id']);
        self::markDone($item);
    }

    protected static function processDirectory(OpenListClient $client,array $source,array $item)
    {
        try{
            self::assertJobActive((int)$item['job_id']);
            $data=$client->listDirectory($item['path'],1,0,false);
            self::assertJobActive((int)$item['job_id']);
            self::consumeDirectoryRows($source,$item,isset($data['content'])&&is_array($data['content'])?$data['content']:[]);
            return;
        }catch(\RuntimeException $e){
            if($e->getMessage()==='扫描任务已取消')throw $e;
        }catch(\Exception $e){
            // Some OpenList versions/drivers do not accept per_page=0. Fall back to configured pagination.
        }
        $page=1;$pageSize=max(20,min(1000,(int)$source['scan_page_size']));
        do{
            self::assertJobActive((int)$item['job_id']);
            $data=$client->listDirectory($item['path'], $page, $pageSize, false);
            self::assertJobActive((int)$item['job_id']);
            $rows=isset($data['content'])&&is_array($data['content'])?$data['content']:[];
            self::consumeDirectoryRows($source,$item,$rows);
            $total=isset($data['total'])?(int)$data['total']:count($rows);$page++;
        }while(!empty($rows)&&(($page-1)*$pageSize)<$total);
    }

    protected static function consumeDirectoryRows(array $source,array $item,array $rows)
    {
        $index=0;
        foreach($rows as $row){
            if(($index++ % 50)===0)self::assertJobActive((int)$item['job_id']);
            if(empty($row['name']))continue;
            if(!Db::name('ipa_source')->where('id',(int)$source['id'])->where('enabled',1)->find())throw new \RuntimeException('数据源已停止，取消当前扫描');
            $path=rtrim($item['path'],'/').'/'.ltrim($row['name'],'/');
            if(!empty($row['is_dir'])){self::enqueueItem($item['job_id'],$item['source_id'],'directory',$path);continue;}
            if(strtolower(substr($row['name'],-4))!=='.ipa')continue;self::upsertAsset($source,$path,$row);
        }
        self::assertJobActive((int)$item['job_id']);
    }

    protected static function touchAssetFromFile(OpenListClient $client,array $source,$path){$data=$client->getFile($path);self::upsertAsset($source,$path,$data);}

    protected static function upsertAsset(array $source,$path,array $row)
    {
        if(!Db::name('ipa_source')->where('id',(int)$source['id'])->where('enabled',1)->find())throw new \RuntimeException('数据源已停止，拒绝继续写入扫描结果');
        $now=time();$hash=hash('sha256',(string)$path);$existing=Db::name('ipa_asset')->where('source_id',(int)$source['id'])->where('path_hash',$hash)->find();
        $mtime=0;if(!empty($row['modified'])){$p=strtotime($row['modified']);if($p!==false)$mtime=$p;}$size=isset($row['size'])?(int)$row['size']:0;
        $values=['path'=>(string)$path,'name'=>basename((string)$path),'size_bytes'=>$size,'modified_at'=>$mtime,'last_seen_at'=>$now,'updated_at'=>$now];
        if($existing){$changed=(int)$existing['size_bytes']!==$size||($mtime>0&&(int)$existing['modified_at']!==$mtime);if($changed||(string)$existing['status']==='missing'){$values['status']='discovered';$values['parsed_at']=0;$values['last_error']=null;$values['raw_url']=null;Db::name('ipa_compare_result')->where('asset_id',(int)$existing['id'])->delete();}Db::name('ipa_asset')->where('id',$existing['id'])->update($values);return (int)$existing['id'];}
        $values['source_id']=(int)$source['id'];$values['path_hash']=$hash;$values['status']='discovered';$values['created_at']=$now;$id=Db::name('ipa_asset')->insertGetId($values);$jobId=self::currentJobId();if($jobId>0)Db::name('ipa_scan_job')->where('id',$jobId)->setInc('discovered_count');return $id;
    }

    public static function setJobContext($jobId){self::$jobContext=(int)$jobId;}
    protected static function currentJobId(){return self::$jobContext;}

    public static function failItem(array $item,\Exception $e)
    {
        $job=Db::name('ipa_scan_job')->where('id',(int)$item['job_id'])->find();if($job&&(string)$job['status']==='cancelled')return;
        $now=time();$retry=(int)$item['retry_count']+1;$terminal=$retry>=5;Db::name('ipa_scan_item')->where('id',$item['id'])->update(['status'=>$terminal?'failed':'pending','retry_count'=>$retry,'available_at'=>$terminal?0:$now+min(300,(int)pow(2,$retry)*5),'worker_id'=>'','locked_at'=>0,'last_error'=>substr($e->getMessage(),0,2000),'updated_at'=>$now]);if($terminal)Db::name('ipa_scan_job')->where('id',$item['job_id'])->setInc('failed_count');self::reconcileJob((int)$item['job_id']);
    }

    protected static function markDone(array $item)
    {
        $job=Db::name('ipa_scan_job')->where('id',(int)$item['job_id'])->find();if(!$job||(string)$job['status']==='cancelled')return;$now=time();Db::name('ipa_scan_item')->where('id',$item['id'])->update(['status'=>'done','worker_id'=>'','locked_at'=>0,'updated_at'=>$now]);Db::name('ipa_scan_job')->where('id',$item['job_id'])->setInc('processed_count');self::reconcileJob((int)$item['job_id']);
    }

    protected static function reconcileJob($jobId)
    {
        $job=Db::name('ipa_scan_job')->where('id',(int)$jobId)->find();if(!$job||in_array((string)$job['status'],['cancelled','completed','completed_with_errors'],true))return;
        if((int)Db::name('ipa_scan_item')->where('job_id',$jobId)->where('status','in',['pending','processing'])->count()>0)return;
        $failed=(int)Db::name('ipa_scan_item')->where('job_id',$jobId)->where('status','failed')->count();$now=time();
        if((string)$job['mode']==='full'){$cutoff=!empty($job['started_at'])?(int)$job['started_at']:(int)$job['created_at'];Db::name('ipa_asset')->where('source_id',(int)$job['source_id'])->where('last_seen_at','<',$cutoff)->where('status','<>','missing')->update(['status'=>'missing','updated_at'=>$now]);}
        Db::name('ipa_scan_job')->where('id',$jobId)->update(['status'=>$failed>0?'completed_with_errors':'completed','finished_at'=>$now,'updated_at'=>$now]);
    }

    protected static function assertJobActive($jobId)
    {
        $status=(string)Db::name('ipa_scan_job')->where('id',(int)$jobId)->value('status');
        if($status===''||in_array($status,['cancelled','completed','completed_with_errors'],true))throw new \RuntimeException('扫描任务已取消');
    }

    protected static function enqueueItem($jobId,$sourceId,$type,$path,$availableAt=null)
    {
        $job=Db::name('ipa_scan_job')->where('id',(int)$jobId)->find();if($job&&(string)$job['status']==='cancelled')return;
        $path='/'.ltrim(preg_replace('#/+#','/',(string)$path),'/');$now=time();$data=['job_id'=>(int)$jobId,'source_id'=>(int)$sourceId,'item_type'=>$type,'path_hash'=>hash('sha256',$path),'path'=>$path,'status'=>'pending','available_at'=>$availableAt===null?$now:(int)$availableAt,'created_at'=>$now,'updated_at'=>$now];
        try{Db::name('ipa_scan_item')->insert($data);}catch(\think\exception\PDOException $e){if(stripos($e->getMessage(),'Duplicate entry')===false)throw $e;}
    }
}
