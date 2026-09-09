<?php

require __DIR__ . '/../application/index/service/AppSourceBuilder.php';

use app\index\service\AppSourceBuilder;

date_default_timezone_set('Asia/Shanghai');

function failTest($message)
{
    fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
    exit(1);
}

function assertSameValue($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        fwrite(STDERR, "Expected:\n" . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, "Actual:\n" . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function legacyBuildApps(array $list, $state)
{
    $data = array();

    foreach ($list as $key => $val) {
        if ($state === AppSourceBuilder::LICENSE_ACTIVE || $state === AppSourceBuilder::LICENSE_EXPIRED) {
            $lock = $val['bt2b'];
            if ($lock != '1') {
                $downloadURL = $val['bt1a'];
            } else {
                if ($state === AppSourceBuilder::LICENSE_EXPIRED) {
                    $downloadURL = '';
                } else {
                    $downloadURL = $val['bt1a'];
                }
            }
        } else {
            $downloadURL = $val['bt2b'] ? '' : $val['bt1a'];
        }

        if ($val['type'] == 'default') {
            $val['type'] = 0;
        }

        $data[$key]['name'] = $val['name'];
        $data[$key]['type'] = $val['type'];
        $data[$key]['version'] = $val['nickname'];
        $data[$key]['versionDate'] = date('Y-m-d\TH:i:s\+08:00', $val['updatetime']);
        $data[$key]['versionDescription'] = str_replace('\\n', '@@@', $val['keywords']);
        $data[$key]['lock'] = $val['bt2b'];
        $data[$key]['downloadURL'] = $downloadURL;
        $data[$key]['isLanZouCloud'] = $val['flag'];
        $data[$key]['iconURL'] = $val['image'];
        $data[$key]['tintColor'] = $val['bt1b'];
        $data[$key]['size'] = $val['bt2a'];
    }

    return $data;
}

function legacyBuildSiteInfo(array $config)
{
    $info = array();
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

function legacyBuildPayload(array $info, array $apps, $udid, $time)
{
    return array(
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
    );
}

$list = array(
    10 => array(
        'name' => 'Free App',
        'type' => 'default',
        'nickname' => '1.0',
        'updatetime' => 1704067200,
        'keywords' => 'line1\\nline2',
        'bt2b' => '0',
        'bt1a' => 'https://example.test/free.ipa',
        'flag' => '0',
        'image' => 'https://example.test/free.png',
        'bt1b' => '018084',
        'bt2a' => '100',
    ),
    20 => array(
        'name' => 'Paid App',
        'type' => '2',
        'nickname' => '2.0',
        'updatetime' => 1704153600,
        'keywords' => 'paid',
        'bt2b' => '1',
        'bt1a' => 'https://example.test/paid.ipa',
        'flag' => '1',
        'image' => 'https://example.test/paid.png',
        'bt1b' => 'ff0000',
        'bt2a' => '200',
    ),
    30 => array(
        'name' => 'Legacy Lock Value',
        'type' => '5',
        'nickname' => '3.0',
        'updatetime' => 1704240000,
        'keywords' => 'legacy',
        'bt2b' => '2',
        'bt1a' => 'https://example.test/legacy.ipa',
        'flag' => '0',
        'image' => 'https://example.test/legacy.png',
        'bt1b' => '',
        'bt2a' => '300',
    ),
);

$config = array(
    array('name' => 'message', 'value' => 'hello'),
    array('name' => 'sourceURL', 'value' => 'https://example.test/appstore'),
    array('name' => 'name', 'value' => 'Source'),
    array('name' => 'identifier', 'value' => 'source-id'),
    array('name' => 'sourceicon', 'value' => 'https://example.test/icon.png'),
    array('name' => 'payURL', 'value' => 'https://example.test/pay'),
    array('name' => 'unlockURL', 'value' => 'https://example.test/unlock'),
    array('name' => 'ignored', 'value' => 'must-not-leak'),
);

$states = array(
    AppSourceBuilder::LICENSE_NONE,
    AppSourceBuilder::LICENSE_ACTIVE,
    AppSourceBuilder::LICENSE_EXPIRED,
);

foreach ($states as $state) {
    $legacyApps = legacyBuildApps($list, $state);
    $newApps = AppSourceBuilder::buildApps($list, $state);
    assertSameValue($legacyApps, $newApps, 'apps mapping differs for state ' . $state);

    $legacyInfo = legacyBuildSiteInfo($config);
    $newInfo = AppSourceBuilder::buildSiteInfo($config);
    assertSameValue($legacyInfo, $newInfo, 'site info mapping differs');

    $legacyPayload = legacyBuildPayload($legacyInfo, $legacyApps, 'UDID-TEST', '2026-09-09 08:00:00');
    $newPayload = AppSourceBuilder::buildPayload($newInfo, $newApps, 'UDID-TEST', '2026-09-09 08:00:00');
    assertSameValue($legacyPayload, $newPayload, 'payload differs for state ' . $state);

    $legacyPlain = $legacyPayload;
    unset($legacyPlain['UDID']);
    unset($legacyPlain['Time']);
    $legacyJson = str_replace('@@@', '\\n', json_encode($legacyPlain, 320));

    $newPlain = $newPayload;
    unset($newPlain['UDID']);
    unset($newPlain['Time']);
    $newJson = str_replace('@@@', '\\n', json_encode($newPlain, 320));

    assertSameValue($legacyJson, $newJson, 'plain JSON differs for state ' . $state);

    $legacyEncryptedInput = base64_encode(json_encode($legacyPayload, 320));
    $newEncryptedInput = base64_encode(json_encode($newPayload, 320));
    assertSameValue($legacyEncryptedInput, $newEncryptedInput, 'encryption input differs for state ' . $state);
}

assertSameValue('', AppSourceBuilder::buildApps($list, AppSourceBuilder::LICENSE_NONE)[20]['downloadURL'], 'paid URL must be hidden without license');
assertSameValue('https://example.test/paid.ipa', AppSourceBuilder::buildApps($list, AppSourceBuilder::LICENSE_ACTIVE)[20]['downloadURL'], 'paid URL must be visible with active license');
assertSameValue('', AppSourceBuilder::buildApps($list, AppSourceBuilder::LICENSE_EXPIRED)[20]['downloadURL'], 'paid URL must be hidden with expired license');
assertSameValue('', AppSourceBuilder::buildApps($list, AppSourceBuilder::LICENSE_NONE)[30]['downloadURL'], 'legacy truthy lock must stay hidden without license');
assertSameValue('https://example.test/legacy.ipa', AppSourceBuilder::buildApps($list, AppSourceBuilder::LICENSE_EXPIRED)[30]['downloadURL'], 'legacy non-1 lock behavior must remain unchanged when expired');

echo "PASS: AppSourceBuilder legacy-equivalence regression tests" . PHP_EOL;
