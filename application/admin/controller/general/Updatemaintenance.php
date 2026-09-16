<?php

namespace app\admin\controller\general;

use app\common\controller\Backend;
use app\common\library\UpdateIntegrity;
use app\common\library\update\UpdateOps;
use app\common\library\update\FastStorageManager;

/**
 * Update operations diagnostics and whole-site storage management.
 * Phase 15.3 keeps the panel request fast: index() only reads cached storage
 * data. Heavy scan/cleanup work is delegated to a PHP CLI worker which uses
 * system GNU find and exposes progress through storageStatus().
 */
class Updatemaintenance extends Backend
{
    protected $noNeedRight = ['index', 'panel', 'storageStatus'];

    public function index()
    {
        $ops = new UpdateOps(ROOT_PATH);
        $data = $ops->snapshot();
        $storage = new FastStorageManager(ROOT_PATH);
        $data['site_storage'] = $storage->snapshot();
        $data['storage_jobs'] = $storage->statuses();

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

    /** Poll-only endpoint for scan and cleanup progress bars. */
    public function storageStatus()
    {
        $storage = new FastStorageManager(ROOT_PATH);
        return json(['code' => 200, 'msg' => 'ok', 'data' => $storage->statuses()]);
    }

    /** Start a non-blocking system-level whole-site scan. */
    public function scan()
    {
        if (!$this->request->isPost()) {
            return json(['code' => 405, 'msg' => '仅允许 POST 请求', 'data' => '']);
        }
        $storage = new FastStorageManager(ROOT_PATH);
        $result = $storage->startScan();
        return json([
            'code' => $result['started'] ? 200 : ($result['status']['running'] ? 200 : 503),
            'msg' => $result['message'],
            'data' => $result,
        ]);
    }

    /**
     * apply=0: instant preview from the last completed index.
     * apply=1: start non-blocking cleanup worker; progress is polled separately.
     */
    public function cleanup()
    {
        if (!$this->request->isPost()) {
            return json(['code' => 405, 'msg' => '仅允许 POST 请求', 'data' => '']);
        }
        $apply = intval($this->request->param('apply', 0)) === 1;
        $storage = new FastStorageManager(ROOT_PATH);
        if (!$apply) {
            return json(['code' => 200, 'msg' => '自动安全清理预览完成', 'data' => $storage->previewSafe()]);
        }
        $result = $storage->startCleanup();
        return json([
            'code' => $result['started'] ? 200 : ($result['status']['running'] ? 200 : 503),
            'msg' => $result['message'],
            'data' => $result,
        ]);
    }

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
        $storage = new FastStorageManager(ROOT_PATH);
        $result = $storage->deleteSelected($paths, false);
        return json(['code' => 200, 'msg' => '人工确认清理完成', 'data' => $result]);
    }

    protected function localManifest()
    {
        $file = ROOT_PATH . 'ver.json';
        $raw = @file_get_contents($file);
        if ($raw === false) return false;
        $json = json_decode($raw, true);
        if (!is_array($json) || !isset($json['version'])) return false;
        return [
            'version' => trim((string)$json['version']),
            'file_sign' => isset($json['file_sign']) ? trim((string)$json['file_sign']) : '',
        ];
    }
}
