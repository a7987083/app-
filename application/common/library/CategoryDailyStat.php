<?php

namespace app\common\library;

use think\Db;

/**
 * Daily per-category hit counter.
 *
 * fa_category.cstime historically stored only date('d'), which made
 * different months share the same key. Keep the existing cs/cstime columns
 * and daily-count semantics, but store YYYYMMDD so the date is unambiguous.
 */
class CategoryDailyStat
{
    public static function dateKey($timestamp = null)
    {
        $timestamp = $timestamp === null ? time() : (int)$timestamp;
        return (int)date('Ymd', $timestamp);
    }

    public static function nextState($currentCount, $currentDateKey, $timestamp = null)
    {
        $today = self::dateKey($timestamp);
        if ((int)$currentDateKey === $today) {
            return [
                'cs' => (int)$currentCount + 1,
                'cstime' => $today,
            ];
        }

        return [
            'cs' => 1,
            'cstime' => $today,
        ];
    }

    /**
     * Record one hit atomically for a category row.
     *
     * The row lock keeps two simultaneous hits from both resetting/incrementing
     * from the same stale value.
     */
    public static function record($categoryId, $timestamp = null)
    {
        $categoryId = (int)$categoryId;
        if ($categoryId <= 0) {
            return false;
        }

        Db::startTrans();
        try {
            $row = Db::name('category')
                ->where('id', $categoryId)
                ->field('id,cs,cstime')
                ->lock(true)
                ->find();

            if (!$row) {
                Db::rollback();
                return false;
            }

            $next = self::nextState(
                isset($row['cs']) ? $row['cs'] : 0,
                isset($row['cstime']) ? $row['cstime'] : 0,
                $timestamp
            );

            $result = Db::name('category')
                ->where('id', $categoryId)
                ->update($next);

            if ($result === false) {
                throw new \RuntimeException('分类统计写入失败');
            }

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }
}
