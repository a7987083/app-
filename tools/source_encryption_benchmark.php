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

/** Candidate only: same legacy ASCII-key semantics with an exact-size output buffer. */
function fastRc4Ascii($data, $keyText)
{
    $key = [];
    $keyTextLength = strlen($keyText);
    for ($n = 0; $n < $keyTextLength; $n++) {
        $key[] = ord($keyText[$n]);
    }
    $keyLength = count($key);
    if ($keyLength === 0) {
        throw new RuntimeException('key is empty');
    }

    $state = range(0, 255);
    $j = 0;
    for ($i = 0; $i < 256; $i++) {
        $j = ($j + $state[$i] + $key[$i % $keyLength]) & 0xff;
        $tmp = $state[$i];
        $state[$i] = $state[$j];
        $state[$j] = $tmp;
    }

    $length = strlen($data);
    if ($length === 0) {
        return '';
    }
    $output = str_repeat("\0", $length);
    $i = 0;
    $j = 0;
    for ($n = 0; $n < $length; $n++) {
        $i = ($i + 1) & 0xff;
        $j = ($j + $state[$i]) & 0xff;
        $tmp = $state[$i];
        $state[$i] = $state[$j];
        $state[$j] = $tmp;
        $k = $state[($state[$i] + $state[$j]) & 0xff];
        $output[$n] = chr(ord($data[$n]) ^ $k);
    }
    return $output;
}

/** Candidate only: same V2 variable-width codec with a worst-case preallocated buffer. */
function fastV2Codec($raw)
{
    $alphabet = SourceEncryptionProvider::V2_ALPHABET;
    $length = strlen($raw);
    if ($length === 0) {
        return '';
    }
    $output = str_repeat("\0", (int)ceil($length * 1.61) + 4);
    $out = 0;
    $buffer = 0;
    $bitCount = 0;

    for ($i = 0; $i < $length; $i++) {
        $buffer |= ord($raw[$i]) << $bitCount;
        $bitCount += 8;
        while ($bitCount >= 5) {
            $value5 = $buffer & 31;
            if ($value5 === 30 || $value5 === 31) {
                $output[$out++] = $alphabet[$value5];
                $buffer >>= 5;
                $bitCount -= 5;
                continue;
            }
            if ($bitCount < 6) {
                break;
            }
            $value6 = $buffer & 63;
            $output[$out++] = $alphabet[$value6];
            $buffer >>= 6;
            $bitCount -= 6;
        }
    }

    if ($bitCount > 0) {
        $value5 = $buffer & 31;
        $output[$out++] = ($bitCount >= 5 && ($value5 === 30 || $value5 === 31))
            ? $alphabet[$value5]
            : $alphabet[$buffer & 63];
    }
    return substr($output, 0, $out);
}

$key = 'UthbkJctpzDlLle';
echo "count\tjson_mb\tappstore_ms\tappstore_mb_s\tv2_ms\tv2_mb_s\tprealloc_rc4_ms\tprealloc_codec_ms\tprealloc_v2_core_ms\tlegacy_out_mb\tv2_out_mb\tpeak_mb\n";

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
    $candidatePayload = fastRc4Ascii($json, $key);
    $candidateRc4Ms = elapsedMs($started);
    if (base64_encode($candidatePayload) !== $legacy) {
        throw new RuntimeException('preallocated RC4 output mismatch');
    }

    $candidateRaw = pack('V', SourceEncryptionProvider::V2_MAGIC)
        . pack('V', 256)
        . str_repeat('R', 256)
        . pack('V', strlen($candidatePayload))
        . $candidatePayload;
    $referenceCodec = SourceEncryptionProvider::encodeV2Codec($candidateRaw);
    $started = microtime(true);
    $candidateCodec = fastV2Codec($candidateRaw);
    $candidateCodecMs = elapsedMs($started);
    if ($candidateCodec !== $referenceCodec) {
        throw new RuntimeException('preallocated V2 codec output mismatch');
    }

    $legacyRate = $legacyMs > 0 ? round($mb / ($legacyMs / 1000), 2) : 0;
    $v2Rate = $v2Ms > 0 ? round($mb / ($v2Ms / 1000), 2) : 0;

    echo implode("\t", [
        $count,
        round($mb, 2),
        $legacyMs,
        $legacyRate,
        $v2Ms,
        $v2Rate,
        $candidateRc4Ms,
        $candidateCodecMs,
        round($candidateRc4Ms + $candidateCodecMs, 2),
        round(strlen($legacy) / 1048576, 2),
        round(strlen($v2) / 1048576, 2),
        round(memory_get_peak_usage(true) / 1048576, 2),
    ]) . "\n";

    unset($json, $legacy, $v2, $candidatePayload, $candidateRaw, $referenceCodec, $candidateCodec);
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
}
