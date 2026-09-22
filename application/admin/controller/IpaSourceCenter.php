<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\Ipa\IpaSoftwareSourceService;

class IpaSourceCenter extends Backend
{
    protected $noNeedRight = [];

    public function index()
    {
        return $this->view->fetch();
    }

    public function listSources()
    {
        return json(['total'=>count(IpaSoftwareSourceService::listSources()),'rows'=>IpaSoftwareSourceService::listSources()]);
    }

    public function saveSource()
    {
        if (!$this->request->isPost()) $this->error('仅支持 POST');
        try {
            $id=IpaSoftwareSourceService::save($this->request->post());
            $this->success('软件源已保存',null,['id'=>$id]);
        } catch (\Exception $e) { $this->error($e->getMessage()); }
    }

    public function testSource()
    {
        if (!$this->request->isPost()) $this->error('仅支持 POST');
        try {
            $data=IpaSoftwareSourceService::test((int)$this->request->post('id',0));
            $this->success('连接成功，共 '.$data['rows'].' 条记录',null,$data);
        } catch (\Exception $e) { $this->error('连接失败：'.$e->getMessage()); }
    }

    public function deleteSource()
    {
        if (!$this->request->isPost()) $this->error('仅支持 POST');
        try {
            IpaSoftwareSourceService::delete((int)$this->request->post('id',0));
            $this->success('软件源已删除');
        } catch (\Exception $e) { $this->error($e->getMessage()); }
    }
}
