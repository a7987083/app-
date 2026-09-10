<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\BlacklistPolicy;
use think\Db;

/**
 * 黑名单管理
 *
 * @icon fa fa-circle-o
 */
class Black extends Backend
{
    /**
     * @var \app\admin\model\Black
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Black;
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            $udid = $params && isset($params['udid']) ? trim($params['udid']) : '';
            if ($udid === '') {
                $this->error('UDID不能为空');
            }

            $endtime = 0;
            if (isset($params['endtime']) && trim((string)$params['endtime']) !== '') {
                $endtime = is_numeric($params['endtime']) ? (int)$params['endtime'] : strtotime($params['endtime']);
                if (!$endtime) {
                    $this->error('到期时间格式不正确');
                }
            }

            try {
                $result = Db::table('fa_black')->insert(BlacklistPolicy::insertData($udid, time(), $endtime));
                if ($result !== 1) {
                    $this->error('黑名单添加失败');
                }
                $this->success();
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
        }

        return parent::add();
    }
}
