<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\IpaScanService;
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

    public function setting()
    {
        $source=IpaSourceConfig::first(false);
        $this->view->assign('source',$source?:[]);
        return $this->view->fetch();
    }

    public function taskList()
    {
        $rows=Db::name('ipa_scan_task')->order('id','desc')->limit(50)->select();
        foreach ((array)$rows as &$row) {
            $row['cursor']=json_decode(isset($row['cursor_json'])?$row['cursor_json']:'',true);
            unset($row['cursor_json']);
            $row['started_at_text']=!empty($row['started_at'])?date('Y-m-d H:i:s',$row['started_at']):'';
            $row['finished_at_text']=!empty($row['finished_at'])?date('Y-m-d H:i:s',$row['finished_at']):'';
        }
        unset($row);
        $this->success('',null,['rows'=>$rows]);
    }

    public function scanStart()
    {
        if (!$this->request->isPost()) $this->error('Method not allowed');
        $source=IpaSourceConfig::first(false);
        if (!$source) $this->error('请先配置 IPA 网络源');
        try {
            $taskId=IpaScanService::createTask((int)$source['id'],'manual',(int)$this->auth->id,(bool)$this->request->post('refresh/d',0));
            $spawned=IpaScanService::spawn($taskId);
            $this->success('扫描任务已创建',null,['task_id'=>$taskId,'spawned'=>$spawned,'fallback'=>$spawned?'':'后台启动不可用时可执行 php think ipa:scan --pending']);
        } catch (\Exception $e) { $this->error($e->getMessage()); }
    }

    public function sourceSave()
    {
        if (!$this->request->isPost()) $this->error('Method not allowed');
        try {
            $id=IpaSourceConfig::save($this->request->post(),(int)$this->auth->id);
            $this->success('IPA 网络源已保存',null,['id'=>$id]);
        } catch (\Exception $e) { $this->error($e->getMessage()); }
    }

    public function sourceTest()
    {
        if (!$this->request->isPost()) $this->error('Method not allowed');
        try {
            $source=IpaSourceConfig::first(true);
            if (!$source) throw new RuntimeException('请先保存 IPA 网络源');
            $client=IpaSourceConfig::clientFromRow($source);
            $health=$client->health($source['scan_path']);
            Db::name('ipa_source')->where('id',(int)$source['id'])->update(['last_health'=>'ok','last_checked_at'=>time(),'updatetime'=>time()]);
            $this->success('OpenList 连接正常',null,$health);
        } catch (\Exception $e) {
            $source=IpaSourceConfig::first(false);
            if ($source) Db::name('ipa_source')->where('id',(int)$source['id'])->update(['last_health'=>'failed','last_checked_at'=>time(),'updatetime'=>time()]);
            $this->error($e->getMessage());
        }
    }
}
