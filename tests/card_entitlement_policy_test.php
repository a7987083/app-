<?php
require_once dirname(__DIR__) . '/application/common/library/CardEntitlementPolicy.php';

use app\common\library\CardEntitlementPolicy;

function entitlementAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL card_entitlement_policy_test: {$message}\n");
        exit(1);
    }
}

$now = 1000000;
entitlementAssert(CardEntitlementPolicy::durationSeconds(1) === 86400 * 30, 'month duration');
entitlementAssert(CardEntitlementPolicy::durationSeconds(5) === 86400 * 7, 'week duration');
entitlementAssert(CardEntitlementPolicy::durationSeconds(99) === 0, 'invalid duration');

$rows = [
    ['endtime' => $now - 1],
    ['endtime' => $now + 100],
    ['endtime' => $now + 500],
];
entitlementAssert(CardEntitlementPolicy::activeEndTime($rows, $now) === $now + 500, 'furthest active expiration');
$state = CardEntitlementPolicy::activationState(1, $rows, $now);
entitlementAssert($state['usetime'] === $now, 'activation use time');
entitlementAssert($state['endtime'] === $now + 500 + 86400 * 30, 'month stacks after current active time');
$empty = CardEntitlementPolicy::activationState(4, [], $now);
entitlementAssert($empty['endtime'] === $now + 86400, 'first card starts now');

echo "OK card_entitlement_policy_test\n";
