<?php

namespace app\common\library;

/**
 * Card-duration and stacking semantics.
 * Each newly activated unused card extends the furthest active expiration.
 */
class CardEntitlementPolicy
{
    const DURATIONS = [
        1 => 2592000,
        2 => 7776000,
        3 => 31104000,
        4 => 86400,
        5 => 604800,
    ];

    public static function durationSeconds($type)
    {
        $type = (int)$type;
        return isset(self::DURATIONS[$type]) ? self::DURATIONS[$type] : 0;
    }

    public static function activeEndTime(array $rows, $now = null)
    {
        $now = $now === null ? time() : (int)$now;
        $max = 0;
        foreach ($rows as $row) {
            $endtime = isset($row['endtime']) ? (int)$row['endtime'] : 0;
            if ($endtime > $now && $endtime > $max) {
                $max = $endtime;
            }
        }
        return $max;
    }

    public static function stackBaseTime(array $rows, $now = null)
    {
        $now = $now === null ? time() : (int)$now;
        $activeEnd = self::activeEndTime($rows, $now);
        return $activeEnd > $now ? $activeEnd : $now;
    }

    public static function activationState($type, array $existingRows, $now = null)
    {
        $now = $now === null ? time() : (int)$now;
        $duration = self::durationSeconds($type);
        if ($duration <= 0) {
            throw new \InvalidArgumentException('卡密类型无效');
        }
        return [
            'usetime' => $now,
            'endtime' => self::stackBaseTime($existingRows, $now) + $duration,
        ];
    }
}
