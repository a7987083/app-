<?php

function mysql57Fail($message)
{
    fwrite(STDERR, "FAIL phase16_mysql57_migration_test: {$message}\n");
    exit(1);
}

function mysql57Assert($condition, $message)
{
    if (!$condition) {
        mysql57Fail($message);
    }
}

$host = getenv('ZONOE_MYSQL_HOST') ?: '127.0.0.1';
$port = (int)(getenv('ZONOE_MYSQL_PORT') ?: 3306);
$user = getenv('ZONOE_MYSQL_USER') ?: 'root';
$pass = getenv('ZONOE_MYSQL_PASSWORD') ?: 'root';
$db = getenv('ZONOE_MYSQL_DATABASE') ?: 'zonoe_test';

$mysqli = @new mysqli($host, $user, $pass, $db, $port);
if ($mysqli->connect_errno) {
    mysql57Fail('connect failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8');

function mysql57Exec(mysqli $db, $sql)
{
    if (!$db->multi_query($sql)) {
        mysql57Fail('SQL failed: ' . $db->error);
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
        mysql57Fail('SQL result failed: ' . $db->error);
    }
}

mysql57Exec($mysqli, "DROP TABLE IF EXISTS `fa_kami_app`; DROP TABLE IF EXISTS `fa_kami`; CREATE TABLE `fa_kami` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT, `kami` varchar(128) NOT NULL DEFAULT '', `transfer_count` int(10) unsigned NOT NULL DEFAULT '100', PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8; INSERT INTO `fa_kami` (`kami`,`transfer_count`) VALUES ('LEGACY-CARD',100);");

$sqlFile = dirname(__DIR__) . '/release/sql/2026091611_card_scope.sql';
$sql = file_get_contents($sqlFile);
mysql57Assert($sql !== false && trim($sql) !== '', 'migration SQL missing');

// Run twice: the online migration must be idempotent.
mysql57Exec($mysqli, $sql);
mysql57Exec($mysqli, $sql);

$res = $mysqli->query("SELECT COLUMN_DEFAULT, IS_NULLABLE, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_kami' AND COLUMN_NAME='card_scope'");
mysql57Assert($res && $res->num_rows === 1, 'card_scope column missing');
$column = $res->fetch_assoc();
mysql57Assert((string)$column['COLUMN_DEFAULT'] === '1', 'card_scope default must be 1');
mysql57Assert($column['IS_NULLABLE'] === 'NO', 'card_scope must be NOT NULL');
$res->free();

$res = $mysqli->query("SELECT card_scope FROM fa_kami WHERE kami='LEGACY-CARD' LIMIT 1");
mysql57Assert($res && $res->num_rows === 1, 'legacy row missing after migration');
$row = $res->fetch_assoc();
mysql57Assert((int)$row['card_scope'] === 1, 'legacy card must migrate to whole-source scope');
$res->free();

$res = $mysqli->query("SHOW TABLES LIKE 'fa_kami_app'");
mysql57Assert($res && $res->num_rows === 1, 'fa_kami_app table missing');
$res->free();

$res = $mysqli->query("SHOW INDEX FROM fa_kami_app WHERE Key_name='uniq_kami_app'");
mysql57Assert($res && $res->num_rows === 2, 'uniq_kami_app composite unique index missing');
$res->free();

mysql57Assert($mysqli->query("INSERT INTO fa_kami_app (kami_id,app_id) VALUES (1,12)") === true, 'first app mapping insert failed');
$duplicateAccepted = $mysqli->query("INSERT INTO fa_kami_app (kami_id,app_id) VALUES (1,12)");
mysql57Assert($duplicateAccepted === false && (int)$mysqli->errno === 1062, 'duplicate mapping must be rejected by unique index');

$mysqli->close();
fwrite(STDOUT, "OK phase16_mysql57_migration_test idempotent=passed legacy_default=passed unique_mapping=passed\n");
