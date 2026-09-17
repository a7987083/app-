<?php

namespace app\index\controller;

use app\common\library\ApiEndpointRegistry;
use app\common\library\AppStorePayload;
use app\common\library\BlacklistPolicy;
use app\common\library\CardAccessPolicy;
use app\common\library\CardEntitlementPolicy;
use app\common\library\AuthorizationEventLog;
use app\common\library\AuthorizationPolicy;
use app\common\library\AuthorizationSchema;
use app\common\library\SourceAppRecord;
use app\common\library\SourceAppRepository;
use app\common\library\SourceConfigRepository;
use app\common\library\SourceEncryptionPolicy;
use app\common\library\SourceEncryptionProvider;
use app\common\library\SourceHttpClient;
use app\common\library\SourceLegacyCache;
use app\common\library\SourcePerformance;
use app\common\library\SourceResponse;
use app\common\library\TraceMonitorPolicy;
use think\Db;

class App
{
    protected $requestStartedAt = 0.0;
    protected $sourceBuildMs = 0.0;
    protected $lastEncryptionProvider = 'plain';

    public function list()
    {
        $guard = ApiEndpointRegistry::guard('appstore');
        if ($guard !== null) {
            return $guard;
        }

        $this->requestStartedAt = microtime(true);
        $input = json_decode(file_get_contents('php://input'), true);
        $traceValue = is_array($input) && array_key_exists('value', $input) ? $input['value'] : null;
        $appType = AppStorePayload::appType(isset($_SERVER['HTTP_APPSTORE']) ? $_SERVER['HTTP_APPSTORE'] : null);

        $configRows = SourceConfigRepository::rows();
        $configValues = SourceConfigRepository::mapRows($configRows);
        $openblack = array_key_exists('openblack', $configValues) ? $configValues['openblack'] : null;
        $openblack2 = array_key_exists('openblack2', $configValues) ? $configValues['openblack2'] : null;
        if ($traceValue) {
            $this->processTraceValue($traceValue, $openblack, $openblack2);
        }

        $opencry = array_key_exists('opencry', $configValues) ? $configValues['opencry'] : null;
        $udid = isset($_GET['udid']) ? trim((string)$_GET['udid']) : '';
        $kcode = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
        $now = time();
        $nowtime = date('Y-m-d H:i:s', $now);

        $black = $this->activeBlacklist($udid);
        if ($black) {
            $this->markBlacklistUsed($black);
            $this->emitPayload(
                AppStorePayload::blacklisted($udid, $nowtime),
                $opencry,
                $appType,
                JSON_UNESCAPED_UNICODE,
                false
            );
        }

        if ($kcode !== '') {
            return $this->activateCode($kcode, $udid, $now);
        }

        $kamiRows = $udid === ''
            ? []
            : Db::table('fa_kami')->where('udid', $udid)->order('id desc')->select();
        $appMap = $this->cardAppMap($kamiRows);
        $sourceAccess = CardAccessPolicy::sourceAccess($kamiRows, $appMap, $now);
        // Verification-only cards must behave exactly like guests in the
        // software-source response and therefore never reveal paid URLs.
        $mode = CardAccessPolicy::hasSourceCard($kamiRows) ? 'licensed' : 'guest';

        $buildStartedAt = microtime(true);
        $payload = $this->buildSourcePayload($configRows, $udid, $nowtime, $mode, $sourceAccess);
        $this->sourceBuildMs = (microtime(true) - $buildStartedAt) * 1000;
        if (is_object($payload)) {
            return $payload;
        }

        $this->emitPayload($payload, $opencry, $appType, 320, true);
    }

    protected function processTraceValue($traceValue, $openblack, $openblack2)
    {
        foreach (TraceMonitorPolicy::entries($traceValue) as $entry) {
            $autoBlack = $entry['config'] === 'openblack' ? $openblack : $openblack2;
            $this->processTraceUdid($entry['udid'], $autoBlack, $entry['identity']);
        }
    }

    protected function processTraceUdid($udid, $autoBlack, $identity)
    {
        if (!TraceMonitorPolicy::isSupportedUdid($udid)) {
            return;
        }

        if ($autoBlack == '1') {
            $black = $this->activeBlacklist($udid);
            if ($black) {
                Db::table('fa_monitor')->where(['udid' => $udid])->delete();
                return;
            }

            Db::startTrans();
            try {
                $inserted = Db::table('fa_black')->insert(BlacklistPolicy::insertData($udid));
                if ($inserted !== 1) {
                    Db::rollback();
                    return;
                }
                Db::table('fa_monitor')->where(['udid' => $udid])->delete();
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
            }
            return;
        }

        $black = $this->activeBlacklist($udid);
        if ($black) {
            return;
        }

        $monitor = Db::name('monitor')->where('udid', $udid)->find();
        if ($monitor) {
            Db::name('monitor')->where('udid', $udid)->inc('count', 1)->update();
        } else {
            Db::name('monitor')->insert([
                'udid' => $udid,
                'identity' => $identity,
                'count' => 1,
                'addtime' => time(),
            ]);
        }
    }

    protected function activeBlacklist($udid)
    {
        if ($udid === null || $udid === '') {
            return null;
        }
        $rows = Db::table('fa_black')->where('udid', $udid)->order('id desc')->select();
        return BlacklistPolicy::findActive($rows);
    }

    protected function markBlacklistUsed(array $black)
    {
        if (!empty($black['id']) && empty($black['usetime'])) {
            Db::table('fa_black')->where('id', $black['id'])->update(['usetime' => time()]);
        }
    }

    protected function buildSourcePayload(array $config, $udid, $nowtime, $mode, array $sourceAccess)
    {
        if (empty($config)) {
            return json(['code' => 0, 'msg' => '暂无站点数据']);
        }

        $list = SourceAppRepository::rows();
        if (empty($list)) {
            return json(['code' => 0, 'msg' => '暂无app数据']);
        }

        $info = AppStorePayload::siteInfo($config);
        $apps = AppStorePayload::apps($list, $mode, $sourceAccess);
        return AppStorePayload::source($info, $udid, $nowtime, $apps);
    }

    protected function emitPayload(array $payload, $opencry, $appType, $jsonFlags, $replaceMarkers)
    {
        if ($opencry == '1') {
            $jsonStartedAt = microtime(true);
            $json = json_encode($payload, $jsonFlags);
            $jsonMs = (microtime(true) - $jsonStartedAt) * 1000;
            $content = base64_encode($json);

            $encryptStartedAt = microtime(true);
            $encrypted = $this->encryptedSourcePayload($content, $appType);
            $encryptMs = (microtime(true) - $encryptStartedAt) * 1000;
            $body = SourceResponse::encryptedBody($appType, $encrypted, $replaceMarkers);

            $this->logSourcePerformance($payload, $appType, true, $jsonMs, $encryptMs, strlen((string)$json), strlen($content), strlen((string)$body));
            SourceResponse::send($body);
        }

        $body = SourceResponse::plainBody($payload, $jsonFlags, $replaceMarkers);
        $this->logSourcePerformance($payload, $appType, false, 0, 0, 0, 0, strlen((string)$body));
        SourceResponse::send($body);
    }

    protected function logSourcePerformance(array $payload, $appType, $encrypted, $jsonMs, $encryptMs, $jsonBytes, $inputBytes, $responseBytes)
    {
        $totalMs = $this->requestStartedAt > 0 ? (microtime(true) - $this->requestStartedAt) * 1000 : 0;
        SourcePerformance::log([
            'app_type' => $appType,
            'encrypted' => $encrypted ? 1 : 0,
            'provider' => $encrypted ? $this->lastEncryptionProvider : 'plain',
            'legacy_key_source' => $appType === 'appstore' && $encrypted ? SourceEncryptionProvider::lastLegacyKeySource() : 'n/a',
            'app_rows_source' => SourceAppRepository::lastSource(),
            'legacy_cache' => SourceLegacyCache::status(),
            'app_count' => isset($payload['apps']) && is_array($payload['apps']) ? count($payload['apps']) : 0,
            'source_build_ms' => round($this->sourceBuildMs, 2),
            'json_ms' => round((float)$jsonMs, 2),
            'encryption_ms' => round((float)$encryptMs, 2),
            'total_ms' => round((float)$totalMs, 2),
            'json_bytes' => (int)$jsonBytes,
            'encryption_input_bytes' => (int)$inputBytes,
            'response_bytes' => (int)$responseBytes,
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
        ]);
    }

    protected function encryptedSourcePayload($content, $appType)
    {
        $localRequested = SourceEncryptionPolicy::localRequested()
            && SourceEncryptionPolicy::localAllowedForAppType($appType);

        if ($localRequested) {
            try {
                $result = SourceEncryptionProvider::encryptEncodedContent($content, $appType);
                $this->lastEncryptionProvider = 'local';
                return $result;
            } catch (\Exception $e) {
                error_log('[App::encryptedSourcePayload] local encryption failed: ' . $e->getMessage());
                if (!SourceEncryptionPolicy::fallbackAllowed()) {
                    $this->lastEncryptionProvider = 'local-failed';
                    return false;
                }
            }
        }

        $result = SourceHttpClient::postForm(
            SourceEncryptionPolicy::nuosikeUrl($appType),
            ['content' => $content]
        );
        $this->lastEncryptionProvider = $localRequested ? 'nuosike-fallback' : 'nuosike';

        // Legacy curl_exec() failures serialized as boolean false. Preserve
        // that envelope while logging/timeout/TLS handling is improved.
        return $result['transport_ok'] ? $result['body'] : false;
    }

    /**
     * Activate one unused card. Stacking is isolated by entitlement chain:
     * - whole-source cards stack only with whole-source cards;
     * - verification cards stack only with verification cards;
     * - App cards stack only when their selected App set is exactly equal.
     */
    protected function activateCode($kcode, $udid, $now = null)
    {
        AuthorizationSchema::ensure();
        $now = $now === null ? time() : (int)$now;
        if ($udid === '') {
            return json(['code' => 0, 'msg' => '未获取设备UDID']);
        }

        try {
            $secretKey = $this->unlockSignKey();
        } catch (\Exception $e) {
            error_log('[App::unlockSignKey] signing key unavailable');
            return json(['code' => 0, 'msg' => '解锁签名配置不可用']);
        }

        Db::startTrans();
        try {
            $kdata = Db::table('fa_kami')
                ->where('kami', $kcode)
                ->order('id desc')
                ->lock(true)
                ->find();
            if (!$kdata) {
                Db::rollback();
                return json(['code' => 0, 'msg' => '解锁码不存在']);
            }

            $scope = CardAccessPolicy::scopeForRow($kdata);
            // All card codes are one-time activation credentials. Reusing any
            // activated code, including verification-only cards, is rejected.
            // Ongoing authorization checks belong to /index/index/apiface.
            if (intval($kdata['jh'])) {
                Db::rollback();
                return json(['code' => 0, 'msg' => '解锁码已使用']);
            }

            $targetApps = [];
            if ($scope === CardAccessPolicy::SCOPE_APPS) {
                $map = $this->cardAppMap([$kdata]);
                $targetApps = isset($map[(int)$kdata['id']]) ? $map[(int)$kdata['id']] : [];
                if (!$targetApps) {
                    Db::rollback();
                    return json(['code' => 0, 'msg' => '该指定App卡未配置授权App']);
                }
            }

            $allActive = Db::table('fa_kami')
                ->where('udid', $udid)
                ->where('jh', 1)
                ->where('endtime', '>', $now)
                ->lock(true)
                ->select();
            $existing = $this->stackRowsForScope($allActive, $scope, $targetApps);
            $wasStacked = CardEntitlementPolicy::activeEndTime($existing, $now) > $now;
            $state = CardEntitlementPolicy::activationState((int)$kdata['kmyp'], $existing, $now);
            $configValues = SourceConfigRepository::mapRows(SourceConfigRepository::rows());
            $defaultQuota = AuthorizationPolicy::maxTransfers($configValues);
            $transferQuota = $wasStacked
                ? AuthorizationPolicy::remainingQuota($existing, $defaultQuota)
                : max(0, isset($kdata['transfer_count']) ? (int)$kdata['transfer_count'] : $defaultQuota);

            $updated = Db::table('fa_kami')->where('id', $kdata['id'])->update([
                'udid' => $udid,
                'usetime' => $state['usetime'],
                'endtime' => $state['endtime'],
                'jh' => 1,
                'transfer_count' => $transferQuota,
            ]);
            if ($updated === false || (int)$updated <= 0) {
                throw new \RuntimeException('卡密激活写入失败');
            }

            $scopeName = CardAccessPolicy::scopeName($scope);
            $detail = ($wasStacked ? '卡密叠加授权：' : '卡密首次激活：') . $scopeName;
            if ($scope === CardAccessPolicy::SCOPE_APPS) {
                $detail .= ' AppID=' . implode(',', $targetApps);
            }
            if (!AuthorizationEventLog::record($wasStacked ? 'stack' : 'activate', [
                'kami_id' => $kdata['id'],
                'kami' => $kcode,
                'udid' => $udid,
                'duration' => CardEntitlementPolicy::durationSeconds((int)$kdata['kmyp']),
                'endtime' => $state['endtime'],
                'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
                'detail' => $detail,
                'addtime' => $now,
            ])) {
                throw new \RuntimeException('授权事件写入失败');
            }

            if ($scope === CardAccessPolicy::SCOPE_VERIFY) {
                $message = 'ok，验证卡激活成功';
            } elseif ($scope === CardAccessPolicy::SCOPE_APPS) {
                $message = 'ok，指定App授权成功';
            } else {
                $message = 'ok，解锁成功';
            }
            $response = $this->signedActivationPayload($message, $udid, $state['endtime'], $secretKey);

            Db::commit();
            return json($response);
        } catch (\InvalidArgumentException $e) {
            Db::rollback();
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        } catch (\Exception $e) {
            Db::rollback();
            error_log('[App::activateCode] ' . $e->getMessage());
            return json(['code' => 0, 'msg' => '激活失败，请稍后重试']);
        }
    }

    /**
     * Resolve the server-side HMAC key. Existing configured values are used as-is.
     * A blank/missing key is generated once and persisted so upgrades are safe by default.
     */
    protected function unlockSignKey()
    {
        $key = (string)SourceConfigRepository::get('unlock_sign_key', '', false);
        if ($key !== '') {
            return $key;
        }

        $generated = bin2hex(random_bytes(32));
        $row = Db::name('config')->where('name', 'unlock_sign_key')->find();
        if ($row) {
            $current = isset($row['value']) ? (string)$row['value'] : '';
            if ($current === '') {
                Db::name('config')
                    ->where('id', (int)$row['id'])
                    ->where('value', $current)
                    ->update(['value' => $generated]);
            }
        } else {
            try {
                Db::name('config')->insert([
                    'name' => 'unlock_sign_key',
                    'group' => 'basic',
                    'title' => '解锁签名KEY',
                    'tip' => '用于解锁响应HMAC-SHA256签名；留空时系统自动生成',
                    'type' => 'string',
                    'value' => $generated,
                    'content' => '',
                    'rule' => '',
                    'extend' => 'autocomplete="off"',
                ]);
            } catch (\Exception $e) {
                // A concurrent request may have inserted the unique config row first.
            }
        }

        SourceConfigRepository::forget();
        $key = (string)SourceConfigRepository::get('unlock_sign_key', '', false);
        if ($key === '') {
            throw new \RuntimeException('解锁签名KEY不可用');
        }
        return $key;
    }

    protected function signedActivationPayload($message, $udid, $expire, $secretKey)
    {
        $expire = (int)$expire;
        $ts = time();
        $nonce = bin2hex(random_bytes(8));
        $signData = $udid . '|' . $expire . '|' . $ts . '|' . $nonce;

        return [
            'code' => 0,
            'msg' => $message,
            'expire' => $expire,
            'ts' => $ts,
            'nonce' => $nonce,
            'sign' => hash_hmac('sha256', $signData, $secretKey),
        ];
    }

    /** Return [kami_id => [app_id, ...]] for the supplied card rows. */
    protected function cardAppMap(array $rows)
    {
        $ids = [];
        foreach ($rows as $row) {
            if (CardAccessPolicy::scopeForRow($row) === CardAccessPolicy::SCOPE_APPS && !empty($row['id'])) {
                $ids[(int)$row['id']] = true;
            }
        }
        if (!$ids) {
            return [];
        }

        try {
            $mapped = Db::table('fa_kami_app')
                ->where('kami_id', 'in', array_keys($ids))
                ->field('kami_id,app_id')
                ->select();
        } catch (\Exception $e) {
            error_log('[App::cardAppMap] ' . $e->getMessage());
            return [];
        }

        $result = [];
        foreach ($mapped as $row) {
            $kamiId = isset($row['kami_id']) ? (int)$row['kami_id'] : 0;
            $appId = isset($row['app_id']) ? (int)$row['app_id'] : 0;
            if ($kamiId > 0 && $appId > 0) {
                if (!isset($result[$kamiId])) {
                    $result[$kamiId] = [];
                }
                $result[$kamiId][] = $appId;
            }
        }
        foreach ($result as $kamiId => $appIds) {
            $result[$kamiId] = CardAccessPolicy::normalizeAppIds($appIds);
        }
        return $result;
    }

    protected function stackRowsForScope(array $rows, $scope, array $targetApps)
    {
        $scope = CardAccessPolicy::normalizeScope($scope);
        $result = [];
        $appMap = $scope === CardAccessPolicy::SCOPE_APPS ? $this->cardAppMap($rows) : [];
        foreach ($rows as $row) {
            if (CardAccessPolicy::scopeForRow($row) !== $scope) {
                continue;
            }
            if ($scope === CardAccessPolicy::SCOPE_APPS) {
                $kamiId = isset($row['id']) ? (int)$row['id'] : 0;
                $rowApps = isset($appMap[$kamiId]) ? $appMap[$kamiId] : [];
                if (!CardAccessPolicy::sameAppSet($rowApps, $targetApps)) {
                    continue;
                }
            }
            $result[] = $row;
        }
        return $result;
    }

    public function log()
    {
        $value = isset($_REQUEST['value']) ? $_REQUEST['value'] : null;
    }
}
