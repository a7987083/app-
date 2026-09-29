<?php

$root = dirname(__DIR__);
define('APP_PATH', $root . '/application/');
require $root . '/thinkphp/base.php';
require_once $root . '/application/admin/library/traits/Backend.php';
require_once $root . '/application/common/controller/Backend.php';
require_once $root . '/application/admin/controller/IpaAssets.php';

use think\Config;
use think\Db;

function ipaSearchAssertSame($expected, $actual, $label)
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL ipa_asset_search_mysql_test: {$label}; expected=" . var_export($expected, true) . "; actual=" . var_export($actual, true) . "\n");
        exit(1);
    }
}

class IpaAssetsSearchTestProxy extends \app\admin\controller\IpaAssets
{
    public function quickIds($keyword)
    {
        $query = Db::name('ipa_asset');
        $this->applyQuickSearch($query, $keyword);
        return array_map('intval', $query->order('id', 'asc')->column('id'));
    }

    public function commonIds(array $filter, array $op = [])
    {
        $query = Db::name('ipa_asset');
        $this->applyCommonSearch($query, $filter, $op);
        return array_map('intval', $query->order('id', 'asc')->column('id'));
    }

    public function resolvedIds($search, array $filter, array $op = [])
    {
        $query = $this->buildAssetQuery($search, $filter, $op);
        return array_map('intval', $query->order('id', 'asc')->column('id'));
    }
}

$db = getenv('IPA_SEARCH_DB') ?: 'ipa_search_ci';
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

foreach (['fa_ipa_compare_result', 'fa_ipa_asset', 'fa_ipa_source'] as $table) {
    Db::execute("DROP TABLE IF EXISTS `{$table}`");
}

Db::execute("CREATE TABLE `fa_ipa_source` (
    `id` int unsigned NOT NULL,
    `name` varchar(128) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

Db::execute("CREATE TABLE `fa_ipa_asset` (
    `id` bigint unsigned NOT NULL,
    `source_id` int unsigned NOT NULL,
    `path` varchar(2048) NOT NULL,
    `name` varchar(512) NOT NULL,
    `size_bytes` bigint unsigned NOT NULL DEFAULT '0',
    `status` varchar(32) NOT NULL DEFAULT 'discovered',
    `bundle_id` varchar(255) NOT NULL DEFAULT '',
    `app_name` varchar(255) NOT NULL DEFAULT '',
    `app_version` varchar(128) NOT NULL DEFAULT '',
    `build_version` varchar(128) NOT NULL DEFAULT '',
    `last_error` text,
    `last_seen_at` int unsigned NOT NULL DEFAULT '0',
    `parsed_at` int unsigned NOT NULL DEFAULT '0',
    `updated_at` int unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `idx_source` (`source_id`),
    KEY `idx_bundle` (`bundle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

Db::execute("CREATE TABLE `fa_ipa_compare_result` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `asset_id` bigint unsigned NOT NULL,
    `status` varchar(32) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_asset_status` (`asset_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

Db::name('ipa_source')->insertAll([
    ['id' => 3, 'name' => 'OpenList Tokyo'],
    ['id' => 4, 'name' => 'Archive Mirror'],
]);

Db::name('ipa_asset')->insertAll([
    [
        'id' => 101,
        'source_id' => 3,
        'path' => '/games/agentofadventure2.ipa',
        'name' => 'agentofadventure2(20260913234717).ipa',
        'size_bytes' => 124127284,
        'status' => 'parsed',
        'bundle_id' => 'com.zonoe.agentofadventure2',
        'app_name' => 'Agent of Adventure 2',
        'app_version' => '2.0.1',
        'build_version' => '57',
        'last_error' => null,
        'last_seen_at' => 1800000000,
        'parsed_at' => 1800000001,
        'updated_at' => 1800000001,
    ],
    [
        'id' => 202,
        'source_id' => 4,
        'path' => '/games/othergame.ipa',
        'name' => 'othergame.ipa',
        'size_bytes' => 2048,
        'status' => 'discovered',
        'bundle_id' => 'com.example.othergame',
        'app_name' => 'Other Game',
        'app_version' => '1.4.0',
        'build_version' => '140',
        'last_error' => null,
        'last_seen_at' => 1800000000,
        'parsed_at' => 0,
        'updated_at' => 1800000001,
    ],
]);

Db::name('ipa_compare_result')->insert([
    'asset_id' => 101,
    'status' => 'anomaly',
]);

$proxy = new IpaAssetsSearchTestProxy();

ipaSearchAssertSame([101], $proxy->quickIds('agentofadventure2'), 'quick IPA filename');
ipaSearchAssertSame([101], $proxy->quickIds('com.zonoe.agent'), 'quick Bundle ID');
ipaSearchAssertSame([101], $proxy->quickIds('Adventure 2'), 'quick App name');
ipaSearchAssertSame([101], $proxy->quickIds('2.0.1'), 'quick Version');
ipaSearchAssertSame([101], $proxy->quickIds('57'), 'quick Build');
ipaSearchAssertSame([101], $proxy->quickIds('OpenList Tokyo'), 'quick OpenList source');
ipaSearchAssertSame([101], $proxy->quickIds('已解析'), 'quick localized status');
ipaSearchAssertSame([101], $proxy->quickIds('异常'), 'quick anomaly alias');
ipaSearchAssertSame([202], $proxy->quickIds('202'), 'quick numeric ID');

ipaSearchAssertSame([101], $proxy->commonIds(['id' => '101'], ['id' => '=']), 'common ID');
ipaSearchAssertSame([101], $proxy->commonIds(['name' => 'agentof'], ['name' => 'LIKE']), 'common IPA');
ipaSearchAssertSame([101], $proxy->commonIds(['bundle_id' => 'zonoe.agent'], ['bundle_id' => 'LIKE']), 'common Bundle ID');
ipaSearchAssertSame([101], $proxy->commonIds(['app_name' => 'Adventure'], ['app_name' => 'LIKE']), 'common App');
ipaSearchAssertSame([101], $proxy->commonIds(['app_version' => '2.0'], ['app_version' => 'LIKE']), 'common Version');
ipaSearchAssertSame([101], $proxy->commonIds(['build_version' => '57'], ['build_version' => 'LIKE']), 'common Build');
ipaSearchAssertSame([101], $proxy->commonIds(['status' => 'parsed'], ['status' => '=']), 'common status parsed');
ipaSearchAssertSame([202], $proxy->commonIds(['status' => 'discovered'], ['status' => '=']), 'common status discovered');
ipaSearchAssertSame([101], $proxy->commonIds(['source_name' => 'Tokyo'], ['source_name' => 'LIKE']), 'common source name');
ipaSearchAssertSame([101], $proxy->commonIds(['compare_state' => 'anomaly'], ['compare_state' => '=']), 'common compare state');

// FastAdmin commonSearch may keep stale quick-search text. Effective field filters must win.
ipaSearchAssertSame(
    [202],
    $proxy->resolvedIds('definitely-not-present', ['status' => 'discovered'], ['status' => '=']),
    'common status overrides stale quick search'
);
ipaSearchAssertSame(
    [101],
    $proxy->resolvedIds('agentofadventure2', [], []),
    'quick search still applies when common filters are empty'
);

fwrite(STDOUT, "OK ipa_asset_search_mysql_test\n");
