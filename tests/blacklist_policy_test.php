<?php

require __DIR__ . '/../application/common/library/BlacklistPolicy.php';

use app\common\library\BlacklistPolicy;

function blacklistFail($message)
{
    fwrite(STDERR, "FAIL blacklist_policy_test: {$message}\n");
    exit(1);
}

function blacklistAssertSame($label, $expected, $actual)
{
    if ($expected !== $actual) {
        blacklistFail($label . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

$now = 1700000000;
$data = BlacklistPolicy::insertData('  TEST-UDID  ', $now, 0);
blacklistAssertSame('insert udid trim', 'TEST-UDID', $data['udid']);
blacklistAssertSame('insert addtime', $now, $data['addtime']);
blacklistAssertSame('insert usetime default', 0, $data['usetime']);
blacklistAssertSame('insert endtime permanent', 0, $data['endtime']);

blacklistAssertSame('permanent active', true, BlacklistPolicy::isActive(array('endtime' => 0), $now));
blacklistAssertSame('future active', true, BlacklistPolicy::isActive(array('endtime' => $now + 60), $now));
blacklistAssertSame('expired inactive', false, BlacklistPolicy::isActive(array('endtime' => $now - 1), $now));
blacklistAssertSame('boundary inactive', false, BlacklistPolicy::isActive(array('endtime' => $now), $now));

$rows = array(
    array('id' => 3, 'endtime' => $now - 10),
    array('id' => 2, 'endtime' => $now + 10),
    array('id' => 1, 'endtime' => 0),
);
$active = BlacklistPolicy::findActive($rows, $now);
blacklistAssertSame('find active id', 2, $active['id']);

$none = BlacklistPolicy::findActive(array(array('id' => 9, 'endtime' => $now - 1)), $now);
blacklistAssertSame('find no active', null, $none);

echo "OK blacklist_policy_test\n";
