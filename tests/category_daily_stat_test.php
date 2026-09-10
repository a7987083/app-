<?php

require_once dirname(__DIR__) . '/application/common/library/CategoryDailyStat.php';

use app\common\library\CategoryDailyStat;

function statAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL category_daily_stat_test: {$message}\n");
        exit(1);
    }
}

date_default_timezone_set('Asia/Shanghai');

$aug11 = strtotime('2026-08-11 12:00:00');
$sep11 = strtotime('2026-09-11 12:00:00');
$sep12 = strtotime('2026-09-12 12:00:00');

statAssert(CategoryDailyStat::dateKey($aug11) === 20260811, 'August date key');
statAssert(CategoryDailyStat::dateKey($sep11) === 20260911, 'September date key');
statAssert(CategoryDailyStat::dateKey($aug11) !== CategoryDailyStat::dateKey($sep11), 'cross-month same day must differ');

$same = CategoryDailyStat::nextState(7, 20260911, $sep11);
statAssert($same['cs'] === 8 && $same['cstime'] === 20260911, 'same-day increment');

$nextDay = CategoryDailyStat::nextState(7, 20260911, $sep12);
statAssert($nextDay['cs'] === 1 && $nextDay['cstime'] === 20260912, 'next-day reset');

$crossMonth = CategoryDailyStat::nextState(12, 20260811, $sep11);
statAssert($crossMonth['cs'] === 1 && $crossMonth['cstime'] === 20260911, 'cross-month reset');

$legacy = CategoryDailyStat::nextState(12, 11, $sep11);
statAssert($legacy['cs'] === 1 && $legacy['cstime'] === 20260911, 'legacy day-of-month row migrates lazily');

echo "OK category_daily_stat_test\n";
