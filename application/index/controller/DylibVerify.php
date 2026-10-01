<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use app\common\library\Ipa\DylibDeviceAuthService;
use app\common\library\Ipa\DylibRuntimeConfigService;
use app\common\library\Ipa\DylibVerificationService;
use app\common\library\Ipa\DylibVerifyAudit;

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

    /** Issue a short-lived one-time challenge for protocol v3 device proof. */
    public function challenge()
    {
        if (!$this->request->isPost()) {
            return json([
                'ok' => false,
                'code' => 'method_not_allowed',
                'message' => 'POST required',
            ], 405);
        }
        try {
            return json(DylibDeviceAuthService::issueChallenge($this->payload()));
        } catch (\Exception $e) {
            error_log('[DylibVerify/challenge] ' . $e->getMessage());
            return json([
                'ok' => false,
                'code' => 'challenge_unavailable',
                'message' => 'challenge service unavailable',
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

        $started = microtime(true);
        $payload = $this->payload();
        $ip = $this->request->ip();
        try {
            $result = DylibVerificationService::verify($payload, $ip);
            if (isset($result['code']) && (string)$result['code'] === 'bad_request') {
                DylibVerifyAudit::logBadRequest($payload, $ip, $started, 'bad_request', 'block');
            }
            return json($result);
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

    protected function payload()
    {
        $payload = $this->request->post();
        if ($payload) {
            return $payload;
        }
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
