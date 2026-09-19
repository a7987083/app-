<?php
namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\IpaRangeMetricsService;
use app\common\library\IpaRetentionService;

class IpaProduction extends Backend
{
    protected $noNeedRight=[];

    public function metrics()
    {
        try {
            $days=(int)$this->request->get('days/d',7);
            $this->success('',null,IpaRangeMetricsService::summary($days));
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    public function retentionPreview()
    {
        if (!$this->request->isPost()) $this->error('Method not allowed');
        try {
            $days=(int)$this->request->post('days/d',IpaRetentionService::DEFAULT_DAYS);
            $this->success('Retention 预览已生成',null,IpaRetentionService::previewWithHash($days));
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    public function retentionApply()
    {
        if (!$this->request->isPost()) $this->error('Method not allowed');
        try {
            $days=(int)$this->request->post('days/d',IpaRetentionService::DEFAULT_DAYS);
            $hash=trim((string)$this->request->post('plan_hash',''));
            $this->success('Retention 清理完成',null,IpaRetentionService::apply($days,$hash));
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
