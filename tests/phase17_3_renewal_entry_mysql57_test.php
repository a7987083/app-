<?php

require_once dirname(__DIR__) . '/application/common/library/SourceAppRecord.php';

use app\common\library\SourceAppRecord;

function renewalMysqlFail($message)
{
    fwrite(STDERR, "FAIL phase17_3_renewal_entry_mysql57_test: {$message}\n");
    exit(1);
}

function renewalMysqlAssert($condition, $message)
{
    if (!$condition) {
        renewalMysqlFail($message);
    }
}

$host = getenv('ZONOE_MYSQL_HOST') ?: '127.0.0.1';
$port = (int)(getenv('ZONOE_MYSQL_PORT') ?: 3306);
$user = getenv('ZONOE_MYSQL_USER') ?: 'root';
$pass = getenv('ZONOE_MYSQL_PASSWORD') ?: 'root';
$db = getenv('ZONOE_MYSQL_DATABASE') ?: 'zonoe_test';

$mysqli = @new mysqli($host, $user, $pass, $db, $port);
if ($mysqli->connect_errno) {
    renewalMysqlFail('connect failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8');

function renewalMysqlExec(mysqli $db, $sql)
{
    if (!$db->multi_query($sql)) {
        renewalMysqlFail('SQL failed: ' . $db->error);
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
        renewalMysqlFail('SQL result failed: ' . $db->error);
    }
}

renewalMysqlExec($mysqli, "DROP TABLE IF EXISTS `fa_category`; CREATE TABLE `fa_category` (`id` int(10) unsigned NOT NULL AUTO_INCREMENT, `name` varchar(30) NOT NULL DEFAULT '', `bt2b` varchar(10) NOT NULL DEFAULT '0', PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8; INSERT INTO `fa_category` (`name`,`bt2b`) VALUES ('LEGACY-APP','1');");

// Critical 2026091710 regression check: before the renewal migration exists,
// the public source column list must not select renewal_entry.
$res = $mysqli->query('SHOW COLUMNS FROM `fa_category`');
renewalMysqlAssert($res !== false, 'SHOW COLUMNS failed before migration');
$available = [];
while ($column = $res->fetch_assoc()) {
    $available[] = $column['Field'];
}
$res->free();
$compatible = SourceAppRecord::publicSourceColumnsForSchema($available);
renewalMysqlAssert(!in_array('renewal_entry', $compatible, true), 'legacy schema selected missing renewal_entry');
renewalMysqlAssert(in_array('id', $compatible, true) && in_array('name', $compatible, true) && in_array('bt2b', $compatible, true), 'legacy compatible source columns incomplete');
$res = $mysqli->query('SELECT ' . implode(',', array_map(function ($column) {
    return '`' . str_replace('`', '``', $column) . '`';
}, $compatible)) . ' FROM `fa_category` LIMIT 1');
renewalMysqlAssert($res !== false && $res->num_rows === 1, 'legacy appstore-compatible SELECT failed: ' . $mysqli->error);
$res->free();

$sqlFile = dirname(__DIR__) . '/release/sql/2026091704_renewal_entry.sql';
$sql = file_get_contents($sqlFile);
renewalMysqlAssert($sql !== false && trim($sql) !== '', 'migration SQL missing');

// Online updates may retry the SQL directory after a partial failure, so this
// migration must be safe to execute repeatedly.
renewalMysqlExec($mysqli, $sql);
renewalMysqlExec($mysqli, $sql);

$res = $mysqli->query("SELECT COLUMN_DEFAULT, IS_NULLABLE, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_category' AND COLUMN_NAME='renewal_entry'");
renewalMysqlAssert($res && $res->num_rows === 1, 'renewal_entry column missing');
$column = $res->fetch_assoc();
renewalMysqlAssert((string)$column['COLUMN_DEFAULT'] === '0', 'renewal_entry default must be 0');
renewalMysqlAssert($column['IS_NULLABLE'] === 'NO', 'renewal_entry must be NOT NULL');
$res->free();

$res = $mysqli->query("SELECT renewal_entry FROM fa_category WHERE name='LEGACY-APP' LIMIT 1");
renewalMysqlAssert($res && $res->num_rows === 1, 'legacy app row missing after migration');
$row = $res->fetch_assoc();
renewalMysqlAssert((int)$row['renewal_entry'] === 0, 'legacy apps must remain ordinary apps');
$res->free();

renewalMysqlAssert($mysqli->query("UPDATE fa_category SET renewal_entry=1 WHERE name='LEGACY-APP'") === true, 'renewal flag update failed');
$res = $mysqli->query("SELECT renewal_entry FROM fa_category WHERE name='LEGACY-APP' LIMIT 1");
$row = $res->fetch_assoc();
renewalMysqlAssert((int)$row['renewal_entry'] === 1, 'renewal flag must persist');
$res->free();

// After migration, schema filtering must include renewal_entry again.
$res = $mysqli->query('SHOW COLUMNS FROM `fa_category`');
renewalMysqlAssert($res !== false, 'SHOW COLUMNS failed after migration');
$available = [];
while ($column = $res->fetch_assoc()) {
    $available[] = $column['Field'];
}
$res->free();
$compatible = SourceAppRecord::publicSourceColumnsForSchema($available);
renewalMysqlAssert(in_array('renewal_entry', $compatible, true), 'modern schema did not restore renewal_entry source field');

$mysqli->close();
fwrite(STDOUT, "OK phase17_3_renewal_entry_mysql57_test pre_migration_appstore=passed idempotent=passed legacy_default=passed flag=passed post_migration=passed\n");
