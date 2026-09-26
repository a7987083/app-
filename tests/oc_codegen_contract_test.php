<?php

require dirname(__DIR__) . '/application/common/library/Ipa/DylibApiContract.php';
require dirname(__DIR__) . '/application/common/library/codegen/ObjectiveCGenerator.php';

use app\common\library\codegen\ObjectiveCGenerator;
use app\common\library\Ipa\DylibApiContract;

function assert_true($value, $message)
{
    if (!$value) {
        fwrite(STDERR, "FAIL oc_codegen_contract: {$message}\n");
        exit(1);
    }
}

$generator = new ObjectiveCGenerator();
$dylib = [
    'id' => 7,
    'dylib_key' => 'zonoe.fixture',
    'name' => 'Fixture Dylib',
    'enabled' => 1,
    'verify_secret' => str_repeat('a', 64),
    'default_offline_grace' => 900,
    'default_fail_action' => 'disable_feature',
];
$runtime = [
    'config_version' => 3,
    'api_endpoints_json' => json_encode(['https://api-a.example.invalid', 'https://api-b.example.invalid']),
    'bootstrap_urls_json' => json_encode(['https://bootstrap-a.example.invalid/index/dylib_verify/config', 'https://bootstrap-b.example.invalid/index/dylib_verify/config']),
    'verify_path' => '/index/dylib_verify/verify',
];
$version = [
    'id' => 11,
    'version' => '2.4.8',
    'build' => '248',
    'state' => 'active',
    'sha256' => str_repeat('b', 64),
    'offline_grace' => 1200,
    'fail_action' => 'show_message',
    'notice' => 'fixture notice',
];

$config = $generator->buildConfig($dylib, $runtime, $version, [
    'class_prefix' => 'zon',
    'deployment_target' => '13.0',
    'timeout' => 500,
], 'https://fallback.example.invalid');

assert_true($config['class_prefix'] === 'ZON', 'class prefix normalization failed');
assert_true($config['deployment_target'] === '13.0', 'deployment target normalization failed');
assert_true($config['timeout'] === 120, 'timeout clamp failed');
assert_true($config['dylib_key'] === 'zonoe.fixture', 'dylib key missing');
assert_true($config['dylib_version'] === '2.4.8', 'version missing');
assert_true(count($config['bootstrap_urls']) === 2, 'bootstrap list missing');
assert_true(count($config['api_endpoints']) === 2, 'api endpoint list missing');
assert_true($config['legacy_dylib_urls'][0] === 'https://api-a.example.invalid/index/index/dylib', 'legacy Index::dylib URL missing');
assert_true($config['legacy_apiface_urls'][0] === 'https://api-a.example.invalid/index/index/apiface', 'legacy Index::apiface URL missing');
assert_true(!empty($config['features']['api_reference']), 'API reference feature flag missing');

$validation = $generator->validate($config);
assert_true($validation['valid'] === true, 'fixture config should be valid');

$first = $generator->generate($config);
$second = $generator->generate($config);
assert_true($first === $second, 'generation must be deterministic');

$expected = [
    'ZONDylibConfig.h',
    'ZONDylibConfig.m',
    'ZONDylibVerify.h',
    'ZONDylibVerify.m',
    'GeneratedConfig.json',
    'INTEGRATION.md',
    'API_REFERENCE.md',
    'ERROR_CODES.md',
    'EXAMPLES.md',
    'generation-manifest.json',
];
assert_true(count($first) === count($expected), 'unexpected generated file count');
foreach ($expected as $path) assert_true(isset($first[$path]), 'missing generated file ' . $path);

assert_true(strpos($first['ZONDylibConfig.m'], 'zonoe.fixture') !== false, 'dylib key not embedded');
assert_true(strpos($first['ZONDylibConfig.m'], '2.4.8') !== false, 'dylib version not embedded');
assert_true(strpos($first['ZONDylibConfig.m'], 'bootstrap-a.example.invalid') !== false, 'bootstrap URL not embedded');
assert_true(strpos($first['ZONDylibConfig.m'], str_repeat('a', 64)) !== false, 'client verification secret not embedded');
assert_true(strpos($first['ZONDylibConfig.h'], 'legacyDylibURLs') !== false, 'legacy dylib helper missing');
assert_true(strpos($first['ZONDylibConfig.h'], 'legacyApiFaceURLs') !== false, 'legacy apiface helper missing');
assert_true(strpos($first['INTEGRATION.md'], 'API_REFERENCE.md') !== false, 'integration guide must point to API reference');
assert_true(strpos($first['INTEGRATION.md'], '客户端 UI') !== false, 'integration guide must state API/client UI boundary');

assert_true(strpos($first['API_REFERENCE.md'], 'udid') !== false, 'API request field udid missing');
assert_true(strpos($first['API_REFERENCE.md'], 'dylib_key') !== false, 'API request field dylib_key missing');
assert_true(strpos($first['API_REFERENCE.md'], 'offline_grace_seconds') !== false, 'API response field missing');
assert_true(strpos($first['API_REFERENCE.md'], DylibApiContract::canonicalV2()) !== false, 'v2 canonical reference missing');
assert_true(strpos($first['ERROR_CODES.md'], 'signature_mismatch') !== false, 'signature mismatch error code missing');
assert_true(strpos($first['ERROR_CODES.md'], 'version_unknown') !== false, 'version unknown error code missing');
assert_true(strpos($first['ERROR_CODES.md'], 'integrity_mismatch') !== false, 'integrity mismatch error code missing');
assert_true(strpos($first['ERROR_CODES.md'], '`message`') !== false, 'message handling rule missing');
assert_true(strpos($first['EXAMPLES.md'], 'curl -X POST') !== false, 'curl example missing');
assert_true(strpos($first['EXAMPLES.md'], 'Objective-C') !== false, 'Objective-C example missing');

$codes = DylibApiContract::errorCodes();
$codeNames = array_column($codes, 'code');
foreach (['bad_request', 'timestamp_invalid', 'signature_mismatch', 'replay_detected', 'dylib_unknown', 'license_invalid', 'version_unknown', 'version_blocked', 'version_revoked', 'integrity_mismatch', 'server_error', 'ok'] as $requiredCode) {
    assert_true(in_array($requiredCode, $codeNames, true), 'missing canonical result code ' . $requiredCode);
}

assert_true(strpos($first['ZONDylibVerify.m'], 'canonicalV2UDID') !== false, 'v2 HMAC implementation missing');
assert_true(strpos($first['ZONDylibVerify.m'], 'currentAppMachOUUID') !== false, 'App identity implementation missing');
assert_true(strpos($first['ZONDylibVerify.m'], 'validateRuntimeConfig') !== false, 'runtime config validation missing');
assert_true(strpos($first['ZONDylibVerify.m'], 'permissions') !== false, 'permissions parsing missing');
assert_true(strpos($first['ZONDylibVerify.m'], 'appUpdate') !== false, 'app update parsing missing');

$public = json_decode($first['GeneratedConfig.json'], true);
assert_true(is_array($public), 'GeneratedConfig JSON invalid');
assert_true($public['verify_secret_present'] === true, 'secret presence flag missing');
assert_true($public['verify_secret'] !== str_repeat('a', 64), 'GeneratedConfig must mask verification secret');
assert_true(strpos($public['verify_secret'], '*') !== false, 'masked secret should contain asterisks');
assert_true(isset($public['legacy_dylib_urls'][0]), 'GeneratedConfig legacy dylib URLs missing');
assert_true(isset($public['legacy_apiface_urls'][0]), 'GeneratedConfig legacy apiface URLs missing');

$manifest = json_decode($first['generation-manifest.json'], true);
assert_true(is_array($manifest), 'manifest JSON invalid');
assert_true($manifest['config_sha256'] === $generator->configHash($config), 'config hash mismatch');
assert_true(isset($manifest['files']['ZONDylibVerify.m']), 'manifest Verify.m hash missing');
assert_true(isset($manifest['files']['API_REFERENCE.md']), 'manifest API reference hash missing');
assert_true(isset($manifest['files']['ERROR_CODES.md']), 'manifest error codes hash missing');

$dylibCodegen = @file_get_contents(dirname(__DIR__) . '/application/admin/controller/DylibCodegen.php');
assert_true($dylibCodegen !== false, 'DylibCodegen controller missing');
assert_true(strpos($dylibCodegen, "dylib_center/index") !== false, 'DylibCodegen must bind access to Dylib Center permission');
assert_true(strpos($dylibCodegen, "\$this->auth->check") !== false, 'DylibCodegen must explicitly check Dylib Center permission');
assert_true(strpos($dylibCodegen, "protected \$noNeedRight") !== false, 'DylibCodegen compatibility routes must skip route-specific rights after login');

$dylibCenter = @file_get_contents(dirname(__DIR__) . '/application/admin/controller/DylibCenter.php');
assert_true($dylibCenter !== false, 'DylibCenter controller missing');
assert_true(strpos($dylibCenter, 'public function deleteVersion()') !== false, 'version delete endpoint missing');
assert_true(strpos($dylibCenter, "Db::startTrans()") !== false, 'destructive dylib delete must be transactional');

$dylibCenterJs = @file_get_contents(dirname(__DIR__) . '/public/assets/js/backend/dylib_center.js');
assert_true($dylibCenterJs !== false, 'Dylib Center JS missing');
assert_true(strpos($dylibCenterJs, 'js-version-edit') !== false, 'version edit control missing');
assert_true(strpos($dylibCenterJs, 'js-version-delete') !== false, 'version delete control missing');

$legacyIndex = @file_get_contents(dirname(__DIR__) . '/application/index/controller/Index.php');
assert_true($legacyIndex !== false, 'Index controller missing');
assert_true(strpos($legacyIndex, 'public function dylib()') !== false, 'Index::dylib compatibility endpoint missing');
assert_true(strpos($legacyIndex, 'public function apiface()') !== false, 'Index::apiface compatibility endpoint missing');

$legacyController = @file_get_contents(dirname(__DIR__) . '/application/admin/controller/general/Occodegen.php');
assert_true($legacyController !== false, 'legacy Occodegen compatibility controller missing');
assert_true(strpos($legacyController, 'extends \\app\\admin\\controller\\DylibCodegen') !== false, 'legacy Occodegen must delegate to DylibCodegen');
assert_true(strpos($legacyController, 'ObjectiveCGenerator') === false, 'legacy Occodegen must not keep a second generator implementation');

fwrite(STDOUT, "OK oc_codegen_contract files=" . count($first) . " api_reference=yes error_codes=" . count($codes) . " hash=" . $generator->configHash($config) . "\n");
