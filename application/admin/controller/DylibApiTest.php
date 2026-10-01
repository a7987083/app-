<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\Ipa\DylibDeviceAuthService;
use app\common\library\Ipa\DylibRuntimeConfigService;
use app\common\library\Ipa\DylibVerificationService;
use app\common\library\Ipa\DylibVerifyAudit;
use think\Db;

class DylibApiTest extends Backend
{
    protected $noNeedRight = [];

    public function index()
    {
        $this->view->assign('dylibs', Db::name('dylib')->field('id,dylib_key,name,enabled')->order('id desc')->select());
        return $this->view->fetch();
    }

    public function configTest()
    {
        $this->requirePostRequest();
        $key = trim((string)$this->request->post('dylib_key', ''));
        $started = microtime(true);
        try {
            $result = DylibRuntimeConfigService::bootstrap($key);
            return json($this->wrap('config', '/index/dylib_verify/config?dylib_key=' . rawurlencode($key), $result, $started));
        } catch (\Exception $e) {
            return json($this->wrap('config', '/index/dylib_verify/config?dylib_key=' . rawurlencode($key), [
                'ok' => false,
                'code' => 'config_unavailable',
                'message' => $e->getMessage(),
            ], $started));
        }
    }

    public function challengeTest()
    {
        $this->requirePostRequest();
        $payload = $this->decodePayload();
        $started = microtime(true);
        try {
            $result = DylibDeviceAuthService::issueChallenge($payload);
            return json($this->wrap('challenge', '/index/dylib_verify/challenge', $result, $started));
        } catch (\Exception $e) {
            return json($this->wrap('challenge', '/index/dylib_verify/challenge', [
                'ok' => false,
                'code' => 'challenge_unavailable',
                'message' => $e->getMessage(),
            ], $started));
        }
    }

    public function canonicalTest()
    {
        $this->requirePostRequest();
        $payload = $this->decodePayload();
        $challengeId = trim((string)(isset($payload['challenge_id']) ? $payload['challenge_id'] : ''));
        $challenge = trim((string)(isset($payload['challenge']) ? $payload['challenge'] : ''));
        return json([
            'ok' => true,
            'canonical' => DylibDeviceAuthService::canonicalProof($payload, $challengeId, $challenge),
            'algorithm' => 'ecdsa-p256-sha256',
            'signature_transport' => 'Base64(DER ECDSA signature)',
        ]);
    }

    public function verifyTest()
    {
        $this->requirePostRequest();
        $payload = $this->decodePayload();
        $started = microtime(true);
        $ip = $this->request->ip();
        try {
            $result = DylibVerificationService::verify($payload, $ip);
            if (isset($result['code']) && (string)$result['code'] === 'bad_request') {
                DylibVerifyAudit::logBadRequest($payload, $ip, $started, 'bad_request', 'block');
            }
            return json($this->wrap('verify', '/index/dylib_verify/verify', $result, $started));
        } catch (\Exception $e) {
            return json($this->wrap('verify', '/index/dylib_verify/verify', [
                'ok' => false,
                'code' => 'server_error',
                'action' => 'disable_feature',
                'message' => $e->getMessage(),
            ], $started));
        }
    }

    protected function decodePayload()
    {
        $raw = trim((string)$this->request->post('payload', ''));
        if ($raw === '') {
            return [];
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $this->error('JSON 格式无效：' . json_last_error_msg());
        }
        return $payload;
    }

    protected function wrap($stage, $endpoint, array $result, $started)
    {
        return [
            'ok' => true,
            'stage' => $stage,
            'endpoint' => $endpoint,
            'elapsed_ms' => (int)round((microtime(true) - $started) * 1000),
            'response' => $result,
        ];
    }

    protected function requirePostRequest()
    {
        if (!$this->request->isPost()) {
            $this->error('POST required');
        }
    }
}
