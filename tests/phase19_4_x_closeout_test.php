<?php

$root = dirname(__DIR__);

function p194x_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL phase19_4_x_closeout_test: {$message}\n");
        exit(1);
    }
}

require $root . '/application/common/library/SourceServerRuntime.php';

use app\common\library\SourceServerRuntime;

$tmp = sys_get_temp_dir() . '/zonoe_p194x_' . getmypid() . '_' . mt_rand(1000, 9999) . '/';
@mkdir($tmp, 0755, true);
$first = SourceServerRuntime::info(100000, $tmp);
$second = SourceServerRuntime::info(100000 + 90061, $tmp);

p194x_assert($first['started_at'] === 100000, 'runtime start timestamp not initialized');
p194x_assert($second['started_at'] === 100000, 'runtime start timestamp was not persisted');
p194x_assert($second['seconds'] === 90061, 'runtime elapsed seconds mismatch');
p194x_assert(is_file($tmp . 'runtime/persistent/server_runtime.json'), 'runtime persistence file missing');
p194x_assert(is_file($tmp . 'runtime/persistent/server_runtime.json.bak'), 'runtime persistence backup missing');

$config = file_get_contents($root . '/application/admin/controller/general/Config.php');
$registry = file_get_contents($root . '/application/common/library/ApiEndpointRegistry.php');
$js = file_get_contents($root . '/public/assets/js/backend/general/config.js');
$template = file_get_contents($root . '/application/common/library/SourceAnnouncementTemplate.php');
$sql = file_get_contents($root . '/release/sql/2026091808_phase19_4_x_closeout.sql');
$manifest = file_get_contents($root . '/release/online-update-files.txt');

p194x_assert(strpos($config, 'session_write_close()') !== false, 'long update request does not release PHP session');
p194x_assert(strpos($config, "header('Cache-Control: no-store") !== false, 'update status is not no-cache');
p194x_assert(strpos($config, 'api_test_schema') !== false, 'API test schema endpoint missing');
p194x_assert(strpos($config, "post('test_params/a'") !== false, 'typed API test params not accepted');
p194x_assert(strpos($config, 'SourceAnnouncementTemplate::normalizeTemplate') !== false, 'announcement normalization on save missing');

p194x_assert(strpos($registry, "'path' => '/authorization'") !== false, 'authorization system API path incorrect');
p194x_assert(strpos($registry, "['name' => 'code', 'label' => '卡密', 'required' => true") !== false, 'card field schema missing');
p194x_assert(strpos($registry, "['name' => 'udid', 'label' => 'UDID', 'required' => true") !== false, 'UDID field schema missing');
p194x_assert(strpos($registry, 'syncSystemDefinitions') !== false, 'system API metadata sync missing');

p194x_assert(strpos($js, 'general/config/api_test_schema') !== false, 'dynamic API test schema request missing');
p194x_assert(strpos($js, 'test_params[') !== false, 'dynamic API test field names missing');
p194x_assert(strpos($js, 'restoreActiveUpdate') !== false, 'update job restore support missing');
p194x_assert(strpos($js, 'cache: false') !== false, 'update status cache bypass missing');
p194x_assert(strpos($js, 'preparing') !== false && strpos($js, 'package_complete') !== false, 'backend update stages not normalized');

foreach (['授权摘要','到期时间','源名称','指定APP数量'] as $retired) {
    p194x_assert(strpos($template, "['key' => '{$retired}'") === false, 'retired editor variable exposed: ' . $retired);
}
p194x_assert(strpos($template, "['key' => '服务器运行时间'") !== false, 'server runtime editor variable missing');
p194x_assert(strpos($sql, "REPLACE(`value`, '[服务器时间]', '[服务器运行时间]')") !== false, 'server time migration missing');
p194x_assert(strpos($sql, "`path`='/authorization'") !== false, 'authorization DB migration missing');
p194x_assert(strpos($manifest, "application/common/library/SourceServerRuntime.php\n") !== false, 'server runtime omitted from update package');

echo "OK phase19_4_x_closeout_test runtime=passed session=passed api_fields=passed api_sync=passed announcement=passed update_ui=passed\n";
