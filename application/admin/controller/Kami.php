<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\Db;

/**
 * @icon fa fa-circle-o
 */
class Kami extends Backend
{
    /**
     * @var \app\admin\model\Kami
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
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

                $createdAt = time();
                $timeSeed = date('YmdHis', $createdAt);
                $html = '<br>';

                Db::startTrans();
                try {
                    for ($i = 1; $i <= $count; $i++) {
                        $offset = rand(1, 15);
                        $code = strtoupper($prefix . substr(md5($timeSeed . 'Km' . $i), $offset, 12));
                        Db::table('fa_kami')->insert([
                            'kami' => $code,
                            'udid' => '',
                            'kmyp' => $type,
                            'addtime' => $createdAt,
                            'usetime' => 0,
                            'endtime' => 0,
                        ]);
                        $html .= $code . '<br>';
                    }

                    Db::table('fa_kmstr')->where('id', 1)->update([
                        'kmstr' => $html,
                    ]);
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
