<?php

function p181MysqlFail($message)
{
    fwrite(STDERR, "FAIL phase18_1_source_v3_mysql57_test: {$message}\n");
    exit(1);
}

function p181MysqlAssert($condition, $message)
{
    if (!$condition) {
        p181MysqlFail($message);
    }
}

$host = getenv('ZONOE_MYSQL_HOST') ?: '127.0.0.1';
$port = (int)(getenv('ZONOE_MYSQL_PORT') ?: 3306);
$user = getenv('ZONOE_MYSQL_USER') ?: 'root';
$pass = getenv('ZONOE_MYSQL_PASSWORD') ?: 'root';
$db = getenv('ZONOE_MYSQL_DATABASE') ?: 'zonoe_test';

$mysqli = @new mysqli($host, $user, $pass, $db, $port);
if ($mysqli->connect_errno) {
    p181MysqlFail('connect failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8');

function p181MysqlExec(mysqli $db, $sql)
{
    if (!$db->multi_query($sql)) {
        p181MysqlFail('SQL failed: ' . $db->error);
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
        p181MysqlFail('SQL result failed: ' . $db->error);
    }
}

p181MysqlExec($mysqli, 'DROP TABLE IF EXISTS `fa_source_change`;');
$sqlFile = dirname(__DIR__) . '/release/sql/2026091712_source_sync_v3.sql';
$sql = file_get_contents($sqlFile);
p181MysqlAssert($sql !== false && trim($sql) !== '', 'migration SQL missing');

// The online updater can replay SQL after a partial update, so CREATE must be idempotent.
p181MysqlExec($mysqli, $sql);
p181MysqlExec($mysqli, $sql);

$res = $mysqli->query("SELECT COLUMN_NAME,COLUMN_KEY,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_source_change' ORDER BY ORDINAL_POSITION");
p181MysqlAssert($res !== false && $res->num_rows === 4, 'fa_source_change schema mismatch');
$columns = [];
while ($row = $res->fetch_assoc()) {
    $columns[$row['COLUMN_NAME']] = $row;
}
$res->free();
p181MysqlAssert(isset($columns['revision']) && $columns['revision']['COLUMN_KEY'] === 'PRI', 'revision primary key missing');
p181MysqlAssert(strpos(strtolower($columns['revision']['EXTRA']), 'auto_increment') !== false, 'revision must auto increment');
p181MysqlAssert(isset($columns['app_id']) && isset($columns['action']) && isset($columns['changed_at']), 'change log columns incomplete');

p181MysqlAssert($mysqli->query("INSERT INTO fa_source_change (app_id,action,changed_at) VALUES (10,'add',100),(10,'update',101),(11,'delete',102)") === true, 'change insert failed');
$res = $mysqli->query('SELECT revision,app_id,action FROM fa_source_change ORDER BY revision ASC');
p181MysqlAssert($res !== false && $res->num_rows === 3, 'change rows missing');
$expected = 1;
while ($row = $res->fetch_assoc()) {
    p181MysqlAssert((int)$row['revision'] === $expected, 'revision sequence is not monotonic');
    $expected++;
}
$res->free();

$res = $mysqli->query('SELECT revision,app_id FROM fa_source_change WHERE revision > 1 ORDER BY revision ASC LIMIT 2');
p181MysqlAssert($res !== false && $res->num_rows === 2, 'delta cursor query failed');
$first = $res->fetch_assoc();
$second = $res->fetch_assoc();
p181MysqlAssert((int)$first['revision'] === 2 && (int)$second['revision'] === 3, 'delta cursor ordering invalid');
$res->free();

$mysqli->close();
fwrite(STDOUT, "OK phase18_1_source_v3_mysql57_test schema=passed idempotent=passed revision=passed delta_cursor=passed\n");
