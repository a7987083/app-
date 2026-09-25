<?php

$root = dirname(__DIR__);
define('APP_PATH', $root . '/application/');
require $root . '/thinkphp/base.php';
require_once $root . '/application/common/library/CardAccessPolicy.php';
require_once $root . '/application/common/library/Ipa/DylibRuntimeAccessService.php';

use app\common\library\Ipa\DylibRuntimeAccessService;
use think\Config;
use think\Db;

function runtimeAssertSame($expected, $actual, $label)
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL dylib_runtime_access_mysql_test: {$label}; expected=" . var_export($expected, true) . "; actual=" . var_export($actual, true) . "\n");
        exit(1);
    }
}

function runtimeAssert($condition, $label)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL dylib_runtime_access_mysql_test: {$label}\n");
        exit(1);
    }
}

$db = getenv('DYL_RUNTIME_DB') ?: 'dylib_runtime_ci';
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

foreach ([
    'fa_kami_app',
    'fa_kami',
    'fa_ipa_app_identity',
    'fa_ipa_category_binding',
    'fa_ipa_asset',
    'fa_category',
] as $table) {
    Db::execute("DROP TABLE IF EXISTS `{$table}`");
}

Db::execute("CREATE TABLE `fa_category` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL DEFAULT '',
    `status` varchar(32) NOT NULL DEFAULT 'normal',
    `bt2b` tinyint unsigned NOT NULL DEFAULT '1',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

Db::execute("CREATE TABLE `fa_ipa_asset` (
    `id` bigint unsigned NOT NULL,
    `status` varchar(32) NOT NULL DEFAULT 'discovered',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

Db::execute("CREATE TABLE `fa_ipa_category_binding` (
    `asset_id` bigint unsigned NOT NULL,
    `category_id` int unsigned NOT NULL,
    `status` varchar(32) NOT NULL DEFAULT 'active',
    KEY `idx_asset` (`asset_id`),
    KEY `idx_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

Db::execute("CREATE TABLE `fa_ipa_app_identity` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `asset_id` bigint unsigned NOT NULL,
    `bundle_id` varchar(255) NOT NULL DEFAULT '',
    `executable` varchar(255) NOT NULL DEFAULT '',
    `macho_uuid` varchar(64) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_asset_identity` (`asset_id`),
    KEY `idx_runtime_identity` (`bundle_id`(191),`executable`(191),`macho_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

Db::execute("CREATE TABLE `fa_kami` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `udid` varchar(255) NOT NULL DEFAULT '',
    `jh` tinyint unsigned NOT NULL DEFAULT '0',
    `endtime` int unsigned NOT NULL DEFAULT '0',
    `card_scope` tinyint unsigned NOT NULL DEFAULT '1',
    PRIMARY KEY (`id`),
    KEY `idx_udid` (`udid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

Db::execute("CREATE TABLE `fa_kami_app` (
    `kami_id` int unsigned NOT NULL,
    `app_id` int unsigned NOT NULL,
    UNIQUE KEY `uk_kami_app` (`kami_id`,`app_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$now = 1800000000;
$future = $now + 86400;

Db::name('category')->insertAll([
    ['id' => 101, 'name' => 'Game A', 'status' => 'normal', 'bt2b' => 1],
    ['id' => 202, 'name' => 'Game B', 'status' => 'normal', 'bt2b' => 1],
]);
Db::name('ipa_asset')->insertAll([
    ['id' => 1001, 'status' => 'parsed'],
    ['id' => 2001, 'status' => 'parsed'],
]);
Db::name('ipa_category_binding')->insertAll([
    ['asset_id' => 1001, 'category_id' => 101, 'status' => 'active'],
    ['asset_id' => 2001, 'category_id' => 202, 'status' => 'active'],
]);
Db::name('ipa_app_identity')->insertAll([
    ['asset_id' => 1001, 'bundle_id' => 'com.example.gamea', 'executable' => 'GameA', 'macho_uuid' => 'AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE'],
    ['asset_id' => 2001, 'bundle_id' => 'com.example.gameb', 'executable' => 'GameB', 'macho_uuid' => '11111111-2222-3333-4444-555555555555'],
]);

$identityA = DylibRuntimeAccessService::resolveAppIdentity([
    'protocol_version' => 2,
    'bundle_id' => 'com.example.gamea',
    'app_executable' => 'GameA',
    'app_macho_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
]);
$identityB = DylibRuntimeAccessService::resolveAppIdentity([
    'protocol_version' => 2,
    'bundle_id' => 'com.example.gameb',
    'app_executable' => 'GameB',
    'app_macho_uuid' => '11111111-2222-3333-4444-555555555555',
]);
runtimeAssertSame(true, $identityA['resolved'], 'Game A identity must resolve');
runtimeAssertSame(101, $identityA['category_id'], 'Game A category id');
runtimeAssertSame(true, $identityB['resolved'], 'Game B identity must resolve');
runtimeAssertSame(202, $identityB['category_id'], 'Game B category id');

$noCard = DylibRuntimeAccessService::resolveAccess('UDID-NONE', $now, $identityA);
runtimeAssertSame('block', $noCard['access_level'], 'no card must block');
runtimeAssertSame(false, $noCard['permissions']['normal_menu'], 'block normal_menu false');

Db::name('kami')->insert([
    'udid' => 'UDID-VERIFY', 'jh' => 1, 'endtime' => $future, 'card_scope' => 2,
]);
$verifyAccess = DylibRuntimeAccessService::resolveAccess('UDID-VERIFY', $now, $identityA);
runtimeAssertSame('basic', $verifyAccess['access_level'], 'scope=2 must resolve basic');
runtimeAssertSame(true, $verifyAccess['permissions']['normal_menu'], 'scope=2 normal menu');
runtimeAssertSame(false, $verifyAccess['permissions']['extra_menu'], 'scope=2 extra menu false');

$appCardId = Db::name('kami')->insertGetId([
    'udid' => 'UDID-APP', 'jh' => 1, 'endtime' => $future, 'card_scope' => 3,
]);
Db::table('fa_kami_app')->insert(['kami_id' => $appCardId, 'app_id' => 101]);
$appMatch = DylibRuntimeAccessService::resolveAccess('UDID-APP', $now, $identityA);
runtimeAssertSame('app_plus', $appMatch['access_level'], 'scope=3 matching App must resolve app_plus');
runtimeAssertSame(true, $appMatch['permissions']['extra_features'], 'scope=3 match extra features');
$appMismatch = DylibRuntimeAccessService::resolveAccess('UDID-APP', $now, $identityB);
runtimeAssertSame('block', $appMismatch['access_level'], 'scope=3 different App must block without fallback card');
runtimeAssertSame('app_not_authorized', $appMismatch['reason'], 'scope=3 different App reason');

$spoofIdentity = DylibRuntimeAccessService::resolveAppIdentity([
    'protocol_version' => 2,
    'bundle_id' => 'com.example.gamea',
    'app_executable' => 'GameB',
    'app_macho_uuid' => '11111111-2222-3333-4444-555555555555',
]);
runtimeAssertSame(false, $spoofIdentity['resolved'], 'changing only BundleID must not resolve another App as Game A');
runtimeAssertSame('app_identity_unknown', $spoofIdentity['code'], 'BundleID-only spoof identity code');
$spoofAccess = DylibRuntimeAccessService::resolveAccess('UDID-APP', $now, $spoofIdentity);
runtimeAssertSame('block', $spoofAccess['access_level'], 'BundleID-only spoof must not receive app_plus');
runtimeAssertSame('app_identity_required', $spoofAccess['reason'], 'BundleID-only spoof must require valid identity');

Db::name('kami')->insert([
    'udid' => 'UDID-GLOBAL', 'jh' => 1, 'endtime' => $future, 'card_scope' => 1,
]);
$globalAccess = DylibRuntimeAccessService::resolveAccess('UDID-GLOBAL', $now, $spoofIdentity);
runtimeAssertSame('global_plus', $globalAccess['access_level'], 'scope=1 must resolve global_plus without App restriction');
runtimeAssertSame(true, $globalAccess['permissions']['extra_menu'], 'scope=1 extra menu');

Db::name('kami')->insert([
    'udid' => 'UDID-MULTI', 'jh' => 1, 'endtime' => $future, 'card_scope' => 2,
]);
$multiAppCardId = Db::name('kami')->insertGetId([
    'udid' => 'UDID-MULTI', 'jh' => 1, 'endtime' => $future, 'card_scope' => 3,
]);
Db::table('fa_kami_app')->insert(['kami_id' => $multiAppCardId, 'app_id' => 101]);
$multiA = DylibRuntimeAccessService::resolveAccess('UDID-MULTI', $now, $identityA);
runtimeAssertSame('app_plus', $multiA['access_level'], 'scope=2 + matching scope=3 must select app_plus');
$multiB = DylibRuntimeAccessService::resolveAccess('UDID-MULTI', $now, $identityB);
runtimeAssertSame('basic', $multiB['access_level'], 'scope=2 + non-matching scope=3 must fall back to basic');

Db::name('ipa_asset')->where('id', 1001)->update(['status' => 'discovered']);
$staleIdentity = DylibRuntimeAccessService::resolveAppIdentity([
    'protocol_version' => 2,
    'bundle_id' => 'com.example.gamea',
    'app_executable' => 'GameA',
    'app_macho_uuid' => 'AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE',
]);
runtimeAssertSame(false, $staleIdentity['resolved'], 'identity must become stale when parent IPA leaves parsed state');
runtimeAssertSame('app_identity_stale', $staleIdentity['code'], 'stale identity code');

runtimeAssert(true, true, 'completed');
echo "OK dylib_runtime_access_mysql_test\n";
