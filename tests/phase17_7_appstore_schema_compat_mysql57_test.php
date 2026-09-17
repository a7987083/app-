<?php

require_once dirname(__DIR__) . '/application/common/library/SourceAppRecord.php';

use app\common\library\SourceAppRecord;

function p177MysqlFail($message)
{
    fwrite(STDERR, "FAIL phase17_7_appstore_schema_compat_mysql57_test: {$message}\n");
    exit(1);
}

function p177MysqlAssert($condition, $message)
{
    if (!$condition) {
        p177MysqlFail($message);
    }
}

$host = getenv('ZONOE_MYSQL_HOST') ?: '127.0.0.1';
$port = (int)(getenv('ZONOE_MYSQL_PORT') ?: 3306);
$user = getenv('ZONOE_MYSQL_USER') ?: 'root';
$pass = getenv('ZONOE_MYSQL_PASSWORD') ?: 'root';
$db = getenv('ZONOE_MYSQL_DATABASE') ?: 'zonoe_test';

$mysqli = @new mysqli($host, $user, $pass, $db, $port);
if ($mysqli->connect_errno) {
    p177MysqlFail('connect failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8');

p177MysqlAssert($mysqli->query('DROP TABLE IF EXISTS `fa_category`') === true, 'drop failed');
$create = "CREATE TABLE `fa_category` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `pid` int unsigned NOT NULL DEFAULT 0,
    `type` varchar(30) NOT NULL DEFAULT 'default',
    `name` varchar(100) NOT NULL DEFAULT '',
    `nickname` varchar(100) NOT NULL DEFAULT '',
    `keywords` text,
    `bt1a` text,
    `bt1b` varchar(32) NOT NULL DEFAULT '',
    `bt2a` bigint NOT NULL DEFAULT 0,
    `bt2b` varchar(10) NOT NULL DEFAULT '0',
    `flag` varchar(30) NOT NULL DEFAULT '0',
    `image` text,
    `updatetime` int unsigned NOT NULL DEFAULT 0,
    `beizhu` text,
    `weigh` int NOT NULL DEFAULT 0,
    `status` varchar(30) NOT NULL DEFAULT 'normal',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
p177MysqlAssert($mysqli->query($create) === true, 'legacy table create failed: ' . $mysqli->error);
p177MysqlAssert($mysqli->query("INSERT INTO `fa_category` (`name`,`nickname`,`bt1a`,`bt2b`,`status`,`weigh`) VALUES ('Legacy','1.0','https://example.test/a.ipa','0','normal',100)") === true, 'legacy insert failed');

function p177AvailableColumns(mysqli $mysqli)
{
    $res = $mysqli->query('SHOW COLUMNS FROM `fa_category`');
    if (!$res) {
        p177MysqlFail('SHOW COLUMNS failed: ' . $mysqli->error);
    }
    $columns = [];
    while ($row = $res->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    $res->free();
    return $columns;
}

$legacyColumns = SourceAppRecord::publicSourceColumnsForSchema(p177AvailableColumns($mysqli));
p177MysqlAssert(!in_array('renewal_entry', $legacyColumns, true), 'legacy schema unexpectedly selected renewal_entry');
p177MysqlAssert(count($legacyColumns) > 0, 'legacy compatible column list empty');
$sql = 'SELECT ' . implode(',', array_map(function ($column) {
    return '`' . str_replace('`', '``', $column) . '`';
}, $legacyColumns)) . " FROM `fa_category` WHERE `status`='normal' ORDER BY `weigh` DESC";
$res = $mysqli->query($sql);
p177MysqlAssert($res !== false && $res->num_rows === 1, 'legacy appstore SELECT failed: ' . $mysqli->error);
$row = $res->fetch_assoc();
p177MysqlAssert($row['name'] === 'Legacy', 'legacy row mismatch');
$res->free();

p177MysqlAssert($mysqli->query("ALTER TABLE `fa_category` ADD COLUMN `renewal_entry` tinyint(1) unsigned NOT NULL DEFAULT '0' AFTER `bt2b`") === true, 'renewal column add failed');
$modernColumns = SourceAppRecord::publicSourceColumnsForSchema(p177AvailableColumns($mysqli));
p177MysqlAssert(in_array('renewal_entry', $modernColumns, true), 'modern schema did not select renewal_entry');
$sql = 'SELECT ' . implode(',', array_map(function ($column) {
    return '`' . str_replace('`', '``', $column) . '`';
}, $modernColumns)) . " FROM `fa_category` WHERE `status`='normal' ORDER BY `weigh` DESC";
$res = $mysqli->query($sql);
p177MysqlAssert($res !== false && $res->num_rows === 1, 'modern appstore SELECT failed: ' . $mysqli->error);
$row = $res->fetch_assoc();
p177MysqlAssert(array_key_exists('renewal_entry', $row), 'renewal field missing from modern SELECT');
$res->free();

$mysqli->close();
fwrite(STDOUT, "OK phase17_7_appstore_schema_compat_mysql57_test legacy_schema=passed modern_schema=passed\n");
