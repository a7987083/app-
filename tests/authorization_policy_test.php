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
authPolicyAssert(AuthorizationPolicy::maxTransfers($values) === 100, 'default transfer quota');
authPolicyAssert(AuthorizationPolicy::dailyTransfers($values) === 1, 'default daily transfers');
authPolicyAssert(AuthorizationPolicy::cooldownSeconds($values) === 3600, 'default cooldown');
authPolicyAssert(AuthorizationPolicy::ipHourlyAttempts($values) === 10, 'default IP hourly limit');
$rows = [
    ['transfer_count' => 97],
    ['transfer_count' => 99],
    ['transfer_count' => 98],
];
authPolicyAssert(AuthorizationPolicy::remainingQuota($rows, 100) === 99, 'active entitlement uses highest admin-adjusted remaining quota');
authPolicyAssert(AuthorizationPolicy::remainingQuota([], 50) === 50, 'empty entitlement uses fallback quota');
authPolicyAssert(AuthorizationPolicy::remainingTransfers(2, 100) === 98, 'legacy remaining helper');
$values = ['unbind_max_count' => '250', 'unbind_daily_limit' => '2', 'unbind_cooldown_seconds' => '0', 'unbind_ip_hour_limit' => '20'];
authPolicyAssert(AuthorizationPolicy::maxTransfers($values) === 250, 'config default transfer quota');
authPolicyAssert(AuthorizationPolicy::dailyTransfers($values) === 2, 'config daily transfers');
authPolicyAssert(AuthorizationPolicy::cooldownSeconds($values) === 0, 'zero cooldown allowed');
authPolicyAssert(AuthorizationPolicy::ipHourlyAttempts($values) === 20, 'config IP hourly attempts');

echo "OK authorization_policy_test\n";
