<?php

namespace app\admin\controller\general;

use app\common\controller\Backend;
use app\common\library\UpdateIntegrity;
use app\common\library\update\UpdateOps;

/**
 * Phase 14.3 update operations diagnostics.
 */
class Updatemaintenance extends Backend
{
    protected $noNeedRight = ['index', 'panel', 'cleanup'];

    /**
     * JSON diagnostics endpoint.
     */
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

    /**
     * Human-facing operations panel. The page reads data from index().
     */
    public function panel()
    {
        return $this->view->fetch();
    }

    /**
     * Safe cleanup endpoint. Default is dry-run; apply=1 performs deletion.
     */
    public function cleanup()
    {
        $apply = intval($this->request->param('apply', 0)) === 1;
        $ops = new UpdateOps(ROOT_PATH);
        $result = $ops->cleanup(!$apply);
        return json([
            'code' => 200,
            'msg' => $apply ? '安全清理完成' : '安全清理预览完成',
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
