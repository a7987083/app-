<?php
namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\IpaGovernanceLifecycleService;
use RuntimeException;

class IpaLifecycle extends Backend
{
    protected $noNeedRight=[];

    public function ignoredList()
    {
        try{
            $data=['stats'=>IpaGovernanceLifecycleService::stats(),'rows'=>IpaGovernanceLifecycleService::listIgnored((int)$this->request->get('limit/d',100))];
        }catch(\Exception $e){
            $this->error($e->getMessage());
        }
        $this->success('',null,$data);
    }

    public function ignored_list()
    {
        return $this->ignoredList();
    }

    public function ignoreBatch()
    {
        if(!$this->request->isPost())$this->error('Method not allowed');
        try{
            $ids=json_decode((string)$this->request->post('issue_ids_json','[]'),true);
            if(!is_array($ids))throw new RuntimeException('issue_ids_json 无效');
            $days=(int)$this->request->post('days/d',30);
            $result=IpaGovernanceLifecycleService::ignoreBatch($ids,$days,(int)$this->auth->id);
        }catch(\Exception $e){
            $this->error($e->getMessage());
        }
        $this->success('批量忽略完成',null,$result);
    }

    public function ignore_batch()
    {
        return $this->ignoreBatch();
    }

    public function unignoreBatch()
    {
        if(!$this->request->isPost())$this->error('Method not allowed');
        try{
            $ids=json_decode((string)$this->request->post('issue_ids_json','[]'),true);
            if(!is_array($ids))throw new RuntimeException('issue_ids_json 无效');
            $result=IpaGovernanceLifecycleService::unignoreBatch($ids,(int)$this->auth->id);
        }catch(\Exception $e){
            $this->error($e->getMessage());
        }
        $this->success('已恢复忽略项',null,$result);
    }

    public function unignore_batch()
    {
        return $this->unignoreBatch();
    }

    public function sweepExpired()
    {
        if(!$this->request->isPost())$this->error('Method not allowed');
        try{
            $result=IpaGovernanceLifecycleService::sweepExpired((int)$this->auth->id);
        }catch(\Exception $e){
            $this->error($e->getMessage());
        }
        $this->success('忽略到期扫描完成',null,$result);
    }

    public function sweep_expired()
    {
        return $this->sweepExpired();
    }
}
