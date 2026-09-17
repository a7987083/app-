<?php

function p193_fail($message)
{
    fwrite(STDERR, "FAIL phase19_3_api_center_performance_test: {$message}\n");
    exit(1);
}

function p193_assert($condition, $message)
{
    if (!$condition) {
        p193_fail($message);
    }
}

$root = dirname(__DIR__);
$read = function ($relative) use ($root) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        p193_fail('missing file: ' . $relative);
    }
    $data = file_get_contents($path);
    if ($data === false) {
        p193_fail('cannot read file: ' . $relative);
    }
    return $data;
};

$authorization = $read('application/admin/controller/Authorization.php');
$transferDelete = strpos($authorization, "DELETE FROM `fa_card_transfer_log`");
$transferCatch = strpos($authorization, 'catch (\\Exception $e)', $transferDelete);
$transferSuccess = strpos($authorization, "换绑记录已清空", $transferDelete);
p193_assert($transferDelete !== false && $transferCatch !== false && $transferSuccess !== false, 'transfer clear flow missing');
p193_assert($transferDelete < $transferCatch && $transferCatch < $transferSuccess, 'transfer success must be outside DB exception handler');

$eventDelete = strpos($authorization, "DELETE FROM `fa_authorization_event`");
$eventCatch = strpos($authorization, 'catch (\\Exception $e)', $eventDelete);
$eventSuccess = strpos($authorization, "授权事件已清空", $eventDelete);
p193_assert($eventDelete !== false && $eventCatch !== false && $eventSuccess !== false, 'event clear flow missing');
p193_assert($eventDelete < $eventCatch && $eventCatch < $eventSuccess, 'event success must be outside DB exception handler');

$route = $read('application/route.php');
p193_assert(strpos($route, "Route::rule('project-api/:slug','index/ManagedApi/dispatch')") !== false, 'custom project API route missing');

$registry = $read('application/common/library/ApiEndpointRegistry.php');
foreach (['appstore', 'dylib_config', 'dylib_auth', 'unbind', 'unbind_query', 'license'] as $key) {
    p193_assert(strpos($registry, "'endpoint_key' => '{$key}'") !== false, 'project endpoint missing: ' . $key);
}
p193_assert(strpos($registry, "fa_api_request_log") !== false, 'API request telemetry missing');
p193_assert(strpos($registry, "source'] === 'system'") !== false, 'system API delete protection missing');
p193_assert(strpos($registry, "project-api/") !== false, 'custom API aliases must stay in project namespace');

$managed = $read('application/index/controller/ManagedApi.php');
p193_assert(strpos($managed, 'Safe aliases for project-owned APIs') !== false, 'managed API safety boundary missing');
p193_assert(strpos($managed, 'eval(') === false, 'managed API must not execute arbitrary PHP');
p193_assert(strpos($managed, 'action($map[$handler])') !== false, 'managed API handler dispatch missing');

$app = $read('application/index/controller/App.php');
p193_assert(strpos($app, "ApiEndpointRegistry::guard('appstore')") !== false, 'appstore runtime switch missing');
p193_assert(strpos($app, "'legacy_cache' => SourceLegacyCache::status()") !== false, 'cache telemetry missing');

$index = $read('application/index/controller/Index.php');
foreach ([
    "ApiEndpointRegistry::guard('dylib_config')",
    "ApiEndpointRegistry::guard('dylib_auth')",
    "ApiEndpointRegistry::guard('unbind')",
    "ApiEndpointRegistry::guard('unbind_query')",
    "ApiEndpointRegistry::guard('license')",
] as $needle) {
    p193_assert(strpos($index, $needle) !== false, 'project API guard missing: ' . $needle);
}

$config = $read('application/admin/controller/general/Config.php');
p193_assert(strpos($config, 'ApiEndpointRegistry::all($this->request->domain())') !== false, 'API center list binding missing');
foreach (['api_toggle', 'api_save', 'api_delete', 'api_test', 'api_logs'] as $action) {
    p193_assert(strpos($config, 'function ' . $action . '(') !== false, 'API center action missing: ' . $action);
}

$view = $read('application/admin/view/general/config/index.html');
foreach (['API接口', 'API列表', '新增API', '编辑API', '测试API', '请求日志'] as $label) {
    p193_assert(strpos($view, $label) !== false, 'API center UI missing: ' . $label);
}
p193_assert(strpos($view, 'FastAdmin 原生 /api/*') !== false, 'API center scope notice missing');

$repo = $read('application/common/library/SourceAppRepository.php');
p193_assert(strpos($repo, 'const CACHE_TTL = 120;') !== false, 'source row cache TTL not aligned with Phase 19.3');

$sql = $read('release/sql/2026091803_api_center_performance.sql');
foreach (['fa_api_endpoint', 'fa_api_request_log', 'idx_zonoe_udid_auth', 'idx_zonoe_kami_lookup', 'idx_zonoe_black_udid', 'idx_zonoe_kami_app'] as $needle) {
    p193_assert(strpos($sql, $needle) !== false, 'Phase 19.3 migration missing: ' . $needle);
}

$manifest = $read('release/online-update-files.txt');
foreach ([
    'application/common/library/ApiEndpointRegistry.php',
    'application/index/controller/ManagedApi.php',
    'application/admin/view/general/config/index.html',
] as $path) {
    p193_assert(strpos($manifest, $path . "\n") !== false, 'online update manifest missing: ' . $path);
}

require_once $root . '/application/common/library/ApiEndpointRegistry.php';
$definitions = \app\common\library\ApiEndpointRegistry::systemDefinitions();
p193_assert(count($definitions) === 6, 'API center must expose exactly six project system APIs');
p193_assert(!isset($definitions['api_user']) && !isset($definitions['sms']), 'FastAdmin native APIs must not enter project registry');

echo "OK phase19_3_api_center_performance_test clear=passed api_center=passed telemetry=passed indexes=declared manifest=passed\n";
