<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\BlacklistPolicy;
use app\common\library\AuthorizationEventLog;
use app\common\library\AuthorizationSchema;
use think\Db;

/**
 * @icon fa fa-circle-o
 */
class Monitor extends Backend
{
    /**
     * @var \app\admin\model\Monitor
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Monitor;
    }

    /**
     * 将监控记录移动到永久黑名单。
     * 保持历史行为：每次操作仍新增一条 fa_black 记录，再删除对应 monitor。
     */
    public function black()
    {
        $id = $this->request->request('ids');
        if ($id === null || $id === '') {
            $this->error(__('Invalid parameters'));
        }

        $res = Db::table('fa_monitor')->where(['id' => $id])->find();
        if (!$res || empty($res['udid'])) {
            $this->error(__('No Results were found'));
        }

        AuthorizationSchema::ensure();
        Db::startTrans();
        try {
            $inserted = Db::table('fa_black')->insert(BlacklistPolicy::insertData($res['udid']));
            if ($inserted !== 1) {
                throw new \RuntimeException('黑名单添加失败');
            }
            Db::table('fa_monitor')->where(['id' => $id])->delete();
            if (!AuthorizationEventLog::record('blacklist_add', [
                'udid' => $res['udid'],
                'ip' => $this->request->ip(),
                'detail' => '异常监控转入黑名单',
            ])) {
                throw new \RuntimeException('授权事件写入失败');
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }

        $this->success();
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $udid = isset($params['udid']) ? trim($params['udid']) : '';
                Db::table('fa_monitor')->insert([
                    'udid' => $udid,
                    'addtime' => time(),
                ]);
                $this->success();
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return parent::add();
    }
}
