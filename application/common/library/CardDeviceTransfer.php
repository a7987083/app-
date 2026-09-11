<?php

namespace app\common\library;

use think\Db;

/**
 * Self-service active-card transfer from an old UDID to a replacement device.
 * Phase 11 adds total/daily/cooldown/IP limits and auditable transfer logs.
 */
class CardDeviceTransfer
{
    public static function isSupportedUdid($udid)
    {
        $length = strlen(trim((string)$udid));
        return $length === 25 || $length === 40;
    }

    public static function queryStatus($udid, $now = null)
    {
        AuthorizationSchema::ensure();
        $udid = trim((string)$udid);
        $now = $now === null ? time() : (int)$now;
        if (!self::isSupportedUdid($udid)) {
            return self::statusFail('UDID格式不正确');
        }

        $rows = Db::table('fa_kami')
            ->where('udid', $udid)
            ->where('jh', 1)
            ->where('endtime', '>', $now)
            ->order('id desc')
            ->select();
        if (!$rows) {
            return self::statusFail('当前UDID没有有效授权');
        }

        $values = SourceConfigRepository::mapRows(SourceConfigRepository::rows());
        $defaultQuota = AuthorizationPolicy::maxTransfers($values);
        $remaining = AuthorizationPolicy::remainingQuota($rows, $defaultQuota);
        $dailyLimit = AuthorizationPolicy::dailyTransfers($values);
        $cooldown = AuthorizationPolicy::cooldownSeconds($values);
        $today = strtotime(date('Y-m-d 00:00:00', $now));
        $dailyUsed = self::successCountForUdid($udid, $today);
        $lastSuccess = self::lastSuccessForUdid($udid);
        $nextAllowed = $lastSuccess > 0 ? $lastSuccess + $cooldown : 0;
        $canTransfer = $remaining > 0 && $dailyUsed < $dailyLimit && ($nextAllowed <= $now || $cooldown <= 0);

        return [
            'ok' => true,
            'message' => $remaining > 0 ? ($canTransfer ? '当前可换绑' : '仍有次数，但当前处于限制期') : '换绑次数已用完',
            'used' => null,
            'max' => null,
            'remaining' => $remaining,
            'daily_used' => $dailyUsed,
            'daily_limit' => $dailyLimit,
            'next_allowed_at' => $nextAllowed > $now ? $nextAllowed : 0,
            'can_transfer' => $canTransfer,
        ];
    }

    public static function transfer($code, $oldUdid, $newUdid, $ip = '', $now = null)
    {
        AuthorizationSchema::ensure();
        $code = trim((string)$code);
        $oldUdid = trim((string)$oldUdid);
        $newUdid = trim((string)$newUdid);
        $ip = trim((string)$ip);
        $now = $now === null ? time() : (int)$now;

        if ($code === '') {
            return self::fail('请输入卡密');
        }
        if (!self::isSupportedUdid($oldUdid) || !self::isSupportedUdid($newUdid)) {
            return self::fail('UDID格式不正确');
        }
        if ($oldUdid === $newUdid) {
            return self::fail('新旧UDID不能相同');
        }

        $values = SourceConfigRepository::mapRows(SourceConfigRepository::rows());
        $ipLimit = AuthorizationPolicy::ipHourlyAttempts($values);
        if ($ip !== '' && self::ipAttempts($ip, $now - 3600) >= $ipLimit) {
            self::logAttempt(0, $code, $oldUdid, $newUdid, $ip, 0, '请求过于频繁，请稍后再试', 0, 0, $now);
            return self::fail('请求过于频繁，请稍后再试');
        }

        Db::startTrans();
        try {
            $card = Db::table('fa_kami')
                ->where('kami', $code)
                ->where('udid', $oldUdid)
                ->where('jh', 1)
                ->lock(true)
                ->find();
            if (!$card) {
                Db::rollback();
                self::logAttempt(0, $code, $oldUdid, $newUdid, $ip, 0, '卡密与旧UDID不匹配', 0, 0, $now);
                return self::fail('卡密与旧UDID不匹配');
            }

            $oldBlackRows = Db::table('fa_black')->where('udid', $oldUdid)->order('id desc')->select();
            $newBlackRows = Db::table('fa_black')->where('udid', $newUdid)->order('id desc')->select();
            if (BlacklistPolicy::findActive($oldBlackRows, $now) || BlacklistPolicy::findActive($newBlackRows, $now)) {
                Db::rollback();
                self::logAttempt($card['id'], $code, $oldUdid, $newUdid, $ip, 0, '设备存在有效黑名单', 0, isset($card['transfer_count']) ? $card['transfer_count'] : 0, $now);
                return self::fail('该设备存在有效黑名单，无法自助换绑');
            }

            $activeRows = Db::table('fa_kami')
                ->where('udid', $oldUdid)
                ->where('jh', 1)
                ->where('endtime', '>', $now)
                ->lock(true)
                ->select();
            if (!$activeRows) {
                Db::rollback();
                self::logAttempt($card['id'], $code, $oldUdid, $newUdid, $ip, 0, '旧UDID当前没有有效授权', 0, isset($card['transfer_count']) ? $card['transfer_count'] : 0, $now);
                return self::fail('旧UDID当前没有有效授权');
            }

            $defaultQuota = AuthorizationPolicy::maxTransfers($values);
            $remaining = AuthorizationPolicy::remainingQuota($activeRows, $defaultQuota);
            if ($remaining <= 0) {
                Db::rollback();
                self::logAttempt($card['id'], $code, $oldUdid, $newUdid, $ip, 0, '换绑次数已用完', 0, 0, $now);
                return self::fail('换绑次数已用完');
            }

            $dailyLimit = AuthorizationPolicy::dailyTransfers($values);
            $today = strtotime(date('Y-m-d 00:00:00', $now));
            if (self::successCountForUdid($oldUdid, $today) >= $dailyLimit) {
                Db::rollback();
                self::logAttempt($card['id'], $code, $oldUdid, $newUdid, $ip, 0, '今日换绑次数已达上限', 0, $remaining, $now);
                return self::fail('今日换绑次数已达上限，请明天再试');
            }

            $cooldown = AuthorizationPolicy::cooldownSeconds($values);
            $lastSuccess = self::lastSuccessForUdid($oldUdid);
            if ($cooldown > 0 && $lastSuccess > 0 && $lastSuccess + $cooldown > $now) {
                Db::rollback();
                self::logAttempt($card['id'], $code, $oldUdid, $newUdid, $ip, 0, '换绑冷却中', 0, $remaining, $now);
                return self::fail('换绑冷却中，请稍后再试');
            }

            $newActive = Db::table('fa_kami')
                ->where('udid', $newUdid)
                ->where('jh', 1)
                ->where('endtime', '>', $now)
                ->lock(true)
                ->find();
            if ($newActive) {
                Db::rollback();
                self::logAttempt($card['id'], $code, $oldUdid, $newUdid, $ip, 0, '新UDID已有有效授权', 0, $remaining, $now);
                return self::fail('新UDID已有有效授权，请联系客服处理');
            }

            $maxEnd = CardEntitlementPolicy::activeEndTime($activeRows, $now);
            $newRemaining = max(0, $remaining - 1);
            $moved = 0;
            foreach ($activeRows as $row) {
                $updated = Db::table('fa_kami')->where('id', $row['id'])->update([
                    'udid' => $newUdid,
                    'transfer_count' => $newRemaining,
                ]);
                if ($updated === false) {
                    throw new \RuntimeException('换绑写入失败');
                }
                $moved++;
            }
            if ($moved <= 0) {
                throw new \RuntimeException('换绑写入失败');
            }

            if (!self::logAttempt($card['id'], $code, $oldUdid, $newUdid, $ip, 1, '换绑成功', $maxEnd, $newRemaining, $now)) {
                throw new \RuntimeException('换绑日志写入失败');
            }
            if (!AuthorizationEventLog::record('transfer', [
                'kami_id' => $card['id'],
                'kami' => $code,
                'udid' => $newUdid,
                'related_udid' => $oldUdid,
                'endtime' => $maxEnd,
                'ip' => $ip,
                'detail' => '设备换绑成功，剩余' . $newRemaining . '次',
                'addtime' => $now,
            ])) {
                throw new \RuntimeException('授权事件写入失败');
            }

            Db::commit();
            return [
                'ok' => true,
                'message' => '换绑成功',
                'moved' => $moved,
                'endtime' => $maxEnd,
                'transfer_count' => $newRemaining,
                'remaining' => $newRemaining,
            ];
        } catch (\Exception $e) {
            Db::rollback();
            error_log('[CardDeviceTransfer] ' . $e->getMessage());
            self::logAttempt(0, $code, $oldUdid, $newUdid, $ip, 0, '换绑失败', 0, 0, $now);
            return self::fail('换绑失败，请稍后重试');
        }
    }

    protected static function successCountForUdid($udid, $startTime)
    {
        $oldCount = Db::table('fa_card_transfer_log')->where('status', 1)->where('addtime', '>=', $startTime)->where('old_udid', $udid)->count();
        $newCount = Db::table('fa_card_transfer_log')->where('status', 1)->where('addtime', '>=', $startTime)->where('new_udid', $udid)->count();
        return (int)$oldCount + (int)$newCount;
    }

    protected static function lastSuccessForUdid($udid)
    {
        $old = (int)Db::table('fa_card_transfer_log')->where('status', 1)->where('old_udid', $udid)->max('addtime');
        $new = (int)Db::table('fa_card_transfer_log')->where('status', 1)->where('new_udid', $udid)->max('addtime');
        return max($old, $new);
    }

    protected static function ipAttempts($ip, $startTime)
    {
        return (int)Db::table('fa_card_transfer_log')->where('ip', $ip)->where('addtime', '>=', $startTime)->count();
    }

    protected static function logAttempt($kamiId, $code, $oldUdid, $newUdid, $ip, $status, $message, $endtime, $transferCount, $addtime)
    {
        try {
            return Db::table('fa_card_transfer_log')->insert([
                'kami_id' => (int)$kamiId,
                'kami' => (string)$code,
                'old_udid' => (string)$oldUdid,
                'new_udid' => (string)$newUdid,
                'ip' => (string)$ip,
                'status' => (int)$status,
                'message' => (string)$message,
                'endtime' => (int)$endtime,
                'transfer_count' => (int)$transferCount,
                'addtime' => (int)$addtime,
            ]) === 1;
        } catch (\Exception $e) {
            error_log('[CardDeviceTransfer::logAttempt] ' . $e->getMessage());
            return false;
        }
    }

    protected static function fail($message)
    {
        return ['ok' => false, 'message' => $message, 'moved' => 0, 'endtime' => 0, 'transfer_count' => 0, 'remaining' => 0];
    }

    protected static function statusFail($message)
    {
        return ['ok' => false, 'message' => $message, 'used' => 0, 'max' => 0, 'remaining' => 0, 'daily_used' => 0, 'daily_limit' => 0, 'next_allowed_at' => 0, 'can_transfer' => false];
    }
}
