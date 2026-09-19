<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\IpaMysqlSourceService;

class IpaMysqlSource extends Backend
{
    protected $layout='default';

    public function index()
    {
        return $this->view->fetch();
    }

    public function sourceList()
    {
        try{$rows=IpaMysqlSourceService::all(false,false);}
        catch(\Exception $e){$this->error($e->getMessage());return;}
        $this->success('',null,['rows'=>$rows,'total'=>count($rows)]);
    }

    public function sourceSave()
    {
        if(!$this->request->isPost()){$this->error('Method not allowed');return;}
        try{$id=IpaMysqlSourceService::save($this->request->post(),(int)$this->auth->id);}
        catch(\Exception $e){$this->error($e->getMessage());return;}
        $this->success('MySQL 软件源已保存',null,['id'=>$id]);
    }

    public function sourceTest()
    {
        if(!$this->request->isPost()){$this->error('Method not allowed');return;}
        try{$result=IpaMysqlSourceService::test((int)$this->request->post('id/d',0));}
        catch(\Exception $e){$this->error($e->getMessage());return;}
        $this->success('MySQL 连接正常',null,$result);
    }

    public function sourceDelete()
    {
        if(!$this->request->isPost()){$this->error('Method not allowed');return;}
        try{$affected=IpaMysqlSourceService::delete((int)$this->request->post('id/d',0));}
        catch(\Exception $e){$this->error($e->getMessage());return;}
        $this->success($affected?'软件源配置已删除':'软件源不存在');
    }

    // Native FastAdmin snake_case actions used by auth_rule and generated URLs.
    public function source_list(){return $this->sourceList();}
    public function source_save(){return $this->sourceSave();}
    public function source_test(){return $this->sourceTest();}
    public function source_delete(){return $this->sourceDelete();}
}
