<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\IpaScanService;
use app\common\library\IpaParserService;
use app\common\library\IpaSourceConfig;
use think\Db;
use RuntimeException;

class IpaCenter extends Backend
{
    protected $noNeedRight=[];
    protected $layout='default';
    public function index(){return $this->view->fetch();}
    public function metadata(){return $this->view->fetch();}
    public function binding(){return $this->view->fetch();}
    public function governance(){return $this->view->fetch();}
    public function task(){return $this->view->fetch();}
    public function writeback(){return $this->view->fetch();}
    public function setting(){$source=IpaSourceConfig::first(false);$this->view->assign('source',$source?:[]);return $this->view->fetch();}

    public function taskList()
    {
        $rows=Db::name('ipa_scan_task')->order('id','desc')->limit(50)->select();
        foreach((array)$rows as &$row){$row['cursor']=json_decode(isset($row['cursor_json'])?$row['cursor_json']:'',true);unset($row['cursor_json']);$row['started_at_text']=!empty($row['started_at'])?date('Y-m-d H:i:s',$row['started_at']):'';$row['finished_at_text']=!empty($row['finished_at'])?date('Y-m-d H:i:s',$row['finished_at']):'';}unset($row);
        $this->success('',null,['rows'=>$rows]);
    }

    public function scanStart()
    {
        if(!$this->request->isPost())$this->error('Method not allowed');$source=IpaSourceConfig::first(false);if(!$source)$this->error('请先配置 IPA 网络源');
        try{$taskId=IpaScanService::createTask((int)$source['id'],'manual',(int)$this->auth->id,(bool)$this->request->post('refresh/d',0));$spawned=IpaScanService::spawn($taskId);$this->success('扫描任务已创建',null,['task_id'=>$taskId,'spawned'=>$spawned,'fallback'=>$spawned?'':'后台启动不可用时可执行 php think ipa:scan --pending']);}catch(\Exception $e){$this->error($e->getMessage());}
    }

    public function metadataList()
    {
        $state=trim((string)$this->request->get('state',''));$keyword=trim((string)$this->request->get('q',''));$page=max(1,(int)$this->request->get('page/d',1));$limit=max(10,min(100,(int)$this->request->get('limit/d',50)));$query=Db::name('ipa_metadata');
        if(in_array($state,['pending','success','failed'],true))$query->where('parse_state',$state);
        if($keyword!==''){$query->where(function($q)use($keyword){$like='%'.$keyword.'%';$q->where('file_name','like',$like)->whereOr('bundle_id','like',$like)->whereOr('package_name','like',$like)->whereOr('remote_path','like',$like);});}
        $countQuery=clone $query;$total=(int)$countQuery->count();$rows=$query->order('id','desc')->page($page,$limit)->select();
        foreach((array)$rows as &$row){$normalized=json_decode(isset($row['normalized_metadata_json'])?$row['normalized_metadata_json']:'',true);$normalized=is_array($normalized)?$normalized:[];$parser=isset($normalized['_parser'])&&is_array($normalized['_parser'])?$normalized['_parser']:[];$row['architectures']=isset($normalized['architectures'])&&is_array($normalized['architectures'])?$normalized['architectures']:[];$row['primary_icon']=isset($normalized['primary_icon'])&&is_array($normalized['primary_icon'])?$normalized['primary_icon']:null;$row['range_bytes']=isset($parser['range_bytes'])?(int)$parser['range_bytes']:0;$row['range_requests']=isset($parser['range_requests'])?(int)$parser['range_requests']:0;$row['parsed_at_text']=!empty($row['parsed_at'])?date('Y-m-d H:i:s',$row['parsed_at']):'';unset($row['raw_metadata_json'],$row['normalized_metadata_json'],$row['confidence_json']);}unset($row);
        $this->success('',null,['rows'=>$rows,'total'=>$total,'page'=>$page,'limit'=>$limit]);
    }

    public function parseStart()
    {
        if(!$this->request->isPost())$this->error('Method not allowed');$taskId=(int)$this->request->post('task_id/d',0);$limit=(int)$this->request->post('limit/d',0);$spawned=IpaParserService::spawn($taskId,$limit);$this->success('解析任务已启动',null,['spawned'=>$spawned,'fallback'=>$spawned?'':'后台启动不可用时可执行 php think ipa:parse']);
    }

    public function sourceSave()
    {
        if(!$this->request->isPost())$this->error('Method not allowed');try{$id=IpaSourceConfig::save($this->request->post(),(int)$this->auth->id);$this->success('IPA 网络源已保存',null,['id'=>$id]);}catch(\Exception $e){$this->error($e->getMessage());}
    }

    public function sourceTest()
    {
        if(!$this->request->isPost())$this->error('Method not allowed');try{$source=IpaSourceConfig::first(true);if(!$source)throw new RuntimeException('请先保存 IPA 网络源');$client=IpaSourceConfig::clientFromRow($source);$health=$client->health($source['scan_path']);Db::name('ipa_source')->where('id',(int)$source['id'])->update(['last_health'=>'ok','last_checked_at'=>time(),'updatetime'=>time()]);$this->success('OpenList 连接正常',null,$health);}catch(\Exception $e){$source=IpaSourceConfig::first(false);if($source)Db::name('ipa_source')->where('id',(int)$source['id'])->update(['last_health'=>'failed','last_checked_at'=>time(),'updatetime'=>time()]);$this->error($e->getMessage());}
    }
}
