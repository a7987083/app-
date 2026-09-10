<?php

namespace app\common\library;

/**
 * Shared blacklist record semantics.
 *
 * endtime = 0 means permanent.
 * usetime = 0 means the blacklist has not been hit yet.
 */
class BlacklistPolicy
{
    public static function insertData($udid, $addtime = null, $endtime = 0)
    {
        $addtime = $addtime === null ? time() : (int)$addtime;
        return [
            'udid' => trim((string)$udid),
            'addtime' => $addtime,
            'usetime' => 0,
            'endtime' => (int)$endtime,
        ];
    }

    public static function isActive(array $row, $now = null)
    {
        $now = $now === null ? time() : (int)$now;
        $endtime = isset($row['endtime']) ? (int)$row['endtime'] : 0;
        return $endtime === 0 || $endtime > $now;
    }

    public static function findActive(array $rows, $now = null)
    {
        foreach ($rows as $row) {
            if (self::isActive($row, $now)) {
                return $row;
            }
        }
        return null;
    }
}
