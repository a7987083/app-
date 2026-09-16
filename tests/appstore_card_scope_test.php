<?php

require_once dirname(__DIR__) . '/application/common/library/SourceAppRecord.php';
require_once dirname(__DIR__) . '/application/common/library/CardAccessPolicy.php';
require_once dirname(__DIR__) . '/application/common/library/AppStorePayload.php';

use app\common\library\AppStorePayload;

function appScopeAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL appstore_card_scope_test: {$message}\n");
        exit(1);
    }
}

$rows = [
    [
        'id' => 12, 'type' => 'default', 'name' => 'Game A', 'nickname' => '1.0', 'keywords' => '',
        'bt1a' => 'https://example.test/a.ipa', 'bt1b' => '', 'bt2a' => 100, 'bt2b' => '1',
        'flag' => '0', 'image' => '', 'updatetime' => 100,
    ],
    [
        'id' => 15, 'type' => 'default', 'name' => 'Game B', 'nickname' => '1.0', 'keywords' => '',
        'bt1a' => 'https://example.test/b.ipa', 'bt1b' => '', 'bt2a' => 100, 'bt2b' => '1',
        'flag' => '0', 'image' => '', 'updatetime' => 100,
    ],
];

$appOnly = AppStorePayload::apps($rows, 'licensed', ['unlock_all' => false, 'app_ids' => [12]]);
appScopeAssert($appOnly[0]['downloadURL'] === 'https://example.test/a.ipa', 'selected app must be downloadable');
appScopeAssert($appOnly[1]['downloadURL'] === '', 'unselected paid app must stay locked');
appScopeAssert(!array_key_exists('id', $appOnly[0]), 'internal app id must not change public protocol');

$verifyOnly = AppStorePayload::apps($rows, 'guest', ['unlock_all' => false, 'app_ids' => []]);
appScopeAssert($verifyOnly[0]['downloadURL'] === '' && $verifyOnly[1]['downloadURL'] === '', 'verification-only card must expose no paid URLs');

$wholeSource = AppStorePayload::apps($rows, 'licensed', ['unlock_all' => true, 'app_ids' => []]);
appScopeAssert($wholeSource[0]['downloadURL'] !== '' && $wholeSource[1]['downloadURL'] !== '', 'whole-source card must unlock all paid URLs');

$legacyTrue = AppStorePayload::apps($rows, 'licensed', true);
appScopeAssert($legacyTrue[0]['downloadURL'] !== '' && $legacyTrue[1]['downloadURL'] !== '', 'legacy boolean true must remain compatible');

echo "OK appstore_card_scope_test\n";
