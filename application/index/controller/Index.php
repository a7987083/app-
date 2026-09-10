<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use app\common\library\BlacklistPolicy;
use app\common\library\CategoryDailyStat;
use app\common\library\SourceConfigRepository;
use think\Db;

class Index extends Frontend
{
    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';
    protected $layout = '';

    public function dylib()
    {
        header('Content-Type: application/json;charset=utf-8');
        $arr = ['code' => 0, 'msg' => '未获取设备UDID！', 'data' => []];
        if (empty($_REQUEST['udid'])) {
            echo json_encode($arr, JSON_UNESCAPED_UNICODE);
            die;
        }

        $arr['data'] = $this->dylibConfig();
        $udid = $_REQUEST['udid'];
        $res = Db::name('kami')->where('udid', $udid)->find();
        if (!$res) {
            $arr['msg'] = '未查到解锁记录！';
            $arr['code'] = 2;
            echo json_encode($arr, JSON_UNESCAPED_UNICODE);
            die;
        }

        $black = $this->activeBlacklist($res['udid']);
        if ($res['endtime'] <= time()) {
            $res = Db::name('kami')->where('udid', $udid)->where(['endtime' => ['>', time()]])->find();
            if (!$res) {
                $arr['msg'] = '设备解锁已到期！';
                $arr['code'] = 3;
                echo json_encode($arr, JSON_UNESCAPED_UNICODE);
                die;
            }
        }

        if ($black) {
            $this->markBlacklistUsed($black);
            $arr['msg'] = 'UDID黑名单';
            $arr['code'] = 666;
        } else {
            $arr['msg'] = '验证成功';
            $arr['code'] = 1;
        }
        echo json_encode($arr, JSON_UNESCAPED_UNICODE);
        die;
    }

    public function apiface()
    {
        if (empty($_REQUEST['udid'])) {
            echo json_encode(['msg' => '未获取设备udid'], JSON_UNESCAPED_UNICODE);
            die;
        }

        $udid = $_REQUEST['udid'];
        $res = Db::name('kami')->where('udid', $udid)->find();
        if (!$res) {
            echo json_encode(['msg' => '未查到解锁记录'], JSON_UNESCAPED_UNICODE);
            die;
        }

        if ($res['endtime'] <= time()) {
            $res = Db::name('kami')->where('udid', $udid)->where(['endtime' => ['>', time()]])->find();
            if (!$res) {
                echo json_encode(['msg' => '解锁已到期'], JSON_UNESCAPED_UNICODE);
                die;
            }
        }

        echo json_encode(['msg' => 'ok'], JSON_UNESCAPED_UNICODE);
        die;
    }

    public function index()
    {
        if (!empty($_POST['uid'])) {
            CategoryDailyStat::record((int)$_POST['uid']);
            return 'ok';
        }

        $data['img'] = Db::name('attachment')->whereNotNull('urls')->select();
        $data['category'] = Db::name('category')
            ->where('pid', 0)
            ->where('status', 'normal')
            ->order('weigh desc')
            ->select();
        $data['xm'] = $this->loadChildrenByParent($data['category']);

        return $this->view->fetch('', $data);
    }

    protected function activeBlacklist($udid)
    {
        $rows = Db::name('black')->where('udid', $udid)->order('id desc')->select();
        return BlacklistPolicy::findActive($rows);
    }

    protected function markBlacklistUsed(array $black)
    {
        if (!empty($black['id']) && empty($black['usetime'])) {
            Db::name('black')->where('id', $black['id'])->update(['usetime' => time()]);
        }
    }

    protected function dylibConfig()
    {
        return SourceConfigRepository::project(SourceConfigRepository::rows(), [
            'name' => 'name',
            'payURL' => 'pay',
            'dylib-look' => 'look',
            'dylib-control' => 'control',
            'dylib-notice' => 'notice',
            'dylib-time' => 'time',
            'dylib-on' => 'on',
        ]);
    }

    protected function loadChildrenByParent($parents)
    {
        $grouped = [];
        $parentIds = [];
        foreach ($parents as $parent) {
            $parentIds[] = $parent['id'];
            $grouped[$parent['id']] = [];
        }
        if (!$parentIds) {
            return $grouped;
        }

        $children = Db::name('category')
            ->where('pid', 'in', $parentIds)
            ->where('status', 'normal')
            ->order('weigh desc')
            ->select();
        foreach ($children as $child) {
            if (isset($grouped[$child['pid']])) {
                $grouped[$child['pid']][] = $child;
            }
        }
        return $grouped;
    }
}
