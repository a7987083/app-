<?php

function p182Assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL phase18_2_encryption_mode_contract_test: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
require_once $root . '/application/common/library/SourceEncryptionMode.php';
require_once $root . '/application/common/library/SourceConfigRepository.php';
require_once $root . '/application/common/library/AppStorePayload.php';

use app\common\library\SourceEncryptionMode;
use app\common\library\SourceConfigRepository;

p182Assert(SourceEncryptionMode::normalize('0') === '0', 'off mode normalization failed');
p182Assert(SourceEncryptionMode::normalize('1') === '1', 'normal mode normalization failed');
p182Assert(SourceEncryptionMode::normalize('2') === '2', 'v2 mode normalization failed');
p182Assert(SourceEncryptionMode::normalize('invalid') === '0', 'invalid mode must fail safe to off');
p182Assert(SourceEncryptionMode::runtimeEnabledValue('0') === '0', 'off must remain disabled for legacy callers');
p182Assert(SourceEncryptionMode::runtimeEnabledValue('1') === '1', 'normal mode must enable legacy encryption path');
p182Assert(SourceEncryptionMode::runtimeEnabledValue('2') === '1', 'v2 mode must enable legacy encryption path');

$rows = [
    ['name' => 'opencry', 'value' => '2'],
    ['name' => 'openblack', 'value' => '1'],
];
p182Assert(SourceConfigRepository::rawValueFromRows($rows, 'opencry') === '2', 'raw V2 selection must be preserved');
$mapped = SourceConfigRepository::mapRows($rows);
p182Assert(isset($mapped['opencry']) && $mapped['opencry'] === '1', 'V2 must map to enabled for old boolean callers');
p182Assert(isset($mapped['openblack']) && $mapped['openblack'] === '1', 'unrelated config changed unexpectedly');

$appStorePayloadSource = file_get_contents($root . '/application/common/library/AppStorePayload.php');
$migration = file_get_contents($root . '/release/sql/2026091713_source_encryption_mode.sql');
p182Assert($appStorePayloadSource !== false && strpos($appStorePayloadSource, 'SourceEncryptionMode::appType($headerValue)') !== false, 'AppStorePayload does not delegate protocol selection');
p182Assert($migration !== false && strpos($migration, "'radio'") !== false, 'opencry migration is not a radio selector');
p182Assert(strpos($migration, '\"0\":\"关闭\"') !== false, 'off choice missing');
p182Assert(strpos($migration, '\"1\":\"普通\"') !== false, 'normal choice missing');
p182Assert(strpos($migration, '\"2\":\"V2\"') !== false, 'V2 choice missing');
p182Assert(strpos($migration, "WHEN `value` IN ('0','1','2')") !== false, 'migration must preserve valid old/new values');

echo "OK phase18_2_encryption_mode_contract_test modes=passed compatibility=passed mutual_exclusion=passed\n";
