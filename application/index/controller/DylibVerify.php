<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use app\common\library\Ipa\DylibRuntimeConfigService;
use app\common\library\Ipa\DylibVerificationService;

class DylibVerify extends Frontend
{
    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';
    protected $layout = '';

    /**
     * Lightweight discovery endpoint used by independently hosted bootstrap
     * anchors. The returned runtime endpoint list is signed per Dylib.
     */
    public function config()
    {
        $dylibKey = trim((string)$this->request->request('dylib_key', ''));
        try {
            return json(DylibRuntimeConfigService::bootstrap($dylibKey));
        } catch (\Exception $e) {
            error_log('[DylibVerify/config] ' . $e->getMessage());
            return json([
                'ok' => false,
                'code' => 'config_unavailable',
                'message' => 'runtime configuration unavailable',
                'server_time' => time(),
            ], 503);
        }
    }

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
