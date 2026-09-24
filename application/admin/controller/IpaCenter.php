<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\Ipa\IpaCompareService;
use app\common\library\Ipa\IpaOpsSettings;
use app\common\library\Ipa\IpaScanService;
use app\common\library\Ipa\IpaWorkerLauncher;
use app\common\library\Ipa\IpaWritebackService;
use app\common\library\Ipa\SecretBox;
use app\common\library\Ipa\WorkerState;
use think\Db;

class IpaCenter extends Backend
{
    protected $noNeedRight = [];

    public function index()
    {
        $settings=IpaOpsSettings::all();
        $this->view->assign('parseSettings',$settings);
        $this->view->assign('summary',[
            'sources'=>(int)Db::name('ipa_source')->count(),
            'assets'=>(int)Db::name('ipa_asset')->count(),
            'pending'=>(int)Db::name('ipa_scan_item')->where('status','pending')->count(),
            'failed'=>(int)Db::name('ipa_scan_item')->where('status','failed')->count(),
            'parse_pending'=>(int)Db::name('ipa_asset')->where('status','discovered')->count(),
            'parse_failed'=>(int)Db::name('ipa_asset')->where('status','parse_failed')->count(),
            'dylibs'=>(int)Db::name('dylib')->count(),
            'verify24h'=>(int)Db::name('dylib_verify_log')->where('created_at','>=',time()-86400)->count(),
        ]);
        $this->view->assign('sources',Db::name('ipa_source')->field('id,name,base_url,root_path,enabled,scan_page_size,request_timeout,updated_at')->order('id desc')->select());
        return $this->view->fetch();
    }

    public function assets()
    {
        $offset=max(0,(int)$this->request->get('offset',0));
        $limit=max(20,min(500,(int)$this->request->get('limit',100)));
        $search=trim((string)$this->request->get('search',''));
        $query=Db::name('ipa_asset');
        if ($search!=='') $query->where(function($q) use($search){$q->whereLike('name','%'.$search.'%')->whereOr('bundle_id','like','%'.$search.'%');});
        $total=(clone $query)->count();
        $rows=$query->field('id,source_id,name,path,size_bytes,status,bundle_id,app_name,app_version,build_version,last_error,last_seen_at,parsed_at,updated_at')->order('id desc')->limit($offset,$limit)->select();
        $ids=[];foreach($rows as $r)$ids[]=(int)$r['id'];
        $summaries=[];try{$summaries=IpaCompareService::summaryForAssets($ids);}catch(\Exception $e){}
        foreach($rows as &$row){$row['compare_summary']=isset($summaries[(int)$row['id']])?$summaries[(int)$row['id']]:['sources'=>0,'anomalies'=>0,'unmatched'=>0,'errors'=>0];}
        unset($row);
        return json(['total'=>(int)$total,'rows'=>$rows]);
    }

    public function jobs()
    {
        $offset=max(0,(int)$this->request->get('offset',0));$limit=max(20,min(200,(int)$this->request->get('limit',50)));
        $total=Db::name('ipa_scan_job')->count();
        $rows=Db::name('ipa_scan_job')->field('id,source_id,mode,status,root_path,discovered_count,processed_count,failed_count,worker_id,started_at,finished_at,created_at')->order('id desc')->limit($offset,$limit)->select();
        return json(['total'=>(int)$total,'rows'=>$rows]);
    }

    public function saveParseSettings()
    {
        if(!$this->request->isPost())$this->error('仅支持 POST');
        try{$cfg=IpaOpsSettings::save($this->request->post());}
        catch(\Throwable $e){$this->error($this->errorMessage($e,'解析设置保存失败'));return;}
        $this->success('解析设置已保存',url('ipa_center/index'),$cfg);
    }

    public function pauseParse()
    {
        if(!$this->request->isPost())$this->error('仅支持 POST');
        try{
            IpaOpsSettings::save(['parse_enabled'=>0]);
            $reclaimed=$this->reclaimOrphanedParsing();
            $message='自动解析已暂停；不会再领取新任务';
            if($reclaimed>0)$message.='；已回收 '.$reclaimed.' 个无存活 Worker 的解析任务';
            else $message.='；若当前 Parse Worker 仍存活，正在处理的 1 个 IPA 会允许完成';
        }catch(\Throwable $e){$this->error($this->errorMessage($e,'暂停解析失败'));return;}
        $this->success($message);
    }

    public function resumeParse()
    {
        if(!$this->request->isPost())$this->error('仅支持 POST');
        try{IpaOpsSettings::save(['parse_enabled'=>1]);}
        catch(\Throwable $e){$this->error($this->errorMessage($e,'恢复解析失败'));return;}
        $this->success('自动解析已恢复');
    }

    public function clearParseResults()
    {
        if(!$this->request->isPost())$this->error('仅支持 POST');
        $settings=IpaOpsSettings::all();
        if(!empty($settings['parse_enabled']))$this->error('请先暂停自动解析，再清空解析结果');
        $reclaimed=$this->reclaimOrphanedParsing();
        $parsing=(int)Db::name('ipa_asset')->where('status','parsing')->count();
        if($parsing>0)$this->error('仍有 '.$parsing.' 个 IPA 正由存活的 Parse Worker 解析；请等待当前任务结束后再清空');
        $now=time();$affected=0;
        Db::startTrans();
        try{
            while(true){
                $rows=Db::name('ipa_asset')->field('id')->where('status','in',['parsed','parse_failed'])->limit(500)->select();
                if(!$rows)break;
                $ids=[];foreach($rows as $row)$ids[]=(int)$row['id'];
                Db::name('ipa_compare_result')->where('asset_id','in',$ids)->delete();
                Db::name('ipa_binary')->where('asset_id','in',$ids)->delete();
                Db::name('ipa_asset')->where('id','in',$ids)->update([
                    'status'=>'discovered','bundle_id'=>'','app_name'=>'','app_version'=>'','build_version'=>'','minimum_os'=>'','sha256'=>'','last_error'=>null,'parsed_at'=>0,'updated_at'=>$now
                ]);
                $affected+=count($ids);
            }
            Db::name('ipa_parse_attempt')->delete(true);
            Db::commit();
        }catch(\Throwable $e){Db::rollback();$this->error($this->errorMessage($e,'清空解析结果失败'));return;}
        $message='已清空 '.$affected.' 个 IPA 的解析/比对结果，文件扫描记录和 fa_category 均未删除';
        if($reclaimed>0)$message.='；同时回收了 '.$reclaimed.' 个孤立解析任务';
        $this->success($message);
    }

    public function assetDetail()
    {
        $id=(int)$this->request->get('id',0);
        $asset=Db::name('ipa_asset')->where('id',$id)->find();
        if(!$asset)return json(['code'=>0,'msg'=>'IPA 不存在','data'=>null]);
        $compare=[];try{$compare=IpaCompareService::details($id);}catch(\Exception $e){}
        return json(['code'=>1,'msg'=>'ok','data'=>['asset'=>$asset,'compare'=>$compare]]);
    }

    public function compareAsset()
    {
        if(!$this->request->isPost())$this->error('仅支持 POST');
        try{$rows=IpaCompareService::refreshAsset((int)$this->request->post('asset_id',0));$this->success('数据库比对完成',null,['rows'=>$rows]);}catch(\Exception $e){$this->error($e->getMessage());}
    }

    public function applyCompareWriteback()
    {
        if(!$this->request->isPost())$this->error('仅支持 POST');
        $fields=$this->request->post('fields/a',[]);
        try{$r=IpaCompareService::applyWriteback((int)$this->request->post('compare_id',0),is_array($fields)?$fields:[]);$this->success('所选字段已写回数据库',null,$r);}catch(\Exception $e){$this->error($e->getMessage());}
    }

    public function categorySearch()
    {
        $q=trim((string)$this->request->get('q',''));$limit=max(1,min(50,(int)$this->request->get('limit',20)));
        $query=Db::name('category')->field('id,name,nickname,bt1a,bt2a,status')->where('pid',0);
        if($q!==''){if(ctype_digit($q)){$query->where(function($w)use($q){$w->where('id',(int)$q)->whereOr('name','like','%'.$q.'%');});}else{$query->where('name','like','%'.$q.'%');}}
        return json(['rows'=>$query->order('id desc')->limit($limit)->select()]);
    }

    public function writebackPreview()
    {
        try{$data=IpaWritebackService::preview((int)$this->request->request('asset_id',0),(int)$this->request->request('category_id',0));return json(['code'=>1,'msg'=>'ok','data'=>$data]);}catch(\Exception $e){return json(['code'=>0,'msg'=>$e->getMessage(),'data'=>null]);}
    }

    public function writebackApply()
    {
        if(!$this->request->isPost())$this->error('仅支持 POST');$fields=$this->request->post('fields/a',[]);
        try{$result=IpaWritebackService::apply((int)$this->request->post('asset_id',0),(int)$this->request->post('category_id',0),is_array($fields)?$fields:[],(int)$this->auth->id);$this->success('写回完成',null,$result);}catch(\Exception $e){$this->error($e->getMessage());}
    }

    public function saveSource()
    {
        if(!$this->request->isPost())$this->error('仅支持 POST');
        $id=(int)$this->request->post('id',0);$name=trim((string)$this->request->post('name',''));$baseUrl=rtrim(trim((string)$this->request->post('base_url','')),'/');$rootPath=trim((string)$this->request->post('root_path','/'));$token=trim((string)$this->request->post('token',''));
        if($name===''||!filter_var($baseUrl,FILTER_VALIDATE_URL))$this->error('数据源名称或 URL 无效');
        if(stripos($baseUrl,'https://')!==0&&stripos($baseUrl,'http://')!==0)$this->error('仅允许 HTTP/HTTPS OpenList URL');
        if($id>0&&!Db::name('ipa_source')->where('id',$id)->find())$this->error('OpenList 数据源不存在');
        $rootPath='/'.ltrim(preg_replace('#/+#','/',$rootPath),'/');$now=time();
        $data=['name'=>$name,'base_url'=>$baseUrl,'root_path'=>$rootPath,'enabled'=>(int)$this->request->post('enabled',1)?1:0,'scan_page_size'=>max(20,min(1000,(int)$this->request->post('scan_page_size',500))),'request_timeout'=>max(3,min(120,(int)$this->request->post('request_timeout',20))),'updated_at'=>$now];
        if($token!==''){try{$data['token_ciphertext']=SecretBox::encrypt($token);}catch(\Exception $e){$this->error($e->getMessage());}}
        if($id>0)Db::name('ipa_source')->where('id',$id)->update($data);else{$data['created_at']=$now;$id=Db::name('ipa_source')->insertGetId($data);} $this->success('已保存',url('ipa_center/index'),['id'=>(int)$id]);
    }

    public function deleteSource()
    {
        if(!$this->request->isPost())$this->error('仅支持 POST');$id=(int)$this->request->post('id',0);
        if(!Db::name('ipa_source')->where('id',$id)->find())$this->error('OpenList 数据源不存在');
        $active=(int)Db::name('ipa_scan_job')->where('source_id',$id)->where('status','in',['pending','running'])->count();
        $parsing=(int)Db::name('ipa_asset')->where('source_id',$id)->where('status','parsing')->count();
        if($active>0||$parsing>0)$this->error('当前数据源仍有活动任务；可使用“停止任务并删除”');
        $this->deleteSourceRows($id);$this->success('数据源已删除');
    }

    public function forceDeleteSource()
    {
        if(!$this->request->isPost())$this->error('仅支持 POST');$id=(int)$this->request->post('id',0);
        if(!Db::name('ipa_source')->where('id',$id)->find())$this->error('OpenList 数据源不存在');
        $now=time();
        Db::name('ipa_source')->where('id',$id)->update(['enabled'=>0,'updated_at'=>$now]);
        Db::name('ipa_scan_job')->where('source_id',$id)->where('status','in',['pending','running'])->update(['status'=>'cancelled','finished_at'=>$now,'updated_at'=>$now]);
        Db::name('ipa_scan_item')->where('source_id',$id)->where('status','in',['pending','processing'])->update(['status'=>'cancelled','worker_id'=>'','locked_at'=>0,'updated_at'=>$now]);
        $this->deleteSourceRows($id);$this->success('活动任务已停止，数据源已删除');
    }

    protected function deleteSourceRows($id)
    {
        Db::startTrans();try{
            while(true){$rows=Db::name('ipa_asset')->field('id')->where('source_id',$id)->limit(500)->select();if(!$rows)break;$ids=[];foreach($rows as $r)$ids[]=(int)$r['id'];Db::name('ipa_compare_result')->where('asset_id','in',$ids)->delete();Db::name('ipa_binary')->where('asset_id','in',$ids)->delete();Db::name('ipa_category_binding')->where('asset_id','in',$ids)->delete();Db::name('ipa_asset')->where('id','in',$ids)->delete();}
            Db::name('ipa_scan_item')->where('source_id',$id)->delete();Db::name('ipa_scan_job')->where('source_id',$id)->delete();Db::name('ipa_source')->where('id',$id)->delete();Db::commit();
        }catch(\Exception $e){Db::rollback();$this->error($e->getMessage());}
    }

    public function retryParse()
    {
        if(!$this->request->isPost())$this->error('仅支持 POST');$id=(int)$this->request->post('asset_id',0);$asset=Db::name('ipa_asset')->where('id',$id)->find();if(!$asset)$this->error('IPA 不存在');if((string)$asset['status']==='parsing')$this->error('IPA 正在解析');
        Db::name('ipa_asset')->where('id',$id)->update(['status'=>'discovered','last_error'=>null,'parsed_at'=>0,'updated_at'=>time()]);$this->success('IPA 已重新进入解析队列');
    }

    public function startScan()
    {
        if(!$this->request->isPost())$this->error('仅支持 POST');
        try{
            $jobId=IpaScanService::createJob((int)$this->request->post('source_id'),(string)$this->request->post('mode','incremental'));
            $worker=IpaWorkerLauncher::ensureScanWorker();
        }catch(\Throwable $e){$this->error($this->errorMessage($e,'扫描任务启动失败'));return;}
        $message=!empty($worker['started'])?'扫描任务已加入队列，扫描 Worker 已自动启动':'扫描任务已加入队列，扫描 Worker 正在运行';
        $this->success($message,null,['job_id'=>(int)$jobId,'worker'=>$worker]);
    }

    protected function reclaimOrphanedParsing()
    {
        $settings=IpaOpsSettings::all();
        $workers=[];
        try{$workers=WorkerState::snapshot($settings['worker_alive_seconds']);}catch(\Throwable $e){return 0;}
        $parse=isset($workers['parse'])?$workers['parse']:null;
        if($parse && !empty($parse['alive']))return 0;
        $now=time();
        return (int)Db::name('ipa_asset')->where('status','parsing')->update([
            'status'=>'discovered',
            'last_error'=>'Parse Worker 已离线，系统已回收孤立解析任务',
            'updated_at'=>$now,
        ]);
    }

    protected function errorMessage($e,$fallback)
    {
        $message=trim((string)$e->getMessage());
        return $message!==''?$message:$fallback.'（'.get_class($e).'）';
    }
}
