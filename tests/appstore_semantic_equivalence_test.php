<?php
require_once dirname(__DIR__) . '/application/common/library/SourceAppRecord.php';
require_once dirname(__DIR__) . '/application/common/library/AppStorePayload.php';

use app\common\library\AppStorePayload;

function semanticEqAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL appstore_semantic_equivalence_test: {$message}\n");
        exit(1);
    }
}

$row = [
    'name' => 'Demo',
    'type' => 'default',
    'nickname' => '1.2.3',
    'updatetime' => 1700000000,
    'keywords' => 'line1\\nline2',
    'bt2b' => '1',
    'bt1a' => 'https://example.test/app.ipa',
    'flag' => '0',
    'image' => 'https://example.test/icon.png',
    'bt1b' => 'abcdef',
    'bt2a' => '1234',
];
$guest = AppStorePayload::apps([$row], 'guest', false)[0];
semanticEqAssert($guest['type'] === 0, 'type mapping');
semanticEqAssert($guest['downloadURL'] === '', 'guest locked download');
semanticEqAssert($guest['versionDescription'] === 'line1@@@line2', 'description marker');
$licensed = AppStorePayload::apps([$row], 'licensed', true)[0];
semanticEqAssert($licensed['downloadURL'] === $row['bt1a'], 'licensed download');
semanticEqAssert($licensed['version'] === $row['nickname'], 'version alias');
semanticEqAssert($licensed['tintColor'] === $row['bt1b'], 'color alias');
semanticEqAssert($licensed['size'] === $row['bt2a'], 'size alias');

$row['bt2b'] = '2';
semanticEqAssert(AppStorePayload::apps([$row], 'licensed', false)[0]['downloadURL'] === $row['bt1a'], 'licensed strict lock=1 preserved');
semanticEqAssert(AppStorePayload::apps([$row], 'guest', false)[0]['downloadURL'] === '', 'guest truthy lock preserved');

echo "OK appstore_semantic_equivalence_test\n";
