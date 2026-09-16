<?php

function phase174MysqlFail($message)
{
    fwrite(STDERR, "FAIL phase17_4_admin_hotfix_mysql57_test: {$message}\n");
    exit(1);
}

function phase174MysqlAssert($condition, $message)
{
    if (!$condition) {
        phase174MysqlFail($message);
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
    phase174MysqlFail('connect failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8');

phase174MysqlAssert($mysqli->query('DROP TABLE IF EXISTS `fa_category`') === true, 'drop failed');
phase174MysqlAssert($mysqli->query("CREATE TABLE `fa_category` (`id` int unsigned NOT NULL AUTO_INCREMENT, `name` varchar(30) NOT NULL DEFAULT '', `bt2b` varchar(10) NOT NULL DEFAULT '0', PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8") === true, 'legacy table create failed');
phase174MysqlAssert($mysqli->query("INSERT INTO `fa_category` (`name`,`bt2b`) VALUES ('LEGACY','1')") === true, 'legacy row insert failed');

$sqlFile = dirname(__DIR__) . '/release/sql/2026091705_renewal_entry_repair.sql';
$sql = file_get_contents($sqlFile);
phase174MysqlAssert($sql !== false && trim($sql) !== '', 'repair migration missing');

$statements = \app\common\library\update\UpdateSqlRunner::splitStatements($sql);
phase174MysqlAssert(count($statements) >= 5, 'online-update SQL splitter returned too few statements');

for ($passNo = 1; $passNo <= 2; $passNo++) {
    foreach ($statements as $statement) {
        $result = $mysqli->query($statement);
        if ($result === false) {
            phase174MysqlFail('sequential statement failed on pass ' . $passNo . ': ' . $mysqli->error . ' SQL=' . $statement);
        }
        if ($result instanceof mysqli_result) {
            $result->free();
        }
    }
}

$res = $mysqli->query("SELECT COLUMN_DEFAULT, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_category' AND COLUMN_NAME='renewal_entry'");
phase174MysqlAssert($res && $res->num_rows === 1, 'renewal_entry not created by sequential runner semantics');
$column = $res->fetch_assoc();
phase174MysqlAssert((string)$column['COLUMN_DEFAULT'] === '0', 'renewal_entry default mismatch');
phase174MysqlAssert($column['IS_NULLABLE'] === 'NO', 'renewal_entry must be NOT NULL');
$res->free();

phase174MysqlAssert($mysqli->query("UPDATE `fa_category` SET `renewal_entry`=1 WHERE `name`='LEGACY'") === true, 'renewal_entry update failed');
$res = $mysqli->query("SELECT `renewal_entry` FROM `fa_category` WHERE `name`='LEGACY' LIMIT 1");
$row = $res->fetch_assoc();
phase174MysqlAssert((int)$row['renewal_entry'] === 1, 'renewal_entry did not persist');
$res->free();

$mysqli->close();
fwrite(STDOUT, "OK phase17_4_admin_hotfix_mysql57_test splitter=passed idempotent=passed schema=passed\n");
