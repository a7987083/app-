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
                if ($count <= 0) {
                    $this->error('数量需大于0');
                }
                if (!in_array($type, [1, 2, 3, 4, 5], true)) {
                    $this->error('请选择有效的卡密类型');
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
                            'transfer_count' => 0,
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
}
