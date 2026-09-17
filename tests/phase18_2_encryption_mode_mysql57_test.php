<?php

function p182MysqlFail($message)
{
    fwrite(STDERR, "FAIL phase18_2_encryption_mode_mysql57_test: {$message}\n");
    exit(1);
}

function p182MysqlAssert($condition, $message)
{
    if (!$condition) {
        p182MysqlFail($message);
    }
}

function p182MysqlExec(mysqli $db, $sql)
{
    if (!$db->multi_query($sql)) {
        p182MysqlFail('SQL failed: ' . $db->error);
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
        p182MysqlFail('SQL result failed: ' . $db->error);
    }
}

$host = getenv('ZONOE_MYSQL_HOST') ?: '127.0.0.1';
$port = (int)(getenv('ZONOE_MYSQL_PORT') ?: 3306);
$user = getenv('ZONOE_MYSQL_USER') ?: 'root';
$pass = getenv('ZONOE_MYSQL_PASSWORD') ?: 'root';
$dbName = getenv('ZONOE_MYSQL_DATABASE') ?: 'zonoe_test';

$mysqli = @new mysqli($host, $user, $pass, $dbName, $port);
if ($mysqli->connect_errno) {
    p182MysqlFail('connect failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8');

$schema = "CREATE TABLE `fa_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(30) NOT NULL DEFAULT '',
  `group` varchar(30) NOT NULL DEFAULT '',
  `title` varchar(100) NOT NULL DEFAULT '',
  `tip` varchar(100) NOT NULL DEFAULT '',
  `type` varchar(30) NOT NULL DEFAULT '',
  `value` text NOT NULL,
  `content` text NOT NULL,
  `rule` varchar(100) NOT NULL DEFAULT '',
  `extend` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";

$sqlFile = dirname(__DIR__) . '/release/sql/2026091713_source_encryption_mode.sql';
$sql = file_get_contents($sqlFile);
p182MysqlAssert($sql !== false && trim($sql) !== '', 'migration SQL missing');

p182MysqlExec($mysqli, 'DROP TABLE IF EXISTS `fa_config`;');
p182MysqlExec($mysqli, $schema . ';');
p182MysqlAssert($mysqli->query("INSERT INTO fa_config (`name`,`group`,`title`,`tip`,`type`,`value`,`content`,`rule`,`extend`) VALUES ('opencry','basic','旧软件源加密','','switch','1','','','')") === true, 'legacy config insert failed');

// Existing deployments using old opencry=1 must keep normal encryption.
p182MysqlExec($mysqli, $sql);
p182MysqlExec($mysqli, $sql);
$res = $mysqli->query("SELECT `name`,`type`,`value`,`content`,`title` FROM fa_config WHERE name='opencry'");
p182MysqlAssert($res !== false && $res->num_rows === 1, 'opencry row duplicated or missing');
$row = $res->fetch_assoc();
$res->free();
p182MysqlAssert($row['type'] === 'radio', 'opencry type must become radio');
p182MysqlAssert($row['value'] === '1', 'legacy normal encryption value was not preserved');
p182MysqlAssert($row['title'] === '软件源加密', 'config title mismatch');
$choices = json_decode($row['content'], true);
p182MysqlAssert(is_array($choices) && isset($choices['0'], $choices['1'], $choices['2']), 'three encryption choices missing');
p182MysqlAssert($choices['0'] === '关闭' && $choices['1'] === '普通' && $choices['2'] === 'V2', 'encryption labels mismatch');

// V2 must survive replayed online-update migrations.
p182MysqlAssert($mysqli->query("UPDATE fa_config SET value='2' WHERE name='opencry'") === true, 'failed to select V2');
p182MysqlExec($mysqli, $sql);
$res = $mysqli->query("SELECT value FROM fa_config WHERE name='opencry'");
$row = $res->fetch_assoc();
$res->free();
p182MysqlAssert($row['value'] === '2', 'V2 selection was overwritten by replay');

// Invalid historical values fail safe to plaintext.
p182MysqlAssert($mysqli->query("UPDATE fa_config SET value='unexpected' WHERE name='opencry'") === true, 'failed to prepare invalid value');
p182MysqlExec($mysqli, $sql);
$res = $mysqli->query("SELECT value FROM fa_config WHERE name='opencry'");
$row = $res->fetch_assoc();
$res->free();
p182MysqlAssert($row['value'] === '0', 'invalid selection must migrate to off');

// Fresh/missing opencry row must be created exactly once.
p182MysqlAssert($mysqli->query("DELETE FROM fa_config WHERE name='opencry'") === true, 'failed to remove opencry row');
p182MysqlExec($mysqli, $sql);
p182MysqlExec($mysqli, $sql);
$res = $mysqli->query("SELECT COUNT(*) AS c, MAX(value) AS value FROM fa_config WHERE name='opencry'");
$row = $res->fetch_assoc();
$res->free();
p182MysqlAssert((int)$row['c'] === 1, 'missing-row migration is not idempotent');
p182MysqlAssert($row['value'] === '0', 'fresh opencry must default to off');

$mysqli->close();
fwrite(STDOUT, "OK phase18_2_encryption_mode_mysql57_test schema=passed legacy=passed v2=passed idempotent=passed\n");
