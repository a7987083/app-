<?php

require_once __DIR__ . '/../application/common/library/SourceEncryptionProvider.php';

use app\common\library\SourceEncryptionProvider;

function bkeyAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL source_encryption_bkey_cache_test: {$message}\n");
        exit(1);
    }
}

$cacheFile = sys_get_temp_dir() . '/zonoe_bkey_cache_' . getmypid() . '_' . mt_rand(1000, 9999) . '.json';
$key = 'UthbkJctpzDlLle';
$json = '{"name":"cache-test","apps":[]}';

file_put_contents($cacheFile, json_encode([
    'version' => 1,
    'fetched_at' => time(),
    'bkey' => base64_encode($key),
]));
putenv('SOURCE_LEGACY_BKEY_CACHE_FILE=' . $cacheFile);
putenv('SOURCE_LEGACY_BKEY_TTL=3600');
putenv('SOURCE_LEGACY_BKEY_STALE_TTL=86400');

$expected = SourceEncryptionProvider::encryptLegacyJson($json, $key);
$actual = SourceEncryptionProvider::encryptLegacyJson($json);

bkeyAssert($expected === $actual, 'cached bkey must produce identical legacy ciphertext');
bkeyAssert(SourceEncryptionProvider::lastLegacyKeySource() === 'cache', 'legacy key source must report cache');

@unlink($cacheFile);
putenv('SOURCE_LEGACY_BKEY_CACHE_FILE');
putenv('SOURCE_LEGACY_BKEY_TTL');
putenv('SOURCE_LEGACY_BKEY_STALE_TTL');

echo "source_encryption_bkey_cache_test: PASS\n";
