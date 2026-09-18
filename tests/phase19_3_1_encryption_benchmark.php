<?php

function p1931Fail($message)
{
    fwrite(STDERR, "FAIL phase19_3_1_encryption_benchmark: {$message}\n");
    exit(1);
}

function p1931Assert($condition, $message)
{
    if (!$condition) {
        p1931Fail($message);
    }
}

$root = dirname(__DIR__);
require_once $root . '/application/common/library/SourceEncryptionProvider.php';

$key = 'phase1931-benchmark-key';
$payload = str_repeat('{"name":"LOADTEST","downloadURL":"https://example.invalid/a.ipa"}', 100000);
$bytes = strlen($payload);
p1931Assert($bytes >= 5000000, 'benchmark payload must be at least 5 MB');

$cacheDir = sys_get_temp_dir() . '/zonoe_phase1931_keystream_' . getmypid();
@mkdir($cacheDir, 0700, true);
putenv('SOURCE_LEGACY_KEYSTREAM_CACHE_DIR=' . $cacheDir);

putenv('SOURCE_LEGACY_KEYSTREAM_CACHE=0');
$started = microtime(true);
$baseline = \app\common\library\SourceEncryptionProvider::encryptLegacyJson($payload, $key);
$baselineMs = (microtime(true) - $started) * 1000;

putenv('SOURCE_LEGACY_KEYSTREAM_CACHE=1');
$started = microtime(true);
$warm = \app\common\library\SourceEncryptionProvider::encryptLegacyJson($payload, $key);
$warmMs = (microtime(true) - $started) * 1000;

$started = microtime(true);
$hit = \app\common\library\SourceEncryptionProvider::encryptLegacyJson($payload, $key);
$hitMs = (microtime(true) - $started) * 1000;

p1931Assert($baseline === $warm, 'keystream warm output differs from reference RC4');
p1931Assert($baseline === $hit, 'keystream cache-hit output differs from reference RC4');

$encoded = \app\common\library\SourceEncryptionProvider::encryptEncodedContent(base64_encode($payload), 'appstore');
$direct = \app\common\library\SourceEncryptionProvider::encryptJson($payload, 'appstore');
p1931Assert($encoded === $direct, 'direct JSON provider output differs from encoded compatibility entrypoint');
p1931Assert($direct === $baseline, 'direct JSON provider output differs from legacy reference output');
p1931Assert($hitMs > 0, 'cache-hit timing is invalid');

$speedup = $baselineMs / $hitMs;
p1931Assert($speedup >= 2.0, 'legacy RC4 cache-hit speedup below 2x: ' . round($speedup, 2) . 'x');

$files = glob($cacheDir . '/*');
p1931Assert(is_array($files) && count($files) >= 1, 'keystream cache file was not created');

foreach ((array)$files as $file) {
    @unlink($file);
}
@rmdir($cacheDir);

fwrite(STDOUT, sprintf(
    "OK phase19_3_1_encryption_benchmark bytes=%d baseline_ms=%.2f warm_ms=%.2f hit_ms=%.2f speedup=%.2fx equivalent=yes direct_json=yes\n",
    $bytes,
    $baselineMs,
    $warmMs,
    $hitMs,
    $speedup
));
