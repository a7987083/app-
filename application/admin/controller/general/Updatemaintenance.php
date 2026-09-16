<?php

namespace app\admin\controller\general;

use app\common\controller\Backend;
use app\common\library\UpdateIntegrity;
use app\common\library\update\UpdateOps;
use app\common\library\update\SiteStorageManager;

/**
 * Update operations diagnostics and whole-site storage management.
 */
class Updatemaintenance extends Backend
{
    protected $noNeedRight = ['index', 'panel'];

    /**
     * JSON diagnostics endpoint.
     */
    public function index()
    {
        $ops = new UpdateOps(ROOT_PATH);
        $data = $ops->snapshot();
        $storage = new SiteStorageManager(ROOT_PATH);
        $data['site_storage'] = $storage->snapshot();

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
     * Whole-site safe cleanup. Default is dry-run; apply=1 deletes only
     * explicitly regenerable/temp files after the minimum age.
     */
    public function cleanup()
    {
        if (!$this->request->isPost()) {
            return json(['code' => 405, 'msg' => '仅允许 POST 请求', 'data' => '']);
        }
        $apply = intval($this->request->param('apply', 0)) === 1;
        $storage = new SiteStorageManager(ROOT_PATH);
        $result = $storage->cleanupSafe(!$apply);
        return json([
            'code' => 200,
            'msg' => $apply ? '全站安全清理完成' : '全站安全清理预览完成',
            'data' => $result,
        ]);
    }

    /**
     * Manual deletion for review-only backup/unknown files. The server
     * reclassifies every path before deleting, so protected files cannot be
     * removed by a forged request.
     */
    public function cleanupSelected()
    {
        if (!$this->request->isPost()) {
            return json(['code' => 405, 'msg' => '仅允许 POST 请求', 'data' => '']);
        }
        $paths = $this->request->post('paths/a', []);
        if (!is_array($paths) || !$paths) {
            return json(['code' => 400, 'msg' => '请选择要删除的文件', 'data' => '']);
        }
        if (count($paths) > 200) {
            return json(['code' => 400, 'msg' => '单次最多删除 200 个文件', 'data' => '']);
        }
        $storage = new SiteStorageManager(ROOT_PATH);
        $result = $storage->deleteSelected($paths, false);
        return json(['code' => 200, 'msg' => '人工确认清理完成', 'data' => $result]);
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
