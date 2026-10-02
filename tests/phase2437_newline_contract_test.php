<?php
require_once dirname(__DIR__) . '/application/common/library/SourceAppRecord.php';
require_once dirname(__DIR__) . '/application/common/library/AppStorePayload.php';
require_once dirname(__DIR__) . '/application/common/library/SourceLegacyCache.php';

use app\common\library\AppStorePayload;
use app\common\library\SourceLegacyCache;

function nlAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL phase2437_newline_contract_test: {$message}\n");
        exit(1);
    }
}

$row = [
    'id' => 1,
    'name' => 'Demo',
    'type' => 'default',
    'nickname' => '1.0',
    'updatetime' => 1700000000,
    'keywords' => 'first\\nsecond',
    'bt2b' => '0',
    'renewal_entry' => 0,
    'bt1a' => 'https://example.test/demo.ipa',
    'flag' => '0',
    'image' => 'https://example.test/icon.png',
    'bt1b' => 'ffffff',
    'bt2a' => '1',
];

$app = AppStorePayload::apps([$row], 'guest', false)[0];
nlAssert($app['versionDescription'] === "first\nsecond", 'mapped description must contain a real newline');
nlAssert(strpos($app['versionDescription'], '@@@') === false, 'mapped description must never contain @@@');

$payload = AppStorePayload::source([
    'name' => 'ZONOE',
    'message' => 'notice',
    'identifier' => 'id',
    'sourceURL' => 'https://example.test/source',
    'sourceicon' => 'https://example.test/icon.png',
    'payURL' => '',
    'unlockURL' => '',
], 'U', 'T', [$app]);

$json = SourceLegacyCache::buildEncryptedJson($payload, 320);
nlAssert(is_string($json), 'encrypted-path JSON builder must return JSON');
nlAssert(strpos($json, '@@@') === false, 'encrypted-path JSON must never contain @@@');
nlAssert(strpos($json, 'first\\nsecond') !== false, 'encrypted-path JSON must contain escaped newline');
$decoded = json_decode($json, true);
nlAssert(is_array($decoded), 'encrypted-path JSON must decode');
nlAssert($decoded['apps'][0]['versionDescription'] === "first\nsecond", 'decoded encrypted-path JSON must restore newline');

echo "OK phase2437_newline_contract_test\n";
