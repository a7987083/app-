<?php

namespace app\admin\controller\general;

use app\common\controller\Backend;
use app\common\library\UpdateIntegrity;
use app\common\library\update\UpdateOps;
use app\common\library\update\FastStorageManagerCompat;

/**
 * Update operations diagnostics and whole-site storage management.
 * The panel endpoint must always return valid JSON even when the optional
 * fast-storage runtime cannot initialize on a production host.
 */
class Updatemaintenance extends Backend
{
    protected $noNeedRight = ['index', 'panel'];

    public function index()
    {
        $storage = null;
        $storageError = '';
        try {
            $storage = new FastStorageManagerCompat(ROOT_PATH);
        } catch (\Throwable $e) {
            $storageError = $e->getMessage();
        }

        if (intval($this->request->param('status_only', 0)) === 1) {
            if ($storage) {
                try {
                    return json(['code' => 200, 'msg' => 'ok', 'data' => ['storage_jobs' => $storage->statuses()]]);
                } catch (\Throwable $e) {
                    $storageError = $e->getMessage();
                }
            }
            return json([
                'code' => 200,
                'msg' => 'storage unavailable',
                'data' => [
                    'storage_jobs' => $this->emptyStorageJobs($storageError),
                    'storage_error' => $storageError,
                ],
            ]);
        }

        $ops = new UpdateOps(ROOT_PATH);
        $data = $ops->snapshot();

        if ($storage) {
            try {
                $data['site_storage'] = $storage->snapshot();
                $data['storage_jobs'] = $storage->statuses();
            } catch (\Throwable $e) {
                $storageError = $e->getMessage();
                $data['site_storage'] = $this->storageFallback($storageError);
                $data['storage_jobs'] = $this->emptyStorageJobs($storageError);
            }
        } else {
            $data['site_storage'] = $this->storageFallback($storageError);
            $data['storage_jobs'] = $this->emptyStorageJobs($storageError);
        }
        $data['storage_error'] = $storageError;

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
        return json(['code' => 200, 'msg' => $storageError === '' ? 'ok' : 'storage degraded', 'data' => $data]);
    }

    public function panel()
    {
        return $this->view->fetch();
    }

    public function scan()
    {
        if (!$this->request->isPost()) {
            return json(['code' => 405, 'msg' => '仅允许 POST 请求', 'data' => '']);
        }
        try {
            $storage = new FastStorageManagerCompat(ROOT_PATH);
            $result = $storage->startScan();
            return json([
                'code' => $result['started'] ? 200 : ($result['status']['running'] ? 200 : 503),
                'msg' => $result['message'],
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return json(['code' => 503, 'msg' => '高速扫描引擎初始化失败：' . $e->getMessage(), 'data' => '']);
        }
    }

    public function cleanup()
    {
        if (!$this->request->isPost()) {
            return json(['code' => 405, 'msg' => '仅允许 POST 请求', 'data' => '']);
        }
        $apply = intval($this->request->param('apply', 0)) === 1;
        try {
            $storage = new FastStorageManagerCompat(ROOT_PATH);
            if (!$apply) {
                return json(['code' => 200, 'msg' => '自动安全清理预览完成', 'data' => $storage->previewSafe()]);
            }
            $result = $storage->startCleanup();
            return json([
                'code' => $result['started'] ? 200 : ($result['status']['running'] ? 200 : 503),
                'msg' => $result['message'],
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return json(['code' => 503, 'msg' => '安全清理引擎初始化失败：' . $e->getMessage(), 'data' => '']);
        }
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
        try {
            $storage = new FastStorageManagerCompat(ROOT_PATH);
            $result = $storage->deleteSelected($paths, false);
            return json(['code' => 200, 'msg' => '人工确认清理完成', 'data' => $result]);
        } catch (\Throwable $e) {
            return json(['code' => 503, 'msg' => '储存管理引擎初始化失败：' . $e->getMessage(), 'data' => '']);
        }
    }

    protected function storageFallback($message)
    {
        $empty = ['count' => 0, 'bytes' => 0];
        return [
            'index_ready' => false,
            'engine' => [
                'mode' => 'unavailable',
                'system_scan_available' => false,
                'background_worker_available' => false,
                'error' => (string)$message,
            ],
            'buckets' => [
                'total' => $empty,
                'protected' => $empty,
                'persistent' => $empty,
                'regenerable' => $empty,
                'log' => $empty,
                'backup' => $empty,
                'temporary' => $empty,
                'unknown' => $empty,
            ],
            'largest' => [],
            'review_candidates' => [],
            'safe_candidates' => [],
            'errors' => $message === '' ? [] : [(string)$message],
            'meta' => [
                'last_scan_at' => '',
                'duration_seconds' => 0,
                'skipped_source_dirs' => [],
            ],
        ];
    }

    protected function emptyStorageJobs($message)
    {
        $row = [
            'status' => 'idle',
            'stage' => 'idle',
            'running' => false,
            'progress' => 0,
            'processed' => 0,
            'total' => 0,
            'message' => $message === '' ? '空闲' : ('不可用：' . $message),
            'started_at' => '',
            'finished_at' => '',
            'updated_at' => '',
        ];
        return ['scan' => $row, 'cleanup' => $row];
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
