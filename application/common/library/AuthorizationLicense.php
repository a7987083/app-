<?php

namespace app\common\library;

use think\Db;

class AuthorizationLicense
{
    public static function query($code, $udid, $now = null)
    {
        AuthorizationSchema::ensure();
        $code = trim((string)$code);
        $udid = trim((string)$udid);
        $now = $now === null ? time() : (int)$now;
        if ($code === '' || $udid === '') {
            return ['ok' => false, 'message' => '请输入卡密和UDID'];
        }
        if (!CardDeviceTransfer::isSupportedUdid($udid)) {
            return ['ok' => false, 'message' => 'UDID格式不正确'];
        }
        $card = Db::table('fa_kami')->where('kami', $code)->where('udid', $udid)->where('jh', 1)->find();
        if (!$card) {
            return ['ok' => false, 'message' => '卡密与UDID不匹配'];
        }
        $rows = Db::table('fa_kami')->where('udid', $udid)->where('jh', 1)->order('id desc')->select();
        $endtime = CardEntitlementPolicy::activeEndTime($rows, $now);
        $values = SourceConfigRepository::mapRows(SourceConfigRepository::rows());
        $max = AuthorizationPolicy::maxTransfers($values);
        $used = AuthorizationPolicy::usedTransfers($rows);
        $activeCards = 0;
        foreach ($rows as $row) {
            if (isset($row['endtime']) && (int)$row['endtime'] > $now) {
                $activeCards++;
            }
        }
        return [
            'ok' => true,
            'message' => $endtime > $now ? '授权有效' : '授权已到期',
            'active' => $endtime > $now,
            'endtime' => $endtime,
            'remaining_seconds' => $endtime > $now ? $endtime - $now : 0,
            'activated_cards' => count($rows),
            'active_cards' => $activeCards,
            'transfer_used' => $used,
            'transfer_max' => $max,
            'transfer_remaining' => AuthorizationPolicy::remainingTransfers($used, $max),
        ];
    }
}
