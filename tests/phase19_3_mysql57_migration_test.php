<?php

function p193MysqlFail($message)
{
    fwrite(STDERR, "FAIL phase19_3_mysql57_migration_test: {$message}\n");
    exit(1);
}

function p193MysqlAssert($condition, $message)
{
    if (!$condition) {
        p193MysqlFail($message);
    }
}

require_once dirname(__DIR__) . '/application/common/library/update/UpdateSqlRunner.php';

$host = getenv('ZONOE_MYSQL_HOST') ?: '127.0.0.1';
$port = (int)(getenv('ZONOE_MYSQL_PORT') ?: 3306);
$user = getenv('ZONOE_MYSQL_USER') ?: 'root';
$pass = getenv('ZONOE_MYSQL_PASSWORD') ?: 'root';
$db = getenv('ZONOE_MYSQL_DATABASE') ?: 'zonoe_test';

$mysqli = @new mysqli($host, $user, $pass, $db, $port);
if ($mysqli->connect_errno) {
    p193MysqlFail('connect failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8');

foreach (['fa_api_request_log', 'fa_api_endpoint', 'fa_kami_app', 'fa_black', 'fa_kami'] as $table) {
    p193MysqlAssert($mysqli->query("DROP TABLE IF EXISTS `{$table}`") === true, 'drop failed: ' . $table);
}

p193MysqlAssert($mysqli->query("CREATE TABLE `fa_kami` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `kami` varchar(100) NOT NULL DEFAULT '',
    `udid` varchar(100) NOT NULL DEFAULT '',
    `jh` tinyint unsigned NOT NULL DEFAULT 0,
    `endtime` int unsigned NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8") === true, 'fa_kami create failed');

p193MysqlAssert($mysqli->query("CREATE TABLE `fa_black` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `udid` varchar(100) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8") === true, 'fa_black create failed');

p193MysqlAssert($mysqli->query("CREATE TABLE `fa_kami_app` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `kami_id` int unsigned NOT NULL DEFAULT 0,
    `app_id` int unsigned NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8") === true, 'fa_kami_app create failed');

$sql = file_get_contents(dirname(__DIR__) . '/release/sql/2026091803_api_center_performance.sql');
p193MysqlAssert($sql !== false && trim($sql) !== '', 'Phase 19.3 migration missing');
$statements = \app\common\library\update\UpdateSqlRunner::splitStatements($sql);
p193MysqlAssert(count($statements) >= 15, 'migration splitter returned too few statements');

for ($passNo = 1; $passNo <= 2; $passNo++) {
    foreach ($statements as $statement) {
        $result = $mysqli->query($statement);
        if ($result === false) {
            p193MysqlFail('statement failed on pass ' . $passNo . ': ' . $mysqli->error . ' SQL=' . $statement);
        }
        if ($result instanceof mysqli_result) {
            $result->free();
        }
    }
}

$res = $mysqli->query("SELECT COUNT(*) AS c FROM `fa_api_endpoint` WHERE `source`='system'");
$row = $res->fetch_assoc();
p193MysqlAssert((int)$row['c'] === 6, 'system API seed count mismatch after idempotent migration');
$res->free();

foreach ([
    ['fa_kami', 'idx_zonoe_udid_auth'],
    ['fa_kami', 'idx_zonoe_kami_lookup'],
    ['fa_black', 'idx_zonoe_black_udid'],
    ['fa_kami_app', 'idx_zonoe_kami_app'],
] as $check) {
    list($table, $index) = $check;
    $res = $mysqli->query("SELECT COUNT(*) AS c FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . $mysqli->real_escape_string($table) . "' AND INDEX_NAME='" . $mysqli->real_escape_string($index) . "'");
    $row = $res->fetch_assoc();
    p193MysqlAssert((int)$row['c'] > 0, 'missing index ' . $index);
    $res->free();
}

$res = $mysqli->query("SHOW TABLES LIKE 'fa_api_request_log'");
p193MysqlAssert($res && $res->num_rows === 1, 'API request log table missing');
$res->free();

$mysqli->close();
fwrite(STDOUT, "OK phase19_3_mysql57_migration_test idempotent=passed api_schema=passed hot_indexes=passed\n");
