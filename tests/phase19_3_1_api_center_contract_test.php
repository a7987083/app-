<?php

function p1931ApiFail($message)
{
    fwrite(STDERR, "FAIL phase19_3_1_api_center_contract_test: {$message}\n");
    exit(1);
}

function p1931ApiAssert($condition, $message)
{
    if (!$condition) {
        p1931ApiFail($message);
    }
}

$root = dirname(__DIR__);
$view = file_get_contents($root . '/application/admin/view/general/config/index.html');
$js = file_get_contents($root . '/public/assets/js/backend/general/config.js');
$controller = file_get_contents($root . '/application/admin/controller/general/Config.php');
$registry = file_get_contents($root . '/application/common/library/ApiEndpointRegistry.php');

foreach ([$view, $js, $controller, $registry] as $source) {
    p1931ApiAssert($source !== false, 'required source file missing');
}

p1931ApiAssert(strpos($view, 'id="project-api-logs-refresh"') !== false, 'AJAX log refresh button missing');
p1931ApiAssert(strpos($view, 'onclick="location.reload()"') === false || strpos($view, 'id="project-api-logs-refresh" onclick="location.reload()"') === false, 'log refresh must not reload whole page');
p1931ApiAssert(strpos($view, 'id="project-api-log-body"') !== false, 'log tbody target missing');
p1931ApiAssert(strpos($view, 'id="project-api-test-key"') !== false, 'test API dropdown missing');
p1931ApiAssert(strpos($view, 'name="endpoint_key"') !== false, 'test API endpoint_key field missing');
p1931ApiAssert(strpos($view, "data-toggle-url=\"{:url('general.config/api_toggle')}\"") !== false, 'framework-generated toggle URL missing');
p1931ApiAssert(strpos($view, "data-test-url=\"{:url('general.config/api_test')}\"") !== false, 'framework-generated test URL missing');
p1931ApiAssert(strpos($view, 'function projectApiPost(') === false, 'legacy inline API event layer must be removed');

p1931ApiAssert(strpos($js, 'function bindApiCenter()') !== false, 'FastAdmin API center binding missing');
p1931ApiAssert(strpos($js, 'Backend.api.ajax') !== false, 'API center must use FastAdmin AJAX');
p1931ApiAssert(strpos($js, "'.api-toggle-btn'") !== false, 'toggle event binding missing');
p1931ApiAssert(strpos($js, "'#project-api-test-submit'") !== false, 'test event binding missing');
p1931ApiAssert(strpos($js, "'#project-api-logs-refresh'") !== false, 'log refresh binding missing');
p1931ApiAssert(strpos($js, 'renderApiLogs') !== false, 'AJAX log renderer missing');
p1931ApiAssert(strpos($js, 'sessionStorage') !== false, 'API tab persistence missing');

p1931ApiAssert(strpos($controller, '\'enabled\' => $enabled ? 1 : 0') !== false, 'toggle response must return persisted target state');
p1931ApiAssert(strpos($controller, "post('endpoint_key'") !== false, 'API test must use endpoint key');
p1931ApiAssert(strpos($controller, 'where(\'endpoint_key\', $endpointKey)') !== false, 'API test lookup by endpoint key missing');

p1931ApiAssert(strpos($registry, 'API不存在或尚未完成数据库迁移') !== false, 'toggle missing-row guard missing');
p1931ApiAssert(strpos($registry, '接口开关写入后校验失败') !== false, 'toggle post-write verification missing');

fwrite(STDOUT, "OK phase19_3_1_api_center_contract_test ajax_logs=passed toggle=real test_dropdown=passed tab_state=passed\n");
