<?php

namespace app\index\controller;

use app\common\library\AppStorePayload;
use app\common\library\BlacklistPolicy;
use app\common\library\CardEntitlementPolicy;
use app\common\library\AuthorizationEventLog;
use app\common\library\AuthorizationPolicy;
use app\common\library\AuthorizationSchema;
use app\common\library\SourceAppRecord;
use app\common\library\SourceConfigRepository;
use app\common\library\SourceHttpClient;
use app\common\library\SourceResponse;
use app\common\library\TraceMonitorPolicy;
use think\Db;

class App
{
    public function list()
    {
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
        $mode = $kamiRows ? 'licensed' : 'guest';
        $allowLockedDownload = $kamiRows
            ? CardEntitlementPolicy::activeEndTime($kamiRows, $now) > $now
            : false;

        $payload = $this->buildSourcePayload($configRows, $udid, $nowtime, $mode, $allowLockedDownload);
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

    protected function buildSourcePayload(array $config, $udid, $nowtime, $mode, $allowLockedDownload)
    {
        if (empty($config)) {
            return json(['code' => 0, 'msg' => '暂无站点数据']);
        }

        $list = Db::table('fa_category')
            ->field(implode(',', SourceAppRecord::publicSourceColumns()))
            ->where('status', 'normal')
            ->order('weigh desc')
            ->select();
        if (empty($list)) {
            return json(['code' => 0, 'msg' => '暂无app数据']);
        }

        $info = AppStorePayload::siteInfo($config);
        $apps = AppStorePayload::apps($list, $mode, $allowLockedDownload);
        return AppStorePayload::source($info, $udid, $nowtime, $apps);
    }

    protected function emitPayload(array $payload, $opencry, $appType, $jsonFlags, $replaceMarkers)
    {
        if ($opencry == '1') {
            $native = ['content' => base64_encode(json_encode($payload, $jsonFlags))];
            $url = $appType === 'appstore_v2'
                ? 'https://api.nuosike.com/encrypt.php'
                : 'https://api.nuosike.com/api.php';
            $result = SourceHttpClient::postForm($url, $native);

            // Legacy curl_exec() failures serialized as boolean false. Preserve
            // that envelope while logging/timeout/TLS handling is improved.
            $encrypted = $result['transport_ok'] ? $result['body'] : false;
            SourceResponse::send(
                SourceResponse::encryptedBody($appType, $encrypted, $replaceMarkers)
            );
        }

        SourceResponse::send(
            SourceResponse::plainBody($payload, $jsonFlags, $replaceMarkers)
        );
    }

    /**
     * Activate one unused card. If the same UDID already has active time, the
     * new duration is appended to the furthest active expiration.
     */
    protected function activateCode($kcode, $udid, $now = null)
    {
        AuthorizationSchema::ensure();
        $now = $now === null ? time() : (int)$now;
        if ($udid === '') {
            return json(['code' => 0, 'msg' => '未获取设备UDID']);
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
            if (intval($kdata['jh'])) {
                Db::rollback();
                return json(['code' => 0, 'msg' => '解锁码已使用']);
            }

            $existing = Db::table('fa_kami')
                ->where('udid', $udid)
                ->where('jh', 1)
                ->where('endtime', '>', $now)
                ->lock(true)
                ->select();
            $wasStacked = CardEntitlementPolicy::activeEndTime($existing, $now) > $now;
            $state = CardEntitlementPolicy::activationState((int)$kdata['kmyp'], $existing, $now);
            $transferCount = AuthorizationPolicy::usedTransfers($existing);

            $updated = Db::table('fa_kami')->where('id', $kdata['id'])->update([
                'udid' => $udid,
                'usetime' => $state['usetime'],
                'endtime' => $state['endtime'],
                'jh' => 1,
                'transfer_count' => $transferCount,
            ]);
            if ($updated === false || (int)$updated <= 0) {
                throw new \RuntimeException('卡密激活写入失败');
            }

            if (!AuthorizationEventLog::record($wasStacked ? 'stack' : 'activate', [
                'kami_id' => $kdata['id'],
                'kami' => $kcode,
                'udid' => $udid,
                'duration' => CardEntitlementPolicy::durationSeconds((int)$kdata['kmyp']),
                'endtime' => $state['endtime'],
                'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
                'detail' => $wasStacked ? '卡密叠加授权' : '卡密首次激活',
                'addtime' => $now,
            ])) {
                throw new \RuntimeException('授权事件写入失败');
            }

            Db::commit();
            return json(['code' => 0, 'msg' => 'ok，解锁成功']);
        } catch (\InvalidArgumentException $e) {
            Db::rollback();
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        } catch (\Exception $e) {
            Db::rollback();
            error_log('[App::activateCode] ' . $e->getMessage());
            return json(['code' => 0, 'msg' => '激活失败，请稍后重试']);
        }
    }

    public function log()
    {
        $value = isset($_REQUEST['value']) ? $_REQUEST['value'] : null;
    }
}
