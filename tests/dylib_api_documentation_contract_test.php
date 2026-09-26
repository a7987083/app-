<?php

$root = dirname(__DIR__);
require_once $root . '/application/common/library/Ipa/DylibApiContract.php';
require_once $root . '/application/common/library/Ipa/DylibApiDocumentation.php';

use app\common\library\Ipa\DylibApiDocumentation;

function docs_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL dylib_api_documentation_contract: {$message}\n");
        exit(1);
    }
}

$verifyPath = '/custom/dylib/verify';
$catalog = DylibApiDocumentation::catalog($verifyPath);
docs_assert(count($catalog) === 8, 'catalog must contain exactly 8 documented entries');

$paths = [];
foreach ($catalog as $api) {
    $paths[$api['path']] = $api;
}
foreach ([
    '/authorization',
    '/appstore',
    '/index/index/apiface',
    '/index/index/dylib',
    '/unbind',
    '/unbind/query',
    '/index/dylib_verify/config',
    $verifyPath,
] as $path) {
    docs_assert(isset($paths[$path]), 'missing API path ' . $path);
}

docs_assert($paths['/authorization']['response_type'] === 'HTML 页面兼容入口', '/authorization must not be mislabeled as JSON');
docs_assert($paths['/unbind']['response_type'] === 'HTML 页面兼容入口', '/unbind must not be mislabeled as JSON');
docs_assert($paths[$verifyPath]['response_type'] === 'JSON', 'verify must be JSON');

$files = DylibApiDocumentation::exportFiles('zonoe.test', 'Test Dylib', $verifyPath, ['config_version' => 9]);
foreach ([
    'README.md',
    'API_OVERVIEW.md',
    'API_REFERENCE.md',
    'ERROR_CODES.md',
    'SIGNATURE.md',
    'RESPONSE_MODEL.md',
    'FLOW.md',
    'examples/curl.md',
    'examples/Objective-C.md',
    'examples/Swift.md',
    'examples/Python.md',
    'schemas/api.json',
    'schemas/error_codes.json',
] as $file) {
    docs_assert(isset($files[$file]) && strlen($files[$file]) > 0, 'missing generated file ' . $file);
}

docs_assert(strpos($files['API_REFERENCE.md'], $verifyPath) !== false, 'dynamic verify path missing from reference');
docs_assert(strpos($files['SIGNATURE.md'], '<VERIFY_SECRET>') !== false, 'signature guide must use secret placeholder');
docs_assert(strpos($files['README.md'], '真实 Verify Secret') !== false, 'secret export warning missing');

$apiJson = json_decode($files['schemas/api.json'], true);
$errorJson = json_decode($files['schemas/error_codes.json'], true);
docs_assert(is_array($apiJson), 'api.json must be valid JSON');
docs_assert(is_array($errorJson), 'error_codes.json must be valid JSON');
docs_assert(isset($apiJson['apis']) && count($apiJson['apis']) === 8, 'api.json must contain 8 APIs');
docs_assert(isset($apiJson['security']['verify_secret']) && $apiJson['security']['verify_secret'] === '<VERIFY_SECRET>', 'api.json must not export a real secret');
docs_assert(strpos($files['schemas/api.json'], 'verify_secret_ciphertext') === false, 'ciphertext field must never be exported');

echo "OK dylib_api_documentation_contract 2413 8-apis 13-files secret-safe\n";
