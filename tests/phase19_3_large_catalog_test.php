<?php

function p193LargeFail($message)
{
    fwrite(STDERR, "FAIL phase19_3_large_catalog_test: {$message}\n");
    exit(1);
}

function p193LargeAssert($condition, $message)
{
    if (!$condition) {
        p193LargeFail($message);
    }
}

$root = dirname(__DIR__);
require_once $root . '/application/common/library/SourceAppRecord.php';
require_once $root . '/application/common/library/AppStorePayload.php';

$rows = [];
$count = 20000;
$now = time();
for ($i = 1; $i <= $count; $i++) {
    $rows[] = [
        'id' => $i,
        'type' => ($i % 5) + 1,
        'name' => 'LOADTEST-' . $i,
        'nickname' => '1.0.' . $i,
        'keywords' => 'Phase19.3 load test app ' . $i . '\\nsecond line',
        'bt1a' => 'https://example.invalid/loadtest/' . $i . '.ipa',
        'bt1b' => '#336699',
        'bt2a' => (string)(100000000 + $i),
        'bt2b' => ($i % 3 === 0) ? '1' : '0',
        'flag' => '0',
        'image' => 'https://example.invalid/icon/' . $i . '.png',
        'updatetime' => $now - $i,
    ];
}

$started = microtime(true);
$apps = \app\common\library\AppStorePayload::apps(
    $rows,
    'licensed',
    ['unlock_all' => true, 'app_ids' => []]
);
$mapMs = (microtime(true) - $started) * 1000;

p193LargeAssert(count($apps) === $count, '20k catalog mapping count mismatch');

$info = [
    'name' => 'Phase19.3',
    'message' => 'large catalog',
    'identifier' => 'phase19.3',
    'sourceURL' => 'https://example.invalid/appstore',
    'sourceicon' => 'https://example.invalid/icon.png',
    'payURL' => 'https://example.invalid/pay',
    'unlockURL' => 'https://example.invalid/unlock',
];
$payload = \app\common\library\AppStorePayload::source($info, 'TEST-UDID-123', '2026-09-18 07:00:00', $apps);

$started = microtime(true);
$baseline = json_encode($payload, 320);
$baselineMs = (microtime(true) - $started) * 1000;
p193LargeAssert(is_string($baseline), 'baseline json_encode failed');

require_once $root . '/application/common/library/SourceLegacyCache.php';
$started = microtime(true);
$optimized = \app\common\library\SourceLegacyCache::buildEncryptedJson($payload, 320);
$optimizedMs = (microtime(true) - $started) * 1000;

p193LargeAssert(is_string($optimized), 'optimized encrypted JSON build failed');
p193LargeAssert($optimized === $baseline, 'optimized encrypted JSON must be byte-identical to legacy json_encode');
p193LargeAssert(strlen($baseline) > 1000000, '20k catalog payload unexpectedly small');

$peakMb = memory_get_peak_usage(true) / 1048576;
p193LargeAssert($peakMb < 256, '20k catalog peak memory exceeded 256 MB');

fwrite(STDOUT, sprintf(
    "OK phase19_3_large_catalog_test apps=%d bytes=%d map_ms=%.2f baseline_json_ms=%.2f optimized_build_ms=%.2f peak_mb=%.2f\n",
    $count,
    strlen($baseline),
    $mapMs,
    $baselineMs,
    $optimizedMs,
    $peakMb
));
