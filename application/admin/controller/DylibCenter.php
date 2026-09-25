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
    protected $accessLevels = ['', 'basic', 'app_plus', 'global_plus'];
    protected $noticeActions = ['dismiss', 'open_url'];

    public function index()
    {
        $this->view->assign('dylibs', Db::name('dylib')
            ->field('id,dylib_key,name,enabled,default_offline_grace,default_fail_action,created_at,updated_at')
            ->order('id desc')->select());
        $this->view->assign('states', $this->states);
        $this->view->assign('failActions', $this->failActions);
        $runtimeConfig = Db::name('dylib_runtime_config')->where('id', 1)->find();
        if (!$runtimeConfig) {
            $runtimeConfig = [
                'config_version' => 1,
                'api_endpoints_json' => '[]',
                'bootstrap_urls_json' => '[]',
                'verify_path' => '/index/dylib_verify/verify',
                'update_title' => '发现游戏新版本',
                'update_message' => '当前版本：{current_version} ({current_build})\n最新版本：{latest_version} ({latest_build})',
                'update_primary_title' => '前往更新',
                'update_secondary_title' => '稍后提醒',
            ];
        }
        $runtimeConfig['api_endpoints_text'] = implode("\n", $this->decodeUrlList(isset($runtimeConfig['api_endpoints_json']) ? $runtimeConfig['api_endpoints_json'] : '[]'));
        $runtimeConfig['bootstrap_urls_text'] = implode("\n", $this->decodeUrlList(isset($runtimeConfig['bootstrap_urls_json']) ? $runtimeConfig['bootstrap_urls_json'] : '[]'));
        $this->view->assign('runtimeConfig', $runtimeConfig);
        $this->view->assign('appOptions', Db::name('category')
            ->field('id,name')->where('status', 'normal')->where('bt2b', '1')->order('weigh desc,id desc')->select());
        return $this->view->fetch();
    }

    public function versions()
    {
        $dylibId = (int)$this->request->get('dylib_id', 0);
        $offset = max(0, (int)$this->request->get('offset', 0));
        $limit = max(20, min(200, (int)$this->request->get('limit', 50)));
        $query = Db::name('dylib_version');
        if ($dylibId > 0) $query->where('dylib_id', $dylibId);
        $total = (clone $query)->count();
        $rows = $query->field('id,dylib_id,version,build,sha256,file_size,state,offline_grace,fail_action,notice,created_at,updated_at')
            ->order('id desc')->limit($offset, $limit)->select();
        return json(['total' => (int)$total, 'rows' => $rows]);
    }

    /** Legacy 2405 endpoint retained for historical data/API compatibility. */
    public function bindings()
    {
        $dylibId = (int)$this->request->get('dylib_id', 0);
        $offset = max(0, (int)$this->request->get('offset', 0));
        $limit = max(20, min(200, (int)$this->request->get('limit', 50)));
        $query = Db::name('dylib_app_binding');
        if ($dylibId > 0) $query->where('dylib_id', $dylibId);
        $total = (clone $query)->count();
        $rows = $query->field('id,dylib_id,bundle_id,enabled,fail_action_override,offline_grace_override,created_at,updated_at')
            ->order('id desc')->limit($offset, $limit)->select();
        return json(['total' => (int)$total, 'rows' => $rows]);
    }

    public function notices()
    {
        $offset = max(0, (int)$this->request->get('offset', 0));
        $limit = max(20, min(200, (int)$this->request->get('limit', 50)));
        $query = Db::name('dylib_runtime_notice');
        $total = (clone $query)->count();
        $rows = $query->field('id,enabled,category_id,min_access_level,notice_key,revision,priority,title,message,primary_title,primary_action,primary_url,secondary_title,secondary_action,secondary_url,starts_at,ends_at,created_at,updated_at')
            ->order('priority desc,id desc')->limit($offset, $limit)->select();
        $categoryIds = [];
        foreach ($rows as $row) if ((int)$row['category_id'] > 0) $categoryIds[(int)$row['category_id']] = true;
        $names = [];
        if ($categoryIds) {
            foreach (Db::name('category')->field('id,name')->where('id', 'in', array_keys($categoryIds))->select() as $category) {
                $names[(int)$category['id']] = (string)$category['name'];
            }
        }
        foreach ($rows as &$row) {
            $id = (int)$row['category_id'];
            $row['app_name'] = $id > 0 ? (isset($names[$id]) ? $names[$id] : ('App #' . $id)) : '全部 App';
        }
        unset($row);
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
        foreach ($rows as &$row) if (!empty($row['udid_hash'])) $row['udid_hash'] = substr($row['udid_hash'], 0, 12) . '…';
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
        if (!preg_match('/^[A-Za-z0-9._-]{2,128}$/', $key)) $this->error('Invalid dylib key');
        if ($name === '') $this->error('Dylib name is required');
        if (!in_array($action, $this->failActions, true)) $this->error('Invalid fail action');
        if ($id <= 0 && strlen($verifySecret) < 32) $this->error('New dylib requires a verify secret with at least 32 characters');
        if ($verifySecret !== '' && strlen($verifySecret) < 32) $this->error('Verify secret must contain at least 32 characters');

        $existing = null;
        if ($id > 0) {
            $existing = Db::name('dylib')->where('id', $id)->find();
            if (!$existing) $this->error('Dylib not found');
            if ((string)$existing['dylib_key'] !== $key) $this->error('Dylib key cannot be changed after registration');
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
        if ($verifySecret !== '') $data['verify_secret_ciphertext'] = SecretBox::encrypt($verifySecret);
        try {
            if ($id > 0) Db::name('dylib')->where('id', $id)->update($data);
            else {
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
        if ($id <= 0 || !in_array((string)$enabledRaw, ['0', '1'], true)) $this->error('Invalid dylib status');
        $dylib = Db::name('dylib')->where('id', $id)->find();
        if (!$dylib) $this->error('Dylib not found');
        $enabled = (int)$enabledRaw;
        Db::name('dylib')->where('id', $id)->update(['enabled' => $enabled, 'updated_at' => time()]);
        $this->success($enabled ? 'enabled' : 'disabled', null, ['id' => $id, 'enabled' => $enabled]);
    }

    public function deleteDylib()
    {
        $this->requirePost();
        $id = (int)$this->request->post('id', 0);
        if ($id <= 0) $this->error('Invalid dylib id');
        $dylib = Db::name('dylib')->where('id', $id)->find();
        if (!$dylib) $this->error('Dylib not found');
        $versionCount = (int)Db::name('dylib_version')->where('dylib_id', $id)->count();
        // 2405 binding rows are retired from active authorization in 2406 but remain
        // historical references and therefore still prevent destructive deletion.
        $bindingCount = (int)Db::name('dylib_app_binding')->where('dylib_id', $id)->count();
        $logCount = (int)Db::name('dylib_verify_log')->where('dylib_key', (string)$dylib['dylib_key'])->count();
        if ($versionCount > 0 || $bindingCount > 0 || $logCount > 0) {
            $this->error(sprintf('该 Dylib 已产生业务历史（版本 %d / 旧游戏授权 %d / 验证记录 %d），为保留历史禁止删除，请改为停用', $versionCount, $bindingCount, $logCount));
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
        if (!Db::name('dylib')->where('id', $dylibId)->find()) $this->error('Dylib not found');
        if ($version === '' || strlen($version) > 64 || strlen($build) > 64) $this->error('Invalid version/build');
        if ($sha256 !== '' && !preg_match('/^[a-f0-9]{64}$/', $sha256)) $this->error('SHA256 must be 64 lowercase hex characters');
        if (!in_array($state, $this->states, true) || !in_array($action, $this->failActions, true)) $this->error('Invalid state or fail action');
        $now = time();
        $data = [
            'dylib_id' => $dylibId, 'version' => $version, 'build' => $build, 'sha256' => $sha256,
            'file_size' => max(0, (int)$this->request->post('file_size', 0)), 'state' => $state,
            'offline_grace' => max(0, min(86400, (int)$this->request->post('offline_grace', 900))),
            'fail_action' => $action, 'notice' => mb_substr(trim((string)$this->request->post('notice', '')), 0, 1024),
            'updated_at' => $now,
        ];
        try {
            if ($id > 0) Db::name('dylib_version')->where('id', $id)->update($data);
            else {
                $data['created_at'] = $now;
                $id = Db::name('dylib_version')->insertGetId($data);
            }
            $this->success('saved', null, ['id' => (int)$id]);
        } catch (\think\exception\PDOException $e) {
            $this->error(stripos($e->getMessage(), 'Duplicate') !== false ? 'Version/build already exists' : $e->getMessage());
        }
    }

    /** Legacy write endpoint retained; 2406 verification no longer reads this table. */
    public function saveBinding()
    {
        $this->requirePost();
        $id = (int)$this->request->post('id', 0);
        $dylibId = (int)$this->request->post('dylib_id', 0);
        $bundleId = trim((string)$this->request->post('bundle_id', ''));
        $action = trim((string)$this->request->post('fail_action_override', ''));
        if (!Db::name('dylib')->where('id', $dylibId)->find()) $this->error('Dylib not found');
        if ($bundleId === '' || strlen($bundleId) > 255 || strpos($bundleId, '.') === false) $this->error('Invalid bundle id');
        if ($action !== '' && !in_array($action, $this->failActions, true)) $this->error('Invalid fail action override');
        $now = time();
        $data = [
            'dylib_id' => $dylibId, 'bundle_id' => $bundleId,
            'enabled' => (int)$this->request->post('enabled', 1) ? 1 : 0,
            'fail_action_override' => $action,
            'offline_grace_override' => max(0, min(86400, (int)$this->request->post('offline_grace_override', 0))),
            'updated_at' => $now,
        ];
        try {
            if ($id > 0) Db::name('dylib_app_binding')->where('id', $id)->update($data);
            else {
                $data['created_at'] = $now;
                $id = Db::name('dylib_app_binding')->insertGetId($data);
            }
            $this->success('saved', null, ['id' => (int)$id]);
        } catch (\think\exception\PDOException $e) {
            $this->error(stripos($e->getMessage(), 'Duplicate') !== false ? 'Bundle ID is already bound to this dylib' : $e->getMessage());
        }
    }

    public function saveRuntimeConfig()
    {
        $this->requirePost();
        $apiEndpoints = $this->parseUrlLines((string)$this->request->post('api_endpoints', ''));
        $bootstrapUrls = $this->parseUrlLines((string)$this->request->post('bootstrap_urls', ''));
        $verifyPath = trim((string)$this->request->post('verify_path', '/index/dylib_verify/verify'));
        if ($verifyPath === '' || $verifyPath[0] !== '/') $this->error('验证路径必须以 / 开头');
        $current = Db::name('dylib_runtime_config')->where('id', 1)->find();
        $data = [
            'config_version' => max(1, (int)(isset($current['config_version']) ? $current['config_version'] : 0) + 1),
            'api_endpoints_json' => json_encode($apiEndpoints, JSON_UNESCAPED_SLASHES),
            'bootstrap_urls_json' => json_encode($bootstrapUrls, JSON_UNESCAPED_SLASHES),
            'verify_path' => $verifyPath,
            'update_title' => mb_substr(trim((string)$this->request->post('update_title', '发现游戏新版本')), 0, 255),
            'update_message' => mb_substr(trim((string)$this->request->post('update_message', '')), 0, 1024),
            'update_primary_title' => mb_substr(trim((string)$this->request->post('update_primary_title', '前往更新')), 0, 64),
            'update_secondary_title' => mb_substr(trim((string)$this->request->post('update_secondary_title', '稍后提醒')), 0, 64),
            'updated_at' => time(),
        ];
        if ($current) Db::name('dylib_runtime_config')->where('id', 1)->update($data);
        else {
            $data['id'] = 1;
            Db::name('dylib_runtime_config')->insert($data);
        }
        $this->success('运行配置已保存；配置版本已自动递增', null, ['config_version' => $data['config_version']]);
    }

    public function saveNotice()
    {
        $this->requirePost();
        $id = (int)$this->request->post('id', 0);
        $categoryId = max(0, (int)$this->request->post('category_id', 0));
        if ($categoryId > 0 && !Db::name('category')->where('id', $categoryId)->where('status', 'normal')->where('bt2b', '1')->find()) {
            $this->error('指定 App 不存在、已停用或不可锁定');
        }
        $access = trim((string)$this->request->post('min_access_level', ''));
        if (!in_array($access, $this->accessLevels, true)) $this->error('Invalid access level');
        $noticeKey = trim((string)$this->request->post('notice_key', ''));
        if (!preg_match('/^[A-Za-z0-9._-]{2,128}$/', $noticeKey)) $this->error('通知 Key 格式无效');
        $primaryAction = trim((string)$this->request->post('primary_action', 'dismiss'));
        $secondaryAction = trim((string)$this->request->post('secondary_action', 'dismiss'));
        if (!in_array($primaryAction, $this->noticeActions, true) || !in_array($secondaryAction, $this->noticeActions, true)) $this->error('通知按钮动作无效');
        $primaryUrl = trim((string)$this->request->post('primary_url', ''));
        $secondaryUrl = trim((string)$this->request->post('secondary_url', ''));
        if ($primaryAction === 'open_url' && !$this->validHttpUrl($primaryUrl)) $this->error('主按钮 URL 无效');
        if ($secondaryAction === 'open_url' && !$this->validHttpUrl($secondaryUrl)) $this->error('副按钮 URL 无效');
        $now = time();
        $data = [
            'enabled' => (int)$this->request->post('enabled', 1) ? 1 : 0,
            'category_id' => $categoryId,
            'min_access_level' => $access,
            'notice_key' => $noticeKey,
            'revision' => max(1, (int)$this->request->post('revision', 1)),
            'priority' => (int)$this->request->post('priority', 0),
            'title' => mb_substr(trim((string)$this->request->post('title', '')), 0, 255),
            'message' => trim((string)$this->request->post('message', '')),
            'primary_title' => mb_substr(trim((string)$this->request->post('primary_title', '')), 0, 64),
            'primary_action' => $primaryAction,
            'primary_url' => $primaryUrl,
            'secondary_title' => mb_substr(trim((string)$this->request->post('secondary_title', '')), 0, 64),
            'secondary_action' => $secondaryAction,
            'secondary_url' => $secondaryUrl,
            'starts_at' => max(0, (int)$this->request->post('starts_at', 0)),
            'ends_at' => max(0, (int)$this->request->post('ends_at', 0)),
            'updated_at' => $now,
        ];
        if ($data['title'] === '' && $data['message'] === '') $this->error('通知标题和正文不能同时为空');
        try {
            if ($id > 0) Db::name('dylib_runtime_notice')->where('id', $id)->update($data);
            else {
                $data['created_at'] = $now;
                $id = Db::name('dylib_runtime_notice')->insertGetId($data);
            }
            $this->success('通知已保存', null, ['id' => (int)$id]);
        } catch (\think\exception\PDOException $e) {
            $this->error(stripos($e->getMessage(), 'Duplicate') !== false ? '通知 Key 已存在' : $e->getMessage());
        }
    }

    protected function parseUrlLines($raw)
    {
        $out = [];
        foreach (preg_split('/[\r\n,]+/', (string)$raw) as $value) {
            $url = rtrim(trim((string)$value), '/');
            if ($url === '') continue;
            if (!$this->validHttpUrl($url)) $this->error('URL 无效：' . $url);
            $out[$url] = true;
        }
        return array_keys($out);
    }

    protected function decodeUrlList($json)
    {
        $data = json_decode((string)$json, true);
        return is_array($data) ? array_values(array_filter(array_map('strval', $data))) : [];
    }

    protected function validHttpUrl($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL)
            && (stripos($url, 'https://') === 0 || stripos($url, 'http://') === 0);
    }

    protected function requirePost()
    {
        if (!$this->request->isPost()) $this->error('POST required');
    }
}
