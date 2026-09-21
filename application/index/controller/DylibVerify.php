<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use app\common\library\Ipa\DylibVerificationService;

class DylibVerify extends Frontend
{
    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';
    protected $layout = '';

    public function verify()
    {
        if (!$this->request->isPost()) {
            return json([
                'ok' => false,
                'code' => 'method_not_allowed',
                'action' => 'block',
                'message' => 'POST required',
            ], 405);
        }

        $payload = $this->request->post();
        if (!$payload) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        try {
            return json(DylibVerificationService::verify($payload, $this->request->ip()));
        } catch (\Exception $e) {
            error_log('[DylibVerify] ' . $e->getMessage());
            return json([
                'ok' => false,
                'code' => 'server_error',
                'action' => 'disable_feature',
                'offline_grace_seconds' => 900,
                'token' => '',
                'message' => 'verification service unavailable',
                'server_time' => time(),
            ], 503);
        }
    }
}
