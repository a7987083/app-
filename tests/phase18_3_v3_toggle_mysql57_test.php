<?php

function p183MysqlFail($message)
{
    fwrite(STDERR, "FAIL phase18_3_v3_toggle_mysql57_test: {$message}\n");
    exit(1);
}

function p183MysqlAssert($condition, $message)
{
    if (!$condition) {
        p183MysqlFail($message);
    }
}

function p183MysqlExec(mysqli $db, $sql)
{
    if (!$db->multi_query($sql)) {
        p183MysqlFail('SQL failed: ' . $db->error);
    }
    do {
        if ($result = $db->store_result()) {
            $result->free();
        }
        if (!$db->more_results()) {
            break;
        }
    } while ($db->next_result());
    if ($db->errno) {
        p183MysqlFail('SQL result failed: ' . $db->error);
    }
}

$host = getenv('ZONOE_MYSQL_HOST') ?: '127.0.0.1';
$port = (int)(getenv('ZONOE_MYSQL_PORT') ?: 3306);
$user = getenv('ZONOE_MYSQL_USER') ?: 'root';
$pass = getenv('ZONOE_MYSQL_PASSWORD') ?: 'root';
$dbName = getenv('ZONOE_MYSQL_DATABASE') ?: 'zonoe_test';

$mysqli = @new mysqli($host, $user, $pass, $dbName, $port);
if ($mysqli->connect_errno) {
    p183MysqlFail('connect failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8');

$schema = "CREATE TABLE `fa_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(30) NOT NULL DEFAULT '',
  `group` varchar(30) NOT NULL DEFAULT '',
  `title` varchar(100) NOT NULL DEFAULT '',
  `tip` varchar(255) NOT NULL DEFAULT '',
  `type` varchar(30) NOT NULL DEFAULT '',
  `value` text NOT NULL,
  `content` text NOT NULL,
  `rule` varchar(100) NOT NULL DEFAULT '',
  `extend` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";

$sqlFile = dirname(__DIR__) . '/release/sql/2026091714_source_v3_toggle.sql';
$sql = file_get_contents($sqlFile);
p183MysqlAssert($sql !== false && trim($sql) !== '', 'migration SQL missing');

p183MysqlExec($mysqli, 'DROP TABLE IF EXISTS `fa_config`;');
p183MysqlExec($mysqli, $schema . ';');

// Fresh upgrade: V3 stays enabled so existing 1712/1713 behavior is preserved.
p183MysqlExec($mysqli, $sql);
p183MysqlExec($mysqli, $sql);
$res = $mysqli->query("SELECT COUNT(*) AS c, MAX(value) AS value, MAX(type) AS type FROM fa_config WHERE name='source_v3'");
p183MysqlAssert($res !== false, 'query failed');
$row = $res->fetch_assoc();
$res->free();
p183MysqlAssert((int)$row['c'] === 1, 'source_v3 row duplicated');
p183MysqlAssert($row['value'] === '1', 'fresh source_v3 must default enabled');
p183MysqlAssert($row['type'] === 'switch', 'source_v3 must render as switch');

// Admin-disabled V3 must survive repeated online-update migrations.
p183MysqlAssert($mysqli->query("UPDATE fa_config SET value='0' WHERE name='source_v3'") === true, 'failed to disable source_v3');
p183MysqlExec($mysqli, $sql);
$res = $mysqli->query("SELECT value FROM fa_config WHERE name='source_v3'");
$row = $res->fetch_assoc();
$res->free();
p183MysqlAssert($row['value'] === '0', 'disabled source_v3 was overwritten');

// Re-enabled V3 also survives replay.
p183MysqlAssert($mysqli->query("UPDATE fa_config SET value='1' WHERE name='source_v3'") === true, 'failed to enable source_v3');
p183MysqlExec($mysqli, $sql);
$res = $mysqli->query("SELECT value FROM fa_config WHERE name='source_v3'");
$row = $res->fetch_assoc();
$res->free();
p183MysqlAssert($row['value'] === '1', 'enabled source_v3 was overwritten');

// Invalid historical data fails safe to enabled so legacy behavior is not broken.
p183MysqlAssert($mysqli->query("UPDATE fa_config SET value='broken' WHERE name='source_v3'") === true, 'failed to prepare invalid value');
p183MysqlExec($mysqli, $sql);
$res = $mysqli->query("SELECT value FROM fa_config WHERE name='source_v3'");
$row = $res->fetch_assoc();
$res->free();
p183MysqlAssert($row['value'] === '1', 'invalid source_v3 must fail safe to enabled');

$mysqli->close();
fwrite(STDOUT, "OK phase18_3_v3_toggle_mysql57_test default_on=passed off_persist=passed on_persist=passed idempotent=passed\n");
