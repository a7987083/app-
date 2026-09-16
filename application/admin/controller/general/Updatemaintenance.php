<?php

namespace app\admin\controller\general;

use app\common\controller\Backend;
use app\common\library\UpdateIntegrity;
use app\common\library\update\UpdateOps;

/**
 * Update operations center.
 *
 * Phase 15.4 deliberately removes whole-site file scanning/storage search.
 * This controller now only manages data created by the online-update subsystem:
 * status, history, rollback backups, update cache and update lock diagnostics.
 */
class Updatemaintenance extends Backend
{
    protected $noNeedRight = ['index', 'panel'];

    public function index()
    {
        $ops = new UpdateOps(ROOT_PATH);
        $data = $ops->snapshot();

        $manifest = $this->localManifest();
        $version = is_array($manifest) ? $manifest['version'] : '';
        $expected = is_array($manifest) ? $manifest['file_sign'] : '';
        $actual = UpdateIntegrity::signFromRoot(ROOT_PATH);
        $data['release'] = [
            'version' => $version,
            'tag' => $version !== '' ? 'source-v' . $version : '',
            'expected_file_sign' => $expected,
            'actual_file_sign' => $actual,
            'integrity_verified' => $expected !== '' ? hash_equals($expected, $actual) : null,
        ];

        return json(['code' => 200, 'msg' => 'ok', 'data' => $data]);
    }

    public function panel()
    {
        return $this->view->fetch();
    }

    /**
     * Cleanup is intentionally limited to updater-owned data.
     * apply=0 previews; apply=1 applies the same conservative retention rules.
     */
    public function cleanup()
    {
        if (!$this->request->isPost()) {
            return json(['code' => 405, 'msg' => '仅允许 POST 请求', 'data' => '']);
        }

        $apply = intval($this->request->param('apply', 0)) === 1;
        $ops = new UpdateOps(ROOT_PATH);
        $result = $ops->cleanup($apply ? false : true);

        return json([
            'code' => 200,
            'msg' => $apply ? '更新数据安全清理完成' : '更新数据安全清理预览完成',
            'data' => $result,
        ]);
    }

    protected function localManifest()
    {
        $file = ROOT_PATH . 'ver.json';
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return false;
        }
        $json = json_decode($raw, true);
        if (!is_array($json) || !isset($json['version'])) {
            return false;
        }
        return [
            'version' => trim((string)$json['version']),
            'file_sign' => isset($json['file_sign']) ? trim((string)$json['file_sign']) : '',
        ];
    }
}
