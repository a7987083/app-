<?php

function p192_fail($message)
{
    fwrite(STDERR, "FAIL phase19_2_legacy_response_cache_test: {$message}\n");
    exit(1);
}

function p192_assert($condition, $message)
{
    if (!$condition) {
        p192_fail($message);
    }
}

$root = dirname(__DIR__);
$read = function ($relative) use ($root) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        p192_fail('missing file: ' . $relative);
    }
    $data = file_get_contents($path);
    if ($data === false) {
        p192_fail('cannot read file: ' . $relative);
    }
    return $data;
};

$route = $read('application/route.php');
p192_assert(strpos($route, "Route::rule('appstore','index/App/list')") !== false, 'legacy /appstore route missing');
p192_assert(strpos($route, 'appstore/v3/') === false, 'public V3 routes must be retired');

$config = $read('application/admin/controller/general/Config.php');
p192_assert(strpos($config, "name === 'source_v3'") === false, 'admin V3 switch ordering logic must be removed');
p192_assert(strpos($config, 'V3软件源') === false, 'admin controller must not expose V3 switch UI logic');

$v3 = $read('application/index/controller/SourceV3.php');
p192_assert(strpos($v3, 'http_response_code(404)') !== false, 'legacy SourceV3 upgrade tombstone must return 404');
p192_assert(strpos($v3, 'SourceSyncV3') === false, 'V3 tombstone must not execute sync runtime');
p192_assert(strpos($v3, "'Not Found'") !== false, 'V3 tombstone must be generic');

$sql = $read('release/sql/2026091802_retire_source_v3.sql');
p192_assert(strpos($sql, "DELETE FROM `fa_config`") !== false, 'retirement migration must delete config row');
p192_assert(strpos($sql, "'source_v3'") !== false, 'retirement migration must target source_v3');
p192_assert(strpos($sql, 'DROP TABLE') === false, 'revision/change-log table must be retained');

$manifest = $read('release/online-update-files.txt');
p192_assert(strpos($manifest, "application/common/library/SourceLegacyCache.php\n") !== false, 'online update must contain SourceLegacyCache');
p192_assert(strpos($manifest, "application/index/controller/SourceV3.php\n") !== false, 'online update must overwrite old SourceV3 with tombstone');
p192_assert(strpos($manifest, 'application/common/library/SourceSyncV3.php') === false, 'retired SourceSyncV3 must not be distributed');
p192_assert(strpos($manifest, "public/assets/js/backend/authorization.js\n") !== false, 'authorization center JS must remain in online update');

$appPayload = $read('application/common/library/AppStorePayload.php');
p192_assert(strpos($appPayload, 'SourceLegacyCache::begin($mode, $sourceAccess)') !== false, 'App mapping must start entitlement-scoped cache context');
p192_assert(strpos($appPayload, 'SourceLegacyCache::getMappedApps') !== false, 'App mapping cache read missing');
p192_assert(strpos($appPayload, 'SourceLegacyCache::storeMappedApps') !== false, 'App mapping cache write missing');

$response = $read('application/common/library/SourceResponse.php');
p192_assert(strpos($response, 'SourceLegacyCache::getPlainBody') !== false, 'plain response cache read missing');
p192_assert(strpos($response, 'SourceLegacyCache::storePlainBody') !== false, 'plain response cache write missing');
p192_assert(strpos($response, 'AppStorePayload::withoutRuntimeFields') !== false, 'legacy runtime-field stripping must remain intact');
p192_assert(strpos($response, 'const GZIP_MAX_BYTES = 8388608;') !== false, 'large-response gzip memory guard missing');
p192_assert(strpos($response, 'SOURCE_HTTP_GZIP_MAX_BYTES') !== false, 'gzip upper bound must remain configurable');

$app = $read('application/index/controller/App.php');
p192_assert(strpos($app, 'protected function emitPayload(array &$payload') !== false, 'App response path must release caller payload by reference');
p192_assert(strpos($app, '$blacklistedPayload = AppStorePayload::blacklisted') !== false, 'blacklist response must pass a variable to by-reference emitPayload');
p192_assert(strpos($app, '$appCount = isset($payload[\'apps\'])') !== false, 'App count must be captured before payload release');
p192_assert(substr_count($app, '$payload = [];') >= 2, 'encrypted and plain response paths must release payload before send');
p192_assert(strpos($app, 'protected function logSourcePerformance($appCount') !== false, 'performance logging must not retain the full payload array');
p192_assert(strpos($app, "'app_count' => (int)\$appCount") !== false, 'performance logging must preserve app_count after payload release');

$provider = $read('application/common/library/SourceEncryptionProvider.php');
p192_assert(strpos($provider, 'return self::encodeV2Parts([$header, $payload]);') !== false, 'V2 encoder must avoid building a duplicate full container string');
p192_assert(strpos($provider, 'protected static function encodeV2Parts(array $parts)') !== false, 'V2 multipart codec helper missing');
p192_assert(strpos($provider, '$container = pack(') === false, 'V2 path must not restore the full container allocation');

$repo = $read('application/common/library/SourceAppRepository.php');
p192_assert(strpos($repo, 'GENERATION_KEY') !== false, 'cross-worker source generation missing');
p192_assert(strpos($repo, 'public static function generation()') !== false, 'source generation accessor missing');
p192_assert(strpos($repo, 'Cache::rm(self::CACHE_KEY)') !== false, 'source row invalidation missing');

$change = $read('application/common/library/SourceChangeLog.php');
p192_assert(strpos($change, "const TABLE = 'fa_source_change'") !== false, 'revision/change-log infrastructure must remain');
p192_assert(strpos($change, 'REVISION_CACHE_KEY') !== false, 'shared revision cache missing');
p192_assert(strpos($change, 'public static function currentRevision()') !== false, 'current revision accessor missing');

require_once $root . '/application/common/library/SourceLegacyCache.php';
require_once $root . '/application/common/library/SourceResponse.php';
require_once $root . '/application/common/library/SourceEncryptionProvider.php';
$cacheClass = 'app\\common\\library\\SourceLegacyCache';
$responseClass = 'app\\common\\library\\SourceResponse';
$providerClass = 'app\\common\\library\\SourceEncryptionProvider';
$guest = $cacheClass::accessSignature('guest', ['unlock_all' => true, 'app_ids' => [1, 2]]);
$all = $cacheClass::accessSignature('licensed', ['unlock_all' => true, 'app_ids' => []]);
$appsA = $cacheClass::accessSignature('licensed', ['unlock_all' => false, 'app_ids' => [9, 2, 9, 4]]);
$appsB = $cacheClass::accessSignature('licensed', ['unlock_all' => false, 'app_ids' => [4, 9, 2]]);
$appsC = $cacheClass::accessSignature('licensed', ['unlock_all' => false, 'app_ids' => [2, 4]]);

p192_assert($guest === 'guest', 'guest cache signature must ignore supplied unlock data');
p192_assert($all === 'all', 'whole-source entitlement signature incorrect');
p192_assert($appsA === $appsB, 'App entitlement signature must be order/duplicate independent');
p192_assert($appsA !== $appsC, 'different App entitlement sets must not share cache');
p192_assert($guest !== $all && $all !== $appsA, 'guest/all/App caches must remain isolated');

$legacyEnvelope = str_replace('@@@', '\\n', json_encode(['appstore' => 'abc@@@def']));
p192_assert($responseClass::encryptedBody('appstore', 'abc@@@def', true) === $legacyEnvelope, 'encrypted response marker behavior changed');
p192_assert($responseClass::encryptedBody('appstore', false, true) === '{"appstore":false}', 'transport-failure envelope changed');

putenv('SOURCE_HTTP_GZIP=1');
putenv('SOURCE_HTTP_GZIP_MIN_BYTES=1');
putenv('SOURCE_HTTP_GZIP_MAX_BYTES=64');
$largeBody = str_repeat('A', 65);
$transport = $responseClass::transportBody($largeBody, 'gzip');
p192_assert(is_array($transport) && $transport['gzip'] === false, 'large body must bypass one-shot PHP gzip');
p192_assert($transport['body'] === $largeBody, 'gzip guard must not change application bytes');
putenv('SOURCE_HTTP_GZIP');
putenv('SOURCE_HTTP_GZIP_MIN_BYTES');
putenv('SOURCE_HTTP_GZIP_MAX_BYTES');

$rawCodecFixture = '';
for ($i = 0; $i < 257; $i++) {
    $rawCodecFixture .= chr(($i * 37) & 0xff);
}
$wholeCodec = $providerClass::encodeV2Codec($rawCodecFixture);
$partsMethod = new ReflectionMethod($providerClass, 'encodeV2Parts');
$partsMethod->setAccessible(true);
foreach ([1, 5, 6, 7, 31, 32, 63, 64, 65, 127, 128, 129, 255, 256] as $splitAt) {
    $parts = [substr($rawCodecFixture, 0, $splitAt), substr($rawCodecFixture, $splitAt)];
    $splitCodec = $partsMethod->invoke(null, $parts);
    p192_assert($splitCodec === $wholeCodec, 'V2 multipart codec mismatch at split ' . $splitAt);
}
$multiParts = [
    substr($rawCodecFixture, 0, 1),
    substr($rawCodecFixture, 1, 5),
    substr($rawCodecFixture, 6, 58),
    substr($rawCodecFixture, 64, 65),
    substr($rawCodecFixture, 129),
];
p192_assert($partsMethod->invoke(null, $multiParts) === $wholeCodec, 'V2 multipart codec mismatch across multiple boundaries');

echo "OK phase19_2_legacy_response_cache_test legacy_route=passed entitlement_cache=isolated revision=retained v3=retired manifest=passed payload_release=passed gzip_guard=passed v2_codec=equivalent\n";
