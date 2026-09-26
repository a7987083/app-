<?php

namespace app\admin\controller\general;

use app\common\controller\Backend;
use think\Db;

/**
 * Maintenance actions for the project API request log.
 * Authentication is still provided by Backend; the explicit confirmation
 * phrase prevents accidental or forged generic POSTs from clearing the table.
 */
class Apilogmaintenance extends Backend
{
    protected $noNeedRight = ['clear'];

    public function clear()
    {
        if (!$this->request->isPost()) {
            return json(['code' => 405, 'msg' => '仅允许 POST 请求', 'data' => '']);
        }

        $confirm = (string)$this->request->post('confirm', '');
        if (!hash_equals('DELETE_ALL_API_LOGS', $confirm)) {
            return json(['code' => 400, 'msg' => '删除确认无效', 'data' => '']);
        }

        try {
            $count = (int)Db::table('fa_api_request_log')->count();
            if ($count > 0) {
                Db::execute('DELETE FROM `fa_api_request_log`');
            }
        } catch (\Throwable $e) {
            return json(['code' => 500, 'msg' => '清空请求日志失败: ' . $e->getMessage(), 'data' => '']);
        }

        return json([
            'code' => 1,
            'msg' => 'API 请求日志已全部删除',
            'data' => ['deleted' => $count],
        ]);
    }
}
