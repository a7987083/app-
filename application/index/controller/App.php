<?php

namespace app\index\controller;

use app\common\library\AppStorePayload;
use app\common\library\BlacklistPolicy;
use app\common\library\SourceConfigRepository;
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
        $udid = isset($_GET['udid']) ? $_GET['udid'] : '';
        $kcode = isset($_GET['code']) ? $_GET['code'] : '';
        $nowtime = date('Y-m-d H:i:s');

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
            return $this->activateCode($kcode, $udid);
        }

        $kamiRows = Db::table('fa_kami')->where('udid', $udid)->order('id desc')->select();
        $mode = $kamiRows ? 'licensed' : 'guest';
        $allowLockedDownload = $kamiRows ? !(time() > $kamiRows[0]['endtime']) : false;

        $payload = $this->buildSourcePayload($configRows, $udid, $nowtime, $mode, $allowLockedDownload);
        if (is_object($payload)) {
            return $payload;
        }

        $this->emitPayload($payload, $opencry, $appType, 320, true);
    }

    /**
     * Handle the legacy base64 "添加者|破解者" tracking payload.
     */
    protected function processTraceValue($traceValue, $openblack, $openblack2)
    {
        $decoded = base64_decode($traceValue);
        $udidArr = explode('|', $decoded);
        $udid1 = isset($udidArr[0]) ? $udidArr[0] : null;
        $udid2 = isset($udidArr[1]) ? $udidArr[1] : null;

        $this->processTraceUdid($udid1, $openblack, '添加者');
        $this->processTraceUdid($udid2, $openblack2, '破解者');
    }

    protected function processTraceUdid($udid, $autoBlack, $identity)
    {
        if (!$udid || !$this->isLegacyUdid($udid)) {
            return;
        }

        if ($autoBlack == '1') {
            $black = $this->activeBlacklist($udid);
            if (!$black) {
                Db::table('fa_black')->insert(BlacklistPolicy::insertData($udid));
            }
            Db::table('fa_monitor')->where(['udid' => $udid])->delete();
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

    protected function isLegacyUdid($udid)
    {
        $length = strlen($udid);
        return $length === 25 || $length === 40;
    }

    /**
     * Build the exact public source payload from current fa_config/fa_category rows.
     * Returns a ThinkPHP JSON response object when the old controller would do so.
     */
    protected function buildSourcePayload(array $config, $udid, $nowtime, $mode, $allowLockedDownload)
    {
        if (empty($config)) {
            return json(['code' => 0, 'msg' => '暂无站点数据']);
        }

        $list = Db::table('fa_category')->where('status', 'normal')->order('weigh desc')->select();
        if (empty($list)) {
            return json(['code' => 0, 'msg' => '暂无app数据']);
        }

        $info = AppStorePayload::siteInfo($config);
        $apps = AppStorePayload::apps($list, $mode, $allowLockedDownload);
        return AppStorePayload::source($info, $udid, $nowtime, $apps);
    }

    /**
     * Preserve legacy plaintext/encrypted response envelopes and JSON flags.
     */
    protected function emitPayload(array $payload, $opencry, $appType, $jsonFlags, $replaceMarkers)
    {
        if ($opencry == '1') {
            $native = ['content' => base64_encode(json_encode($payload, $jsonFlags))];
            if ($appType === 'appstore_v2') {
                $res = $this->curl('https://api.nuosike.com/encrypt.php', $native);
                $return = ['appstore_v2' => $res];
            } else {
                $res = $this->curl('https://api.nuosike.com/api.php', $native);
                $return = ['appstore' => $res];
            }
            $json = json_encode($return);
            echo $replaceMarkers ? str_replace('@@@', '\\n', $json) : $json;
            die;
        }

        $payload = AppStorePayload::withoutRuntimeFields($payload);
        $json = json_encode($payload, $jsonFlags);
        echo $replaceMarkers ? str_replace('@@@', '\\n', $json) : $json;
        die;
    }

    protected function activateCode($kcode, $udid)
    {
        $codes = Db::table('fa_kami')->where('kami', $kcode)->order('id desc')->select();
        if (!$codes) {
            return json(['code' => 0, 'msg' => '解锁码不存在']);
        }

        $kdata = $codes[0];
        if (intval($kdata['jh'])) {
            return json(['code' => 0, 'msg' => '解锁码已使用']);
        }

        $existing = Db::table('fa_kami')->where('udid', $udid)->select();
        if (!empty($existing)) {
            foreach ($existing as $row) {
                if ($row['endtime'] < time()) {
                    Db::table('fa_kami')->where('id', $row['id'])->delete();
                }
            }
        }

        $startTime = time();
        list($useTime, $endTime) = $this->codeTimes(intval($kdata['kmyp']), $startTime);
        Db::table('fa_kami')->where('id', $kdata['id'])->update([
            'udid' => $udid,
            'usetime' => $useTime,
            'endtime' => $endTime,
            'jh' => 1,
        ]);
        return json(['code' => 0, 'msg' => 'ok，解锁成功']);
    }

    protected function codeTimes($type, $startTime)
    {
        switch ($type) {
            case 1:
                return [$startTime, $startTime + (86400 * 30)];
            case 2:
                return [$startTime, $startTime + (86400 * 30 * 3)];
            case 3:
                return [$startTime, $startTime + (86400 * 30 * 12)];
            case 4:
                return [$startTime, $startTime + 86400];
            case 5:
                return [$startTime, $startTime + (86400 * 7)];
            default:
                return [null, null];
        }
    }

    public function curl($url, $native)
    {
        $postData = http_build_query($native);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        $data = curl_exec($curl);
        curl_close($curl);
        return $data;
    }

    public function log()
    {
        $value = isset($_REQUEST['value']) ? $_REQUEST['value'] : null;
    }
}
