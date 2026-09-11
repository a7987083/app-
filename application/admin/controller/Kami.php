<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\CardCodeGenerator;
use app\common\library\AuthorizationSchema;
use think\Db;

class Kami extends Backend
{
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        AuthorizationSchema::ensure();
        $this->model = new \app\admin\model\Kami;
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $count = isset($params['kami']) ? intval($params['kami']) : 0;
                $prefix = isset($params['udid']) ? trim($params['udid']) : '';
                $type = isset($params['Kmyp']) ? intval($params['Kmyp']) : 0;
                $transferQuota = isset($params['transfer_count']) ? intval($params['transfer_count']) : 100;
                if ($count <= 0) {
                    $this->error('数量需大于0');
                }
                if (!in_array($type, [1, 2, 3, 4, 5], true)) {
                    $this->error('请选择有效的卡密类型');
                }
                if ($transferQuota < 0 || $transferQuota > 1000000) {
                    $this->error('换绑次数需在0到1000000之间');
                }

                try {
                    $codes = CardCodeGenerator::generateUniqueBatch(
                        $count,
                        $prefix,
                        function (array $candidates) {
                            return Db::table('fa_kami')
                                ->where('kami', 'in', $candidates)
                                ->column('kami');
                        }
                    );
                } catch (\Exception $e) {
                    $this->error($e->getMessage());
                    return;
                }

                $createdAt = time();
                $html = '<br>';
                Db::startTrans();
                try {
                    foreach ($codes as $code) {
                        $inserted = Db::table('fa_kami')->insert([
                            'kami' => $code,
                            'udid' => '',
                            'kmyp' => $type,
                            'addtime' => $createdAt,
                            'usetime' => 0,
                            'endtime' => 0,
                            'transfer_count' => $transferQuota,
                        ]);
                        if ($inserted !== 1) {
                            throw new \RuntimeException('卡密写入失败');
                        }
                        $html .= $code . '<br>';
                    }
                    Db::table('fa_kmstr')->where('id', 1)->update(['kmstr' => $html]);
                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                $this->success();
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $stlst = Db::table('fa_kmstr')->where('id', 1)->find();
        $this->view->assign("strLst", $stlst ? $stlst['kmstr'] : '');
        return parent::add();
    }

    /**
     * 卡密换绑次数表示“剩余次数”。管理员编辑一个正在生效的授权时，
     * 同一 UDID 当前有效的叠加卡同步为相同额度，确保补次数立即生效。
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }

        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if ($params) {
                $params = $this->preExcludeFields($params);
                $quotaChanged = array_key_exists('transfer_count', $params);
                $quota = $quotaChanged ? intval($params['transfer_count']) : null;
                if ($quotaChanged && ($quota < 0 || $quota > 1000000)) {
                    $this->error('换绑次数需在0到1000000之间');
                }
                if ($quotaChanged) {
                    $params['transfer_count'] = $quota;
                }

                Db::startTrans();
                try {
                    $result = $row->allowField(true)->save($params);
                    if ($quotaChanged) {
                        $udid = trim((string)$row['udid']);
                        $now = time();
                        if ($udid !== '' && (int)$row['jh'] === 1 && (int)$row['endtime'] > $now) {
                            Db::table('fa_kami')
                                ->where('udid', $udid)
                                ->where('jh', 1)
                                ->where('endtime', '>', $now)
                                ->update(['transfer_count' => $quota]);
                        }
                    }
                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                    return;
                }
                if ($result !== false) {
                    $this->success();
                }
                $this->error(__('No rows were updated'));
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        $this->view->assign('row', $row);
        return $this->view->fetch();
    }
}
