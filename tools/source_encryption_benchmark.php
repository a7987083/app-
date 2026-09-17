<?php

require_once __DIR__ . '/../application/common/library/SourceEncryptionProvider.php';

use app\common\library\SourceEncryptionProvider;

$counts = [1000, 5000, 10000, 20000, 50000];
foreach ($argv as $arg) {
    if (strpos($arg, '--counts=') === 0) {
        $raw = substr($arg, strlen('--counts='));
        $parsed = array_values(array_filter(array_map('intval', explode(',', $raw)), function ($v) {
            return $v > 0;
        }));
        if ($parsed) {
            $counts = $parsed;
        }
    }
}

function benchJson($count)
{
    $app = json_encode([
        'name' => 'Benchmark Game',
        'type' => 0,
        'version' => '1.0.0',
        'versionDate' => '2026-09-17T00:00:00+08:00',
        'versionDescription' => str_repeat('benchmark-description-', 6),
        'lock' => '1',
        'downloadURL' => 'https://example.invalid/files/game.ipa?token=benchmark',
        'isLanZouCloud' => '0',
        'iconURL' => 'https://example.invalid/icons/game.png',
        'tintColor' => '007AFF',
        'size' => '123456789',
    ], JSON_UNESCAPED_SLASHES);

    $items = array_fill(0, $count, $app);
    return '{"name":"bench","message":"","identifier":"bench","sourceURL":"https://example.invalid/source","sourceicon":"","payURL":"","unlockURL":"","UDID":"BENCHMARK","Time":"2026-09-17 00:00:00","apps":['
        . implode(',', $items)
        . ']}';
}

function elapsedMs($startedAt)
{
    return round((microtime(true) - $startedAt) * 1000, 2);
}

$key = 'UthbkJctpzDlLle';
echo "count\tjson_mb\tappstore_ms\tappstore_mb_s\tv2_ms\tv2_mb_s\tlegacy_out_mb\tlegacy_gzip1_mb\tlegacy_gzip1_ms\tv2_out_mb\tv2_gzip1_mb\tv2_gzip1_ms\tpeak_mb\n";

foreach ($counts as $count) {
    $json = benchJson($count);
    $bytes = strlen($json);
    $mb = $bytes / 1048576;

    $started = microtime(true);
    $legacy = SourceEncryptionProvider::encryptLegacyJson($json, $key);
    $legacyMs = elapsedMs($started);

    $started = microtime(true);
    $v2 = SourceEncryptionProvider::encryptV2Json($json, $key);
    $v2Ms = elapsedMs($started);

    $started = microtime(true);
    $legacyGzip = gzencode($legacy, 1);
    $legacyGzipMs = elapsedMs($started);

    $started = microtime(true);
    $v2Gzip = gzencode($v2, 1);
    $v2GzipMs = elapsedMs($started);

    $legacyRate = $legacyMs > 0 ? round($mb / ($legacyMs / 1000), 2) : 0;
    $v2Rate = $v2Ms > 0 ? round($mb / ($v2Ms / 1000), 2) : 0;

    echo implode("\t", [
        $count,
        round($mb, 2),
        $legacyMs,
        $legacyRate,
        $v2Ms,
        $v2Rate,
        round(strlen($legacy) / 1048576, 2),
        round(strlen($legacyGzip) / 1048576, 2),
        $legacyGzipMs,
        round(strlen($v2) / 1048576, 2),
        round(strlen($v2Gzip) / 1048576, 2),
        $v2GzipMs,
        round(memory_get_peak_usage(true) / 1048576, 2),
    ]) . "\n";

    unset($json, $legacy, $v2, $legacyGzip, $v2Gzip);
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
}
