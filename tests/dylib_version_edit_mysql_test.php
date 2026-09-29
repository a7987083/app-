<?php

$root = dirname(__DIR__);
define('APP_PATH', $root . '/application/');
require $root . '/thinkphp/base.php';
require_once $root . '/application/admin/library/traits/Backend.php';
require_once $root . '/application/common/controller/Backend.php';
require_once $root . '/application/admin/controller/DylibCenter.php';

use think\Config;
use think\Db;
use think\Request;

function dylibEditFail($message)
{
    fwrite(STDERR, "FAIL dylib_version_edit_mysql_test: {$message}\n");
    exit(1);
}

function dylibEditAssertSame($expected, $actual, $label)
{
    if ($expected !== $actual) {
        dylibEditFail($label . '; expected=' . var_export($expected, true) . '; actual=' . var_export($actual, true));
    }
}

function dylibEditResponseData($response, $label)
{
    if (!is_object($response) || !method_exists($response, 'getContent')) {
        dylibEditFail($label . ': controller did not return a response object');
    }
    $content = $response->getContent();
    $data = json_decode($content, true);
    if (!is_array($data) || !array_key_exists('total', $data) || !array_key_exists('rows', $data) || !is_array($data['rows'])) {
        dylibEditFail($label . ': response is not BootstrapTable JSON: ' . substr((string)$content, 0, 300));
    }
    return $data;
}

class DylibCenter2421TestProxy extends \app\admin\controller\DylibCenter
{
    public function useGet(array $params)
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];
        $request = new Request();
        $request->get($params);
        $this->request = $request;
        return $this;
    }
}

$db = getenv('DYLIB_EDIT_DB') ?: 'dylib_edit_ci';
Config::set('database', [
    'type' => 'mysql',
    'hostname' => getenv('MYSQL_HOST') ?: '127.0.0.1',
    'database' => $db,
    'username' => getenv('MYSQL_USER') ?: 'root',
    'password' => getenv('MYSQL_PASSWORD') !== false ? getenv('MYSQL_PASSWORD') : 'root',
    'hostport' => getenv('MYSQL_PORT') ?: '3306',
    'dsn' => '',
    'params' => [],
    'charset' => 'utf8mb4',
    'prefix' => 'fa_',
    'debug' => false,
    'deploy' => 0,
    'rw_separate' => false,
    'master_num' => 1,
    'slave_no' => '',
    'fields_strict' => true,
    'resultset_type' => 'array',
    'auto_timestamp' => false,
    'datetime_format' => false,
    'sql_explain' => false,
]);
Db::clear();

foreach (['fa_dylib_verify_log', 'fa_dylib_version'] as $table) {
    Db::execute("DROP TABLE IF EXISTS `{$table}`");
}

Db::execute("CREATE TABLE `fa_dylib_version` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `dylib_id` bigint unsigned NOT NULL,
    `version` varchar(64) NOT NULL,
    `build` varchar(64) NOT NULL DEFAULT '',
    `sha256` varchar(64) NOT NULL DEFAULT '',
    `file_size` bigint unsigned NOT NULL DEFAULT '0',
    `state` varchar(32) NOT NULL DEFAULT 'testing',
    `offline_grace` int unsigned NOT NULL DEFAULT '900',
    `fail_action` varchar(32) NOT NULL DEFAULT 'disable_feature',
    `notice` varchar(1024) NOT NULL DEFAULT '',
    `created_at` int unsigned NOT NULL DEFAULT '0',
    `updated_at` int unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `idx_dylib_id` (`dylib_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

Db::execute("CREATE TABLE `fa_dylib_verify_log` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `udid_hash` varchar(128) NOT NULL DEFAULT '',
    `bundle_id` varchar(255) NOT NULL DEFAULT '',
    `dylib_key` varchar(128) NOT NULL DEFAULT '',
    `dylib_version` varchar(64) NOT NULL DEFAULT '',
    `result_code` varchar(64) NOT NULL DEFAULT '',
    `action` varchar(64) NOT NULL DEFAULT '',
    `latency_ms` int unsigned NOT NULL DEFAULT '0',
    `created_at` int unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `idx_dylib_key` (`dylib_key`),
    KEY `idx_result_code` (`result_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

Db::name('dylib_version')->insertAll([
    ['dylib_id' => 5, 'version' => '1', 'build' => '', 'sha256' => '', 'file_size' => 0, 'state' => 'testing', 'offline_grace' => 900, 'fail_action' => 'disable_feature', 'notice' => '', 'created_at' => 1800000001, 'updated_at' => 1800000001],
    ['dylib_id' => 5, 'version' => '2', 'build' => '45', 'sha256' => '', 'file_size' => 0, 'state' => 'active', 'offline_grace' => 900, 'fail_action' => 'disable_feature', 'notice' => '', 'created_at' => 1800000002, 'updated_at' => 1800000002],
    ['dylib_id' => 6, 'version' => '9', 'build' => '', 'sha256' => '', 'file_size' => 0, 'state' => 'testing', 'offline_grace' => 900, 'fail_action' => 'disable_feature', 'notice' => '', 'created_at' => 1800000003, 'updated_at' => 1800000003],
]);

Db::name('dylib_verify_log')->insertAll([
    ['udid_hash' => 'aaaaaaaaaaaaaaaa', 'bundle_id' => 'com.example.one', 'dylib_key' => 'ceshi', 'dylib_version' => '1', 'result_code' => 'ok', 'action' => 'allow', 'latency_ms' => 12, 'created_at' => 1800000101],
    ['udid_hash' => 'bbbbbbbbbbbbbbbb', 'bundle_id' => 'com.example.two', 'dylib_key' => 'ceshi', 'dylib_version' => '2', 'result_code' => 'version_unknown', 'action' => 'block', 'latency_ms' => 20, 'created_at' => 1800000102],
    ['udid_hash' => 'cccccccccccccccc', 'bundle_id' => 'com.other.app', 'dylib_key' => 'other', 'dylib_version' => '9', 'result_code' => 'ok', 'action' => 'allow', 'latency_ms' => 8, 'created_at' => 1800000103],
]);

// Bypass Backend constructor/auth bootstrap; inject a real ThinkPHP Request into the production controller.
$controller = (new \ReflectionClass(DylibCenter2421TestProxy::class))->newInstanceWithoutConstructor();

$versions = dylibEditResponseData(
    $controller->useGet(['dylib_id' => '5', 'offset' => '0', 'limit' => '20'])->versions(),
    'versions dylib_id=5'
);
dylibEditAssertSame(2, (int)$versions['total'], 'versions total');
dylibEditAssertSame([2, 1], array_map('intval', array_column($versions['rows'], 'id')), 'versions row ids');

$logs = dylibEditResponseData(
    $controller->useGet(['dylib_key' => 'ceshi', 'offset' => '0', 'limit' => '1000'])->logs(),
    'logs dylib_key=ceshi'
);
dylibEditAssertSame(2, (int)$logs['total'], 'logs total');
dylibEditAssertSame([2, 1], array_map('intval', array_column($logs['rows'], 'id')), 'logs row ids');
dylibEditAssertSame('bbbbbbbbbbbb…', $logs['rows'][0]['udid_hash'], 'logs udid masking');

$filteredLogs = dylibEditResponseData(
    $controller->useGet(['dylib_key' => 'ceshi', 'result_code' => 'version_unknown', 'offset' => '0', 'limit' => '1000'])->logs(),
    'logs dylib_key/result_code'
);
dylibEditAssertSame(1, (int)$filteredLogs['total'], 'filtered logs total');
dylibEditAssertSame([2], array_map('intval', array_column($filteredLogs['rows'], 'id')), 'filtered logs row ids');

fwrite(STDOUT, "OK dylib_version_edit_mysql_test\n");
