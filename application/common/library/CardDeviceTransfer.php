<?php

namespace app\common\library;

use think\Db;

/**
 * Self-service active-card transfer from an old UDID to a replacement device.
 */
class CardDeviceTransfer
{
    public static function isSupportedUdid($udid)
    {
        $length = strlen(trim((string)$udid));
        return $length === 25 || $length === 40;
    }

    public static function transfer($code, $oldUdid, $newUdid, $now = null)
    {
        $code = trim((string)$code);
        $oldUdid = trim((string)$oldUdid);
        $newUdid = trim((string)$newUdid);
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
                return self::fail('卡密与旧UDID不匹配');
            }

            $oldBlackRows = Db::table('fa_black')->where('udid', $oldUdid)->order('id desc')->select();
            $newBlackRows = Db::table('fa_black')->where('udid', $newUdid)->order('id desc')->select();
            if (BlacklistPolicy::findActive($oldBlackRows, $now) || BlacklistPolicy::findActive($newBlackRows, $now)) {
                Db::rollback();
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
                return self::fail('旧UDID当前没有有效授权');
            }

            $newActive = Db::table('fa_kami')
                ->where('udid', $newUdid)
                ->where('jh', 1)
                ->where('endtime', '>', $now)
                ->lock(true)
                ->find();
            if ($newActive) {
                Db::rollback();
                return self::fail('新UDID已有有效授权，请联系客服处理');
            }

            $maxEnd = CardEntitlementPolicy::activeEndTime($activeRows, $now);
            $affected = Db::table('fa_kami')
                ->where('udid', $oldUdid)
                ->where('jh', 1)
                ->where('endtime', '>', $now)
                ->update(['udid' => $newUdid]);
            if ($affected === false || (int)$affected <= 0) {
                throw new \RuntimeException('换绑写入失败');
            }

            Db::commit();
            return [
                'ok' => true,
                'message' => '换绑成功',
                'moved' => (int)$affected,
                'endtime' => $maxEnd,
            ];
        } catch (\Exception $e) {
            Db::rollback();
            error_log('[CardDeviceTransfer] ' . $e->getMessage());
            return self::fail('换绑失败，请稍后重试');
        }
    }

    protected static function fail($message)
    {
        return ['ok' => false, 'message' => $message, 'moved' => 0, 'endtime' => 0];
    }
}
