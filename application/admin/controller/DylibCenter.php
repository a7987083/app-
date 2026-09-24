<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\Ipa\SecretBox;
use think\Db;

class DylibCenter extends Backend
{
    protected $noNeedRight = [];

    protected $states = ['active', 'deprecated', 'blocked', 'testing', 'revoked'];
    protected $failActions = ['disable_feature', 'show_message', 'block'];

    public function index()
    {
        $this->view->assign('dylibs', Db::name('dylib')
            ->field('id,dylib_key,name,enabled,default_offline_grace,default_fail_action,created_at,updated_at')
            ->order('id desc')->select());
        $this->view->assign('states', $this->states);
        $this->view->assign('failActions', $this->failActions);
        return $this->view->fetch();
    }

    public function versions()
    {
        $dylibId = (int)$this->request->get('dylib_id', 0);
        $offset = max(0, (int)$this->request->get('offset', 0));
        $limit = max(20, min(200, (int)$this->request->get('limit', 50)));
        $query = Db::name('dylib_version');
        if ($dylibId > 0) {
            $query->where('dylib_id', $dylibId);
        }
        $total = (clone $query)->count();
        $rows = $query->field('id,dylib_id,version,build,sha256,file_size,state,offline_grace,fail_action,notice,created_at,updated_at')
            ->order('id desc')->limit($offset, $limit)->select();
        return json(['total' => (int)$total, 'rows' => $rows]);
    }

    public function bindings()
    {
        $dylibId = (int)$this->request->get('dylib_id', 0);
        $offset = max(0, (int)$this->request->get('offset', 0));
        $limit = max(20, min(200, (int)$this->request->get('limit', 50)));
        $query = Db::name('dylib_app_binding');
        if ($dylibId > 0) {
            $query->where('dylib_id', $dylibId);
        }
        $total = (clone $query)->count();
        $rows = $query->field('id,dylib_id,bundle_id,enabled,fail_action_override,offline_grace_override,created_at,updated_at')
            ->order('id desc')->limit($offset, $limit)->select();
        return json(['total' => (int)$total, 'rows' => $rows]);
    }

    public function logs()
    {
        $offset = max(0, (int)$this->request->get('offset', 0));
        $limit = max(20, min(200, (int)$this->request->get('limit', 50)));
        $search = trim((string)$this->request->get('search', ''));
        $query = Db::name('dylib_verify_log');
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('bundle_id', 'like', '%' . $search . '%')
                    ->whereOr('dylib_key', 'like', '%' . $search . '%')
                    ->whereOr('result_code', 'like', '%' . $search . '%');
            });
        }
        $total = (clone $query)->count();
        $rows = $query->field('id,udid_hash,bundle_id,dylib_key,dylib_version,result_code,action,latency_ms,created_at')
            ->order('id desc')->limit($offset, $limit)->select();
        foreach ($rows as &$row) {
            if (!empty($row['udid_hash'])) {
                $row['udid_hash'] = substr($row['udid_hash'], 0, 12) . '…';
            }
        }
        unset($row);
        return json(['total' => (int)$total, 'rows' => $rows]);
    }

    public function generateVerifySecret()
    {
        $this->requirePost();
        $this->success('generated', null, ['secret' => bin2hex(random_bytes(32))]);
    }

    public function saveDylib()
    {
        $this->requirePost();
        $id = (int)$this->request->post('id', 0);
        $key = trim((string)$this->request->post('dylib_key', ''));
        $name = trim((string)$this->request->post('name', ''));
        $verifySecret = trim((string)$this->request->post('verify_secret', ''));
        $grace = max(0, min(86400, (int)$this->request->post('default_offline_grace', 900)));
        $action = trim((string)$this->request->post('default_fail_action', 'disable_feature'));
        if (!preg_match('/^[A-Za-z0-9._-]{2,128}$/', $key)) {
            $this->error('Invalid dylib key');
        }
        if ($name === '') {
            $this->error('Dylib name is required');
        }
        if (!in_array($action, $this->failActions, true)) {
            $this->error('Invalid fail action');
        }
        if ($id <= 0 && strlen($verifySecret) < 32) {
            $this->error('New dylib requires a verify secret with at least 32 characters');
        }
        if ($verifySecret !== '' && strlen($verifySecret) < 32) {
            $this->error('Verify secret must contain at least 32 characters');
        }

        $existing = null;
        if ($id > 0) {
            $existing = Db::name('dylib')->where('id', $id)->find();
            if (!$existing) {
                $this->error('Dylib not found');
            }
            // dylib_key participates in the client signing/lookup contract. Keep it immutable
            // after registration so an admin edit cannot silently break existing clients.
            if ((string)$existing['dylib_key'] !== $key) {
                $this->error('Dylib key cannot be changed after registration');
            }
        }

        $now = time();
        $data = [
            'dylib_key' => $key,
            'name' => $name,
            'enabled' => (int)$this->request->post('enabled', $existing ? (int)$existing['enabled'] : 1) ? 1 : 0,
            'default_offline_grace' => $grace,
            'default_fail_action' => $action,
            'updated_at' => $now,
        ];
        if ($verifySecret !== '') {
            $data['verify_secret_ciphertext'] = SecretBox::encrypt($verifySecret);
        }
        try {
            if ($id > 0) {
                Db::name('dylib')->where('id', $id)->update($data);
            } else {
                $data['created_at'] = $now;
                $id = Db::name('dylib')->insertGetId($data);
            }
            $this->success('saved', null, ['id' => (int)$id]);
        } catch (\think\exception\PDOException $e) {
            $this->error(stripos($e->getMessage(), 'Duplicate') !== false ? 'Dylib key already exists' : $e->getMessage());
        }
    }

    public function setDylibEnabled()
    {
        $this->requirePost();
        $id = (int)$this->request->post('id', 0);
        $enabledRaw = $this->request->post('enabled', null);
        if ($id <= 0 || !in_array((string)$enabledRaw, ['0', '1'], true)) {
            $this->error('Invalid dylib status');
        }
        $dylib = Db::name('dylib')->where('id', $id)->find();
        if (!$dylib) {
            $this->error('Dylib not found');
        }
        $enabled = (int)$enabledRaw;
        Db::name('dylib')->where('id', $id)->update([
            'enabled' => $enabled,
            'updated_at' => time(),
        ]);
        $this->success($enabled ? 'enabled' : 'disabled', null, [
            'id' => $id,
            'enabled' => $enabled,
        ]);
    }

    public function deleteDylib()
    {
        $this->requirePost();
        $id = (int)$this->request->post('id', 0);
        if ($id <= 0) {
            $this->error('Invalid dylib id');
        }
        $dylib = Db::name('dylib')->where('id', $id)->find();
        if (!$dylib) {
            $this->error('Dylib not found');
        }

        // The schema intentionally has no foreign keys and no soft-delete column. A hard
        // delete is only safe before the registration has any business history. Once used,
        // disabling preserves versions, BundleID policy and the audit log without orphans.
        $versionCount = (int)Db::name('dylib_version')->where('dylib_id', $id)->count();
        $bindingCount = (int)Db::name('dylib_app_binding')->where('dylib_id', $id)->count();
        $logCount = (int)Db::name('dylib_verify_log')->where('dylib_key', (string)$dylib['dylib_key'])->count();
        if ($versionCount > 0 || $bindingCount > 0 || $logCount > 0) {
            $this->error(sprintf(
                '该 Dylib 已产生业务历史（版本 %d / 游戏授权 %d / 验证记录 %d），为保留历史禁止删除，请改为停用',
                $versionCount,
                $bindingCount,
                $logCount
            ));
        }

        Db::name('dylib')->where('id', $id)->delete();
        $this->success('deleted', null, ['id' => $id]);
    }

    public function saveVersion()
    {
        $this->requirePost();
        $id = (int)$this->request->post('id', 0);
        $dylibId = (int)$this->request->post('dylib_id', 0);
        $version = trim((string)$this->request->post('version', ''));
        $build = trim((string)$this->request->post('build', ''));
        $sha256 = strtolower(trim((string)$this->request->post('sha256', '')));
        $state = trim((string)$this->request->post('state', 'testing'));
        $action = trim((string)$this->request->post('fail_action', 'disable_feature'));
        if (!Db::name('dylib')->where('id', $dylibId)->find()) {
            $this->error('Dylib not found');
        }
        if ($version === '' || strlen($version) > 64 || strlen($build) > 64) {
            $this->error('Invalid version/build');
        }
        if ($sha256 !== '' && !preg_match('/^[a-f0-9]{64}$/', $sha256)) {
            $this->error('SHA256 must be 64 lowercase hex characters');
        }
        if (!in_array($state, $this->states, true) || !in_array($action, $this->failActions, true)) {
            $this->error('Invalid state or fail action');
        }
        $now = time();
        $data = [
            'dylib_id' => $dylibId,
            'version' => $version,
            'build' => $build,
            'sha256' => $sha256,
            'file_size' => max(0, (int)$this->request->post('file_size', 0)),
            'state' => $state,
            'offline_grace' => max(0, min(86400, (int)$this->request->post('offline_grace', 900))),
            'fail_action' => $action,
            'notice' => mb_substr(trim((string)$this->request->post('notice', '')), 0, 1024),
            'updated_at' => $now,
        ];
        try {
            if ($id > 0) {
                Db::name('dylib_version')->where('id', $id)->update($data);
            } else {
                $data['created_at'] = $now;
                $id = Db::name('dylib_version')->insertGetId($data);
            }
            $this->success('saved', null, ['id' => (int)$id]);
        } catch (\think\exception\PDOException $e) {
            $this->error(stripos($e->getMessage(), 'Duplicate') !== false ? 'Version/build already exists' : $e->getMessage());
        }
    }

    public function saveBinding()
    {
        $this->requirePost();
        $id = (int)$this->request->post('id', 0);
        $dylibId = (int)$this->request->post('dylib_id', 0);
        $bundleId = trim((string)$this->request->post('bundle_id', ''));
        $action = trim((string)$this->request->post('fail_action_override', ''));
        if (!Db::name('dylib')->where('id', $dylibId)->find()) {
            $this->error('Dylib not found');
        }
        if ($bundleId === '' || strlen($bundleId) > 255 || strpos($bundleId, '.') === false) {
            $this->error('Invalid bundle id');
        }
        if ($action !== '' && !in_array($action, $this->failActions, true)) {
            $this->error('Invalid fail action override');
        }
        $now = time();
        $data = [
            'dylib_id' => $dylibId,
            'bundle_id' => $bundleId,
            'enabled' => (int)$this->request->post('enabled', 1) ? 1 : 0,
            'fail_action_override' => $action,
            'offline_grace_override' => max(0, min(86400, (int)$this->request->post('offline_grace_override', 0))),
            'updated_at' => $now,
        ];
        try {
            if ($id > 0) {
                Db::name('dylib_app_binding')->where('id', $id)->update($data);
            } else {
                $data['created_at'] = $now;
                $id = Db::name('dylib_app_binding')->insertGetId($data);
            }
            $this->success('saved', null, ['id' => (int)$id]);
        } catch (\think\exception\PDOException $e) {
            $this->error(stripos($e->getMessage(), 'Duplicate') !== false ? 'Bundle ID is already bound to this dylib' : $e->getMessage());
        }
    }

    protected function requirePost()
    {
        if (!$this->request->isPost()) {
            $this->error('POST required');
        }
    }
}
