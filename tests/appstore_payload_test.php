<?php
require __DIR__ . '/../application/common/library/SourceAppRecord.php';
require __DIR__ . '/../application/common/library/AppStorePayload.php';

use app\common\library\AppStorePayload;

function same($expected, $actual, $label)
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$label}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

same('appstore', AppStorePayload::appType(null), 'default app type');
same('appstore', AppStorePayload::appType('v1'), 'non-v2 app type');
same('appstore_v2', AppStorePayload::appType('v2'), 'v2 app type');

$config = [
    ['name' => 'name', 'value' => '测试源'],
    ['name' => 'message', 'value' => '公告'],
    ['name' => 'sourceURL', 'value' => 'https://example.test/appstore'],
    ['name' => 'ignored', 'value' => 'x'],
];
$info = AppStorePayload::siteInfo($config);
same('测试源', $info['name'], 'site name');
same('公告', $info['message'], 'site message');
same(null, $info['unlockURL'], 'missing config remains null');

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
same(0, $guest['type'], 'default type maps to zero');
same('', $guest['downloadURL'], 'guest cannot download locked app');
same('line1@@@line2', $guest['versionDescription'], 'newline marker mapping');

$expired = AppStorePayload::apps([$row], 'licensed', false)[0];
same('', $expired['downloadURL'], 'expired license cannot download lock=1');

$valid = AppStorePayload::apps([$row], 'licensed', true)[0];
same('https://example.test/app.ipa', $valid['downloadURL'], 'valid license downloads lock=1');

$rowLock2 = $row;
$rowLock2['bt2b'] = '2';
$licensedLock2 = AppStorePayload::apps([$rowLock2], 'licensed', false)[0];
same('https://example.test/app.ipa', $licensedLock2['downloadURL'], 'licensed branch preserves strict lock=1 behavior');
$guestLock2 = AppStorePayload::apps([$rowLock2], 'guest', false)[0];
same('', $guestLock2['downloadURL'], 'guest branch preserves truthy lock behavior');

$payload = AppStorePayload::source($info, 'UDID123', '2026-09-11 01:00:00', [$valid]);
same('UDID123', $payload['UDID'], 'runtime UDID present before plain output');
$plain = AppStorePayload::withoutRuntimeFields($payload);
same(false, array_key_exists('UDID', $plain), 'plain output removes UDID');
same(false, array_key_exists('Time', $plain), 'plain output removes Time');

$json = AppStorePayload::encodeSourceJson($plain, 320);
if (strpos($json, 'line1\\nline2') === false) {
    fwrite(STDERR, "FAIL: encoded source JSON must preserve escaped newline\n{$json}\n");
    exit(1);
}

$black = AppStorePayload::blacklisted('U', 'T');
same('已被源主拉黑', $black['name'], 'blacklist source name');
same('', $black['apps'][0]['downloadURL'], 'blacklist app download hidden');

echo "OK appstore_payload_test\n";
