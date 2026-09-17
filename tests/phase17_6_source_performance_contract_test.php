<?php

function p176Assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL phase17_6_source_performance_contract_test: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$app = file_get_contents($root . '/application/index/controller/App.php');
$provider = file_get_contents($root . '/application/common/library/SourceEncryptionProvider.php');
$repo = file_get_contents($root . '/application/common/library/SourceAppRepository.php');
$perf = file_get_contents($root . '/application/common/library/SourcePerformance.php');
$model = file_get_contents($root . '/application/common/model/Category.php');
$benchmark = file_get_contents($root . '/tools/source_encryption_benchmark.php');

p176Assert($app !== false && $provider !== false && $repo !== false && $perf !== false && $model !== false && $benchmark !== false, 'required files missing');
p176Assert(strpos($app, 'SourceAppRepository::rows()') !== false, 'App controller must use shared App-row cache');
p176Assert(strpos($app, 'SourcePerformance::log([') !== false, 'App controller must emit timing telemetry');
p176Assert(strpos($app, "'encryption_ms'") !== false && strpos($app, "'response_bytes'") !== false, 'timing/size metrics missing');
p176Assert(strpos($provider, 'LEGACY_BKEY_CACHE_TTL = 900') !== false, 'legacy bkey fresh TTL missing');
p176Assert(strpos($provider, 'LEGACY_BKEY_STALE_TTL = 86400') !== false, 'legacy bkey stale fallback missing');
p176Assert(strpos($provider, "'source_crypto'") !== false && strpos($provider, "'legacy_bkey.json'") !== false, 'legacy bkey runtime cache path missing');
p176Assert(strpos($provider, 'self::$lastLegacyKeySource = \'cache\'') !== false, 'cache-hit source marker missing');
p176Assert(strpos($repo, 'const CACHE_TTL = 15') !== false, 'App-row cache TTL must stay short');
p176Assert(strpos($model, 'SourceAppRepository::forget()') !== false, 'App-row cache invalidation missing');
p176Assert(strpos($benchmark, '50000') !== false && strpos($benchmark, 'encryptV2Json') !== false, '50k dual-protocol benchmark missing');

echo "OK phase17_6_source_performance_contract_test cache=passed telemetry=passed benchmark=passed\n";
