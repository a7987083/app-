<?php

require dirname(__DIR__) . '/application/common/library/codegen/ObjectiveCGenerator.php';

use app\common\library\codegen\ObjectiveCGenerator;

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
    'generation-manifest.json',
];
assert_true(count($first) === count($expected), 'unexpected generated file count');
foreach ($expected as $path) assert_true(isset($first[$path]), 'missing generated file ' . $path);

assert_true(strpos($first['ZONDylibConfig.m'], 'zonoe.fixture') !== false, 'dylib key not embedded');
assert_true(strpos($first['ZONDylibConfig.m'], '2.4.8') !== false, 'dylib version not embedded');
assert_true(strpos($first['ZONDylibConfig.m'], 'bootstrap-a.example.invalid') !== false, 'bootstrap URL not embedded');
assert_true(strpos($first['ZONDylibConfig.m'], str_repeat('a', 64)) !== false, 'client verification secret not embedded');

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

$manifest = json_decode($first['generation-manifest.json'], true);
assert_true(is_array($manifest), 'manifest JSON invalid');
assert_true($manifest['config_sha256'] === $generator->configHash($config), 'config hash mismatch');
assert_true(isset($manifest['files']['ZONDylibVerify.m']), 'manifest Verify.m hash missing');
assert_true(isset($manifest['files']['ZONDylibConfig.m']), 'manifest Config.m hash missing');

$dylibCodegen = @file_get_contents(dirname(__DIR__) . '/application/admin/controller/DylibCodegen.php');
assert_true($dylibCodegen !== false, 'DylibCodegen controller missing');
assert_true(strpos($dylibCodegen, "dylib_center/index") !== false, 'DylibCodegen must bind access to Dylib Center permission');
assert_true(strpos($dylibCodegen, "\$this->auth->check") !== false, 'DylibCodegen must explicitly check Dylib Center permission');
assert_true(strpos($dylibCodegen, "protected \$noNeedRight") !== false, 'DylibCodegen compatibility routes must skip route-specific rights after login');

$legacyController = @file_get_contents(dirname(__DIR__) . '/application/admin/controller/general/Occodegen.php');
assert_true($legacyController !== false, 'legacy Occodegen compatibility controller missing');
assert_true(strpos($legacyController, 'extends \\app\\admin\\controller\\DylibCodegen') !== false, 'legacy Occodegen must delegate to DylibCodegen');
assert_true(strpos($legacyController, 'ObjectiveCGenerator') === false, 'legacy Occodegen must not keep a second generator implementation');

fwrite(STDOUT, "OK oc_codegen_contract files=" . count($first) . " permission=dylib_center/index hash=" . $generator->configHash($config) . "\n");
