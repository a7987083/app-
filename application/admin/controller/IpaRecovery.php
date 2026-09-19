<?php
namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\IpaGovernanceRecoveryService;

class IpaRecovery extends Backend
{
    protected $noNeedRight=[];

    public function scanInterrupted()
    {
        if (!$this->request->isPost()) $this->error('Method not allowed');
        try {
            $seconds=max(60,min(86400,(int)$this->request->post('stale_seconds/d',IpaGovernanceRecoveryService::DEFAULT_STALE_SECONDS)));
            $this->success('中断操作扫描完成',null,IpaGovernanceRecoveryService::markInterrupted($seconds));
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    public function scan_interrupted()
    {
        return $this->scanInterrupted();
    }

    public function retry()
    {
        if (!$this->request->isPost()) $this->error('Method not allowed');
        try {
            $result=IpaGovernanceRecoveryService::retry(trim((string)$this->request->post('operation_id','')),(int)$this->auth->id);
            $this->success('恢复执行完成并通过验证',null,$result);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
