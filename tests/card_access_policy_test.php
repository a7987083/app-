<?php

require_once dirname(__DIR__) . '/application/common/library/CardAccessPolicy.php';

use app\common\library\CardAccessPolicy;

function cardAccessAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL card_access_policy_test: {$message}\n");
        exit(1);
    }
}

$now = 1000000;
cardAccessAssert(CardAccessPolicy::scopeForRow([]) === CardAccessPolicy::SCOPE_SOURCE, 'legacy card must default to whole-source scope');
cardAccessAssert(CardAccessPolicy::scopeName(2) === '仅验证', 'verify scope label');
cardAccessAssert(CardAccessPolicy::normalizeAppIds([5, '3', 5, 0, -1]) === [3, 5], 'app id normalization');
cardAccessAssert(CardAccessPolicy::sameAppSet([9, 4], [4, 9, 9]), 'app set equality');

$rows = [
    ['id' => 1, 'jh' => 1, 'endtime' => $now + 100, 'card_scope' => CardAccessPolicy::SCOPE_VERIFY],
    ['id' => 2, 'jh' => 1, 'endtime' => $now + 200, 'card_scope' => CardAccessPolicy::SCOPE_APPS],
    ['id' => 3, 'jh' => 1, 'endtime' => $now - 1, 'card_scope' => CardAccessPolicy::SCOPE_SOURCE],
];
$access = CardAccessPolicy::sourceAccess($rows, [2 => [12, 27]], $now);
cardAccessAssert($access['unlock_all'] === false, 'verification card must not unlock source');
cardAccessAssert($access['app_ids'] === [12, 27], 'app card target union');
cardAccessAssert(CardAccessPolicy::allowsApp($access, 12), 'target app must be allowed');
cardAccessAssert(!CardAccessPolicy::allowsApp($access, 15), 'untargeted app must stay locked');

$rows[] = ['id' => 4, 'jh' => 1, 'endtime' => $now + 500, 'card_scope' => CardAccessPolicy::SCOPE_SOURCE];
$all = CardAccessPolicy::sourceAccess($rows, [2 => [12, 27]], $now);
cardAccessAssert($all['unlock_all'] === true, 'active whole-source card must unlock all paid apps');
cardAccessAssert(CardAccessPolicy::allowsApp($all, 999), 'whole-source permission must allow arbitrary app id');

$verifyOnly = [
    ['id' => 10, 'jh' => 1, 'endtime' => $now + 100, 'card_scope' => CardAccessPolicy::SCOPE_VERIFY],
];
cardAccessAssert(CardAccessPolicy::hasSourceCard($verifyOnly) === false, 'verify-only cards must not put source into licensed mode');

echo "OK card_access_policy_test\n";
