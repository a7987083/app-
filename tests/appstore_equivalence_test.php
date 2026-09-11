<?php
require __DIR__ . '/../application/common/library/SourceAppRecord.php';
require __DIR__ . '/../application/common/library/AppStorePayload.php';

use app\common\library\AppStorePayload;

function fail($label, $legacy, $current)
{
    fwrite(STDERR, "FAIL: {$label}\nLEGACY=" . var_export($legacy, true) . "\nCURRENT=" . var_export($current, true) . "\n");
    exit(1);
}

function assertSameValue($label, $legacy, $current)
{
    if ($legacy !== $current) {
        fail($label, $legacy, $current);
    }
}

function legacyInfo(array $config)
{
    $info = [
        'name' => null,
        'message' => null,
        'identifier' => null,
        'sourceURL' => null,
        'sourceicon' => null,
        'payURL' => null,
        'unlockURL' => null,
    ];
    foreach ($config as $val) {
        if ($val['name'] == 'name') $info['name'] = $val['value'];
        if ($val['name'] == 'message') $info['message'] = $val['value'];
        if ($val['name'] == 'identifier') $info['identifier'] = $val['value'];
        if ($val['name'] == 'sourceURL') $info['sourceURL'] = $val['value'];
        if ($val['name'] == 'sourceicon') $info['sourceicon'] = $val['value'];
        if ($val['name'] == 'payURL') $info['payURL'] = $val['value'];
        if ($val['name'] == 'unlockURL') $info['unlockURL'] = $val['value'];
    }
    return $info;
}

function legacyApps(array $rows, $mode, $allowLocked)
{
    $data = [];
    foreach ($rows as $key => $val) {
        $type = $val['type'];
        if ($type == 'default') {
            $type = 0;
        }
        if ($mode === 'licensed') {
            $lock = $val['bt2b'];
            if ($lock != '1') {
                $downloadURL = $val['bt1a'];
            } else {
                $downloadURL = $allowLocked ? $val['bt1a'] : '';
            }
        } else {
            $downloadURL = $val['bt2b'] ? '' : $val['bt1a'];
        }
        $data[$key] = [
            'name' => $val['name'],
            'type' => $type,
            'version' => $val['nickname'],
            'versionDate' => date('Y-m-d\TH:i:s\+08:00', $val['updatetime']),
            'versionDescription' => str_replace('\\n', '@@@', $val['keywords']),
            'lock' => $val['bt2b'],
            'downloadURL' => $downloadURL,
            'isLanZouCloud' => $val['flag'],
            'iconURL' => $val['image'],
            'tintColor' => $val['bt1b'],
            'size' => $val['bt2a'],
        ];
    }
    return $data;
}

function legacySource(array $info, $udid, $time, array $apps)
{
    return [
        'name' => $info['name'],
        'message' => $info['message'],
        'identifier' => $info['identifier'],
        'sourceURL' => $info['sourceURL'],
        'sourceicon' => $info['sourceicon'],
        'payURL' => $info['payURL'],
        'unlockURL' => $info['unlockURL'],
        'UDID' => $udid,
        'Time' => $time,
        'apps' => $apps,
    ];
}

$config = [
    ['name' => 'name', 'value' => 'Zonoe'],
    ['name' => 'message', 'value' => "公告A\n公告B"],
    ['name' => 'identifier', 'value' => 'source-id'],
    ['name' => 'sourceURL', 'value' => 'https://example.test/appstore'],
    ['name' => 'sourceicon', 'value' => 'https://example.test/icon.png'],
    ['name' => 'payURL', 'value' => 'https://example.test/pay'],
    ['name' => 'unlockURL', 'value' => 'https://example.test/unlock'],
    ['name' => 'other', 'value' => 'ignored'],
];

$rows = [
    [
        'name' => 'Free', 'type' => 'default', 'nickname' => '1.0', 'updatetime' => 1700000000,
        'keywords' => 'A\\nB', 'bt2b' => '0', 'bt1a' => 'https://example.test/free.ipa',
        'flag' => '0', 'image' => 'i1', 'bt1b' => '112233', 'bt2a' => '100',
    ],
    [
        'name' => 'Paid', 'type' => '2', 'nickname' => '2.0', 'updatetime' => 1700001000,
        'keywords' => 'C', 'bt2b' => '1', 'bt1a' => 'https://example.test/paid.ipa',
        'flag' => '1', 'image' => 'i2', 'bt1b' => '445566', 'bt2a' => '200',
    ],
    [
        'name' => 'OddLock', 'type' => '5', 'nickname' => '3.0', 'updatetime' => 1700002000,
        'keywords' => 'D', 'bt2b' => '2', 'bt1a' => 'https://example.test/odd.ipa',
        'flag' => '0', 'image' => 'i3', 'bt1b' => '', 'bt2a' => '300',
    ],
];

$legacyInfo = legacyInfo($config);
$currentInfo = AppStorePayload::siteInfo($config);
assertSameValue('site info', $legacyInfo, $currentInfo);

foreach ([['guest', false], ['licensed', false], ['licensed', true]] as $case) {
    list($mode, $allow) = $case;
    $legacyApps = legacyApps($rows, $mode, $allow);
    $currentApps = AppStorePayload::apps($rows, $mode, $allow);
    assertSameValue("apps {$mode}/" . ($allow ? 'allow' : 'deny'), $legacyApps, $currentApps);

    $legacyPayload = legacySource($legacyInfo, 'U', 'T', $legacyApps);
    $currentPayload = AppStorePayload::source($currentInfo, 'U', 'T', $currentApps);
    assertSameValue("payload {$mode}/" . ($allow ? 'allow' : 'deny'), $legacyPayload, $currentPayload);

    $legacyPlain = $legacyPayload;
    unset($legacyPlain['UDID'], $legacyPlain['Time']);
    $currentPlain = AppStorePayload::withoutRuntimeFields($currentPayload);
    assertSameValue("plain {$mode}/" . ($allow ? 'allow' : 'deny'), $legacyPlain, $currentPlain);

    $legacyJson = str_replace('@@@', '\\n', json_encode($legacyPlain, 320));
    $currentJson = AppStorePayload::encodeSourceJson($currentPlain, 320);
    assertSameValue("json {$mode}/" . ($allow ? 'allow' : 'deny'), $legacyJson, $currentJson);
}

assertSameValue('wrapper default', 'appstore', AppStorePayload::appType(null));
assertSameValue('wrapper legacy other', 'appstore', AppStorePayload::appType('anything'));
assertSameValue('wrapper v2', 'appstore_v2', AppStorePayload::appType('v2'));

echo "OK appstore_equivalence_test\n";
