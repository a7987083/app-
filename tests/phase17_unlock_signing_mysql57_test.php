<?php

function phase17MysqlFail($message)
{
    fwrite(STDERR, "FAIL phase17_unlock_signing_mysql57_test: {$message}\n");
    exit(1);
}

function phase17MysqlAssert($condition, $message)
{
    if (!$condition) {
        phase17MysqlFail($message);
    }
}

$host = getenv('ZONOE_MYSQL_HOST') ?: '127.0.0.1';
$port = (int)(getenv('ZONOE_MYSQL_PORT') ?: 3306);
$user = getenv('ZONOE_MYSQL_USER') ?: 'root';
$pass = getenv('ZONOE_MYSQL_PASSWORD') ?: 'root';
$db = getenv('ZONOE_MYSQL_DATABASE') ?: 'zonoe_test';

$mysqli = @new mysqli($host, $user, $pass, $db, $port);
if ($mysqli->connect_errno) {
    phase17MysqlFail('connect failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8');

function phase17MysqlExec(mysqli $db, $sql)
{
    if (!$db->multi_query($sql)) {
        phase17MysqlFail('SQL failed: ' . $db->error);
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
        phase17MysqlFail('SQL result failed: ' . $db->error);
    }
}

phase17MysqlExec($mysqli, "DROP TABLE IF EXISTS `fa_config`; CREATE TABLE `fa_config` (`id` int(10) unsigned NOT NULL AUTO_INCREMENT, `name` varchar(30) NOT NULL DEFAULT '', `group` varchar(30) NOT NULL DEFAULT '', `title` varchar(100) NOT NULL DEFAULT '', `tip` varchar(100) NOT NULL DEFAULT '', `type` varchar(30) NOT NULL DEFAULT '', `value` text NOT NULL, `content` text NOT NULL, `rule` varchar(100) NOT NULL DEFAULT '', `extend` varchar(255) NOT NULL DEFAULT '', PRIMARY KEY (`id`), UNIQUE KEY `name` (`name`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

$sqlFile = dirname(__DIR__) . '/release/sql/2026091701_unlock_signing.sql';
$sql = file_get_contents($sqlFile);
phase17MysqlAssert($sql !== false && trim($sql) !== '', 'migration SQL missing');

phase17MysqlExec($mysqli, $sql);
phase17MysqlExec($mysqli, $sql);

$res = $mysqli->query("SELECT name, `group`, title, type, value FROM fa_config WHERE name='unlock_sign_key'");
phase17MysqlAssert($res && $res->num_rows === 1, 'unlock_sign_key row must exist exactly once');
$row = $res->fetch_assoc();
phase17MysqlAssert($row['group'] === 'basic', 'unlock_sign_key must be in basic group');
phase17MysqlAssert($row['title'] === '解锁签名KEY', 'unlock_sign_key title mismatch');
phase17MysqlAssert($row['type'] === 'string', 'unlock_sign_key type must be string');
phase17MysqlAssert($row['value'] === '', 'repository migration must not hardcode a secret key');
$res->free();

phase17MysqlAssert($mysqli->query("UPDATE fa_config SET value='SERVER-OWNED-KEY' WHERE name='unlock_sign_key'") === true, 'failed to set test key');
phase17MysqlExec($mysqli, $sql);
$res = $mysqli->query("SELECT value FROM fa_config WHERE name='unlock_sign_key' LIMIT 1");
phase17MysqlAssert($res && $res->num_rows === 1, 'unlock_sign_key missing after rerun');
$row = $res->fetch_assoc();
phase17MysqlAssert($row['value'] === 'SERVER-OWNED-KEY', 'migration must preserve an existing configured key');
$res->free();

$mysqli->close();
fwrite(STDOUT, "OK phase17_unlock_signing_mysql57_test idempotent=passed secret_preserved=passed no_hardcoded_key=passed\n");
