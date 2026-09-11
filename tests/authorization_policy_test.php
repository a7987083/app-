<?php
require_once dirname(__DIR__) . '/application/common/library/AuthorizationPolicy.php';

use app\common\library\AuthorizationPolicy;

function authPolicyAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL authorization_policy_test: {$message}\n");
        exit(1);
    }
}

$values = [];
authPolicyAssert(AuthorizationPolicy::maxTransfers($values) === 3, 'default max transfers');
authPolicyAssert(AuthorizationPolicy::dailyTransfers($values) === 1, 'default daily transfers');
authPolicyAssert(AuthorizationPolicy::cooldownSeconds($values) === 3600, 'default cooldown');
authPolicyAssert(AuthorizationPolicy::ipHourlyAttempts($values) === 10, 'default IP hourly limit');
$rows = [
    ['transfer_count' => 0],
    ['transfer_count' => 2],
    ['transfer_count' => 1],
];
authPolicyAssert(AuthorizationPolicy::usedTransfers($rows) === 2, 'uses maximum transfer count in entitlement group');
authPolicyAssert(AuthorizationPolicy::remainingTransfers(2, 3) === 1, 'remaining transfer count');
authPolicyAssert(AuthorizationPolicy::remainingTransfers(5, 3) === 0, 'remaining never negative');
$values = ['unbind_max_count' => '5', 'unbind_daily_limit' => '2', 'unbind_cooldown_seconds' => '0', 'unbind_ip_hour_limit' => '20'];
authPolicyAssert(AuthorizationPolicy::maxTransfers($values) === 5, 'config max transfers');
authPolicyAssert(AuthorizationPolicy::dailyTransfers($values) === 2, 'config daily transfers');
authPolicyAssert(AuthorizationPolicy::cooldownSeconds($values) === 0, 'zero cooldown allowed');
authPolicyAssert(AuthorizationPolicy::ipHourlyAttempts($values) === 20, 'config IP hourly attempts');

echo "OK authorization_policy_test\n";
