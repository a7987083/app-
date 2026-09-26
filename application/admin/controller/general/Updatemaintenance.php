<?php

namespace app\admin\controller\general;

use app\common\controller\Backend;
use app\common\library\UpdateIntegrity;
use app\common\library\update\UpdateBackupManager;
use app\common\library\update\UpdateOps;

/**
 * Update operations center.
 *
 * Only manages data created by the online-update subsystem: status, history,
 * rollback backups, update cache and update lock diagnostics.
 */
class Updatemaintenance extends Backend
{
    protected $noNeedRight = ['index', 'panel'];

    public function index()
    {
        $ops = new UpdateOps(ROOT_PATH);
        $data = $ops->snapshot();
        $data['backup_manager'] = (new UpdateBackupManager(ROOT_PATH))->snapshot();

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
     * Cleanup updater-owned data only.
     *
     * mode=retention: existing conservative retention cleanup.
     * mode=backup_selected: delete only selected, non-protected rollback backups.
     * apply=0 previews, apply=1 performs the operation.
     */
    public function cleanup()
    {
        if (!$this->request->isPost()) {
            return json(['code' => 405, 'msg' => '仅允许 POST 请求', 'data' => '']);
        }

        $apply = intval($this->request->param('apply', 0)) === 1;
        $mode = trim((string)$this->request->param('mode', 'retention'));

        if ($mode === 'backup_selected') {
            $ids = $this->request->post('backup_ids/a', []);
            if (!is_array($ids)) {
                $ids = [];
            }
            try {
                $result = (new UpdateBackupManager(ROOT_PATH))->deleteSelected($ids, !$apply);
            } catch (\Exception $e) {
                return json(['code' => 400, 'msg' => $e->getMessage(), 'data' => '']);
            }
            return json([
                'code' => 200,
                'msg' => $apply ? '指定更新备份清理完成' : '指定更新备份清理预览完成',
                'data' => $result,
            ]);
        }

        if ($mode !== 'retention') {
            return json(['code' => 400, 'msg' => '未知清理模式', 'data' => '']);
        }

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
