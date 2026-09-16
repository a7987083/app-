<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use app\common\library\BlacklistPolicy;
use app\common\library\CategoryDailyStat;
use app\common\library\CardAccessPolicy;
use app\common\library\CardDeviceTransfer;
use app\common\library\AuthorizationLicense;
use app\common\library\AuthorizationSchema;
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
        AuthorizationSchema::ensure();
        if (empty($_REQUEST['udid'])) {
            echo json_encode(['msg' => '未获取设备udid'], JSON_UNESCAPED_UNICODE);
            die;
        }

        $udid = trim((string)$_REQUEST['udid']);
        $res = Db::name('kami')->where('udid', $udid)->find();
        if (!$res) {
            echo json_encode(['msg' => '未查到解锁记录'], JSON_UNESCAPED_UNICODE);
            die;
        }

        $now = time();
        $activeRows = Db::name('kami')
            ->where('udid', $udid)
            ->where('jh', 1)
            ->where('endtime', '>', $now)
            ->order('endtime desc')
            ->select();
        if (!$activeRows) {
            echo json_encode(['msg' => '解锁已到期'], JSON_UNESCAPED_UNICODE);
            die;
        }

        try {
            $secretKey = $this->unlockSignKey();
        } catch (\Exception $e) {
            error_log('[Index::apiface] signing key unavailable');
            echo json_encode(['msg' => '解锁签名配置不可用'], JSON_UNESCAPED_UNICODE);
            die;
        }

        $expire = (int)$activeRows[0]['endtime'];
        $authorizations = $this->authorizationSummaries($activeRows);
        echo json_encode(
            $this->signedApiPayload($udid, $expire, $secretKey, $authorizations),
            JSON_UNESCAPED_UNICODE
        );
        die;
    }

    public function unbind()
    {
        AuthorizationSchema::ensure();
        $result = null;
        if ($this->request->isPost()) {
            $result = CardDeviceTransfer::transfer(
                $this->request->post('code', ''),
                $this->request->post('old_udid', ''),
                $this->request->post('new_udid', ''),
                $this->request->ip()
            );
        }
        $this->view->assign('transferResult', $result);
        return $this->view->fetch('index/unbind');
    }

    public function unbindQuery()
    {
        AuthorizationSchema::ensure();
        return json(CardDeviceTransfer::queryStatus($this->request->request('udid', '')));
    }

    public function license()
    {
        AuthorizationSchema::ensure();
        $result = null;
        if ($this->request->isPost()) {
            $result = AuthorizationLicense::query(
                $this->request->post('code', ''),
                $this->request->post('udid', '')
            );
        }
        $this->view->assign('licenseResult', $result);
        return $this->view->fetch('index/license');
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

    protected function unlockSignKey()
    {
        $key = (string)SourceConfigRepository::get('unlock_sign_key', '', false);
        if ($key !== '') {
            return $key;
        }

        $generated = bin2hex(random_bytes(32));
        $row = Db::name('config')->where('name', 'unlock_sign_key')->find();
        if ($row) {
            $current = isset($row['value']) ? (string)$row['value'] : '';
            if ($current === '') {
                Db::name('config')
                    ->where('id', (int)$row['id'])
                    ->where('value', $current)
                    ->update(['value' => $generated]);
            }
        } else {
            try {
                Db::name('config')->insert([
                    'name' => 'unlock_sign_key',
                    'group' => 'basic',
                    'title' => '解锁签名KEY',
                    'tip' => '用于解锁响应HMAC-SHA256签名；留空时系统自动生成',
                    'type' => 'string',
                    'value' => $generated,
                    'content' => '',
                    'rule' => '',
                    'extend' => 'autocomplete="off"',
                ]);
            } catch (\Exception $e) {
                // A concurrent request may have inserted the unique config row first.
            }
        }

        SourceConfigRepository::forget();
        $key = (string)SourceConfigRepository::get('unlock_sign_key', '', false);
        if ($key === '') {
            throw new \RuntimeException('解锁签名KEY不可用');
        }
        return $key;
    }

    protected function signedApiPayload($udid, $expire, $secretKey, array $authorizations = [])
    {
        $expire = (int)$expire;
        $ts = time();
        $nonce = bin2hex(random_bytes(8));
        $signData = $udid . '|' . $expire . '|' . $ts . '|' . $nonce;

        $payload = [
            'code' => 1,
            'msg' => 'ok',
            'expire' => $expire,
            'ts' => $ts,
            'nonce' => $nonce,
            'sign' => hash_hmac('sha256', $signData, $secretKey),
        ];
        if ($authorizations) {
            // Keep all legacy fields and their order unchanged. New scope detail
            // is intentionally appended after sign for backward compatibility.
            $payload['authorizations'] = $authorizations;
        }
        return $payload;
    }

    protected function authorizationSummaries(array $rows)
    {
        $expires = [];
        foreach ($rows as $row) {
            $scope = CardAccessPolicy::scopeForRow($row);
            $endtime = isset($row['endtime']) ? (int)$row['endtime'] : 0;
            if (!isset($expires[$scope]) || $endtime > $expires[$scope]) {
                $expires[$scope] = $endtime;
            }
        }

        $result = [];
        foreach ([CardAccessPolicy::SCOPE_SOURCE, CardAccessPolicy::SCOPE_VERIFY, CardAccessPolicy::SCOPE_APPS] as $scope) {
            if (!isset($expires[$scope])) {
                continue;
            }
            $result[] = [
                'scope' => $scope,
                'type' => CardAccessPolicy::scopeName($scope),
                'expire' => (int)$expires[$scope],
            ];
        }
        return $result;
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
