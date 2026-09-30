<?php

$root = dirname(__DIR__);
define('APP_PATH', $root . '/application/');
require $root . '/thinkphp/base.php';
require_once $root . '/application/common/library/Ipa/IpaOverviewService.php';

use app\common\library\Ipa\IpaOverviewService;
use think\Config;
use think\Db;

function overviewAssertSame($expected, $actual, $label)
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL ipa_overview_mysql_test: {$label}; expected=" . var_export($expected, true) . "; actual=" . var_export($actual, true) . "\n");
        exit(1);
    }
}

$db = getenv('IPA_OVERVIEW_DB') ?: 'ipa_overview_ci';
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

foreach (['fa_dylib_verify_log', 'fa_dylib', 'fa_ipa_scan_item', 'fa_ipa_asset', 'fa_ipa_source'] as $table) {
    Db::execute("DROP TABLE IF EXISTS `{$table}`");
}

Db::execute("CREATE TABLE `fa_ipa_source` (
    `id` int unsigned NOT NULL,
    `name` varchar(128) NOT NULL,
    `base_url` varchar(512) NOT NULL DEFAULT '',
    `root_path` varchar(512) NOT NULL DEFAULT '/',
    `enabled` tinyint unsigned NOT NULL DEFAULT '1',
    `scan_page_size` int unsigned NOT NULL DEFAULT '500',
    `request_timeout` int unsigned NOT NULL DEFAULT '20',
    `updated_at` int unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

Db::execute("CREATE TABLE `fa_ipa_asset` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `status` varchar(32) NOT NULL DEFAULT 'discovered',
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

Db::execute("CREATE TABLE `fa_ipa_scan_item` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `status` varchar(32) NOT NULL DEFAULT 'pending',
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

Db::execute("CREATE TABLE `fa_dylib` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

Db::execute("CREATE TABLE `fa_dylib_verify_log` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `created_at` int unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$now = 1800000000;

Db::name('ipa_source')->insertAll([
    ['id'=>2,'name'=>'Disabled Mirror','base_url'=>'https://mirror.example','root_path'=>'/ipa','enabled'=>0,'scan_page_size'=>200,'request_timeout'=>30,'updated_at'=>20],
    ['id'=>7,'name'=>'Primary OpenList','base_url'=>'https://openlist.example','root_path'=>'/apps','enabled'=>1,'scan_page_size'=>500,'request_timeout'=>20,'updated_at'=>70],
]);

foreach (['discovered','discovered','parsed','parse_failed','missing','parsing'] as $status) {
    Db::name('ipa_asset')->insert(['status'=>$status]);
}
foreach (['pending','pending','processing','failed','done','failed'] as $status) {
    Db::name('ipa_scan_item')->insert(['status'=>$status]);
}
Db::name('dylib')->insertAll([[], [], []]);
Db::name('dylib_verify_log')->insertAll([
    ['created_at'=>$now - 30],
    ['created_at'=>$now - 86399],
    ['created_at'=>$now - 86400],
    ['created_at'=>$now - 86401],
]);

// This reproduces the exact 2424 controller algorithm. The refactored service
// must remain byte-for-byte equivalent at the returned PHP-array contract level.
$legacySources = Db::name('ipa_source')
    ->field('id,name,base_url,root_path,enabled,scan_page_size,request_timeout,updated_at')
    ->order('id desc')
    ->select();
$legacySummary = [
    'sources'=>(int)Db::name('ipa_source')->count(),
    'assets'=>(int)Db::name('ipa_asset')->count(),
    'pending'=>(int)Db::name('ipa_scan_item')->where('status','pending')->count(),
    'failed'=>(int)Db::name('ipa_scan_item')->where('status','failed')->count(),
    'parse_pending'=>(int)Db::name('ipa_asset')->where('status','discovered')->count(),
    'parse_failed'=>(int)Db::name('ipa_asset')->where('status','parse_failed')->count(),
    'dylibs'=>(int)Db::name('dylib')->count(),
    'verify24h'=>(int)Db::name('dylib_verify_log')->where('created_at','>=',$now-86400)->count(),
];

$actual = IpaOverviewService::snapshot($now);
overviewAssertSame($legacySummary, $actual['summary'], 'seeded summary matches 2424');
overviewAssertSame($legacySources, $actual['sources'], 'source rows/order match 2424');
overviewAssertSame(2, $actual['summary']['parse_pending'], 'discovered count');
overviewAssertSame(2, $actual['summary']['pending'], 'scan pending count');
overviewAssertSame(3, $actual['summary']['verify24h'], '24h boundary is inclusive');
overviewAssertSame(7, (int)$actual['sources'][0]['id'], 'source order remains id desc');

// Empty-state behavior is also part of the page contract.
foreach (['ipa_source','ipa_asset','ipa_scan_item','dylib','dylib_verify_log'] as $table) {
    Db::name($table)->delete(true);
}
$empty = IpaOverviewService::snapshot($now);
overviewAssertSame([
    'sources'=>0,
    'assets'=>0,
    'pending'=>0,
    'failed'=>0,
    'parse_pending'=>0,
    'parse_failed'=>0,
    'dylibs'=>0,
    'verify24h'=>0,
], $empty['summary'], 'empty summary');
overviewAssertSame([], $empty['sources'], 'empty sources');

fwrite(STDOUT, "OK ipa_overview_mysql_test\n");
