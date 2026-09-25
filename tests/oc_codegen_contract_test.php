<?php

require dirname(__DIR__) . '/application/common/library/codegen/ObjectiveCGenerator.php';

class ObjectiveCGeneratorFixture extends \app\common\library\codegen\ObjectiveCGenerator
{
    public function currentConfig($domain = '')
    {
        return [
            'project_name' => 'FixtureAPI',
            'class_prefix' => 'ZON',
            'base_url' => $domain ?: 'https://example.invalid',
            'deployment_target' => '13.0',
            'timeout' => 15,
            'user_agent' => 'Fixture/1.0',
            'include_disabled' => false,
            'endpoints' => [
                [
                    'key' => 'appstore',
                    'name' => '软件源接口',
                    'path' => '/appstore',
                    'method' => 'GET',
                    'auth' => 'UDID/卡密',
                    'description' => 'fixture',
                    'enabled' => true,
                    'fields' => [
                        ['name' => 'udid', 'label' => 'UDID', 'required' => true, 'placeholder' => '设备 UDID'],
                    ],
                ],
                [
                    'key' => 'disabled_api',
                    'name' => '停用接口',
                    'path' => '/disabled',
                    'method' => 'POST',
                    'auth' => '',
                    'description' => '',
                    'enabled' => false,
                    'fields' => [],
                ],
            ],
        ];
    }
}

function assert_true($value, $message)
{
    if (!$value) {
        fwrite(STDERR, "FAIL oc_codegen_contract: {$message}\n");
        exit(1);
    }
}

$generator = new ObjectiveCGeneratorFixture();
$config = $generator->normalize([
    'project_name' => 'Fixture API!',
    'class_prefix' => 'zon',
    'base_url' => 'https://api.example.invalid/',
    'deployment_target' => '13.0',
    'timeout' => 500,
    'user_agent' => 'Fixture Test',
    'include_disabled' => false,
    'endpoints' => $generator->currentConfig()['endpoints'],
], 'https://fallback.invalid');

assert_true($config['project_name'] === 'FixtureAPI', 'project name normalization failed');
assert_true($config['class_prefix'] === 'ZON', 'class prefix normalization failed');
assert_true($config['base_url'] === 'https://api.example.invalid', 'base URL normalization failed');
assert_true($config['timeout'] === 120, 'timeout clamp failed');

$validation = $generator->validate($config);
assert_true($validation['valid'] === true, 'fixture config should be valid');

$first = $generator->generate($config);
$second = $generator->generate($config);
assert_true($first === $second, 'generation must be deterministic');

$expected = ['ZONAPIConfig.h','ZONAPIConfig.m','ZONAPIEndpoints.h','ZONAPIEndpoints.m','ZONAPIClient.h','ZONAPIClient.m','GeneratedConfig.json','API_REFERENCE.md','INTEGRATION.md','generation-manifest.json'];
foreach ($expected as $path) {
    assert_true(isset($first[$path]), 'missing generated file ' . $path);
}
assert_true(strpos($first['ZONAPIEndpoints.m'], 'appstore') !== false, 'enabled endpoint missing');
assert_true(strpos($first['ZONAPIEndpoints.m'], 'disabled_api') === false, 'disabled endpoint should be excluded');
assert_true(strpos($first['API_REFERENCE.md'], 'Response Schema') !== false, 'response schema limitation must be documented');
assert_true(strpos($first['INTEGRATION.md'], '不要写死') !== false, 'secret handling guidance missing');

$manifest = json_decode($first['generation-manifest.json'], true);
assert_true(is_array($manifest), 'manifest JSON invalid');
assert_true(isset($manifest['config_sha256']) && $manifest['config_sha256'] === $generator->configHash($config), 'config hash mismatch');
assert_true(isset($manifest['files']['ZONAPIClient.m']), 'manifest file hash missing');

fwrite(STDOUT, "OK oc_codegen_contract files=" . count($first) . " hash=" . $generator->configHash($config) . "\n");
