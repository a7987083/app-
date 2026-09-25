<?php

$host = getenv('ZONOE_MYSQL_HOST') ?: '127.0.0.1';
$port = (int)(getenv('ZONOE_MYSQL_PORT') ?: 3306);
$user = getenv('ZONOE_MYSQL_USER') ?: 'root';
$pass = getenv('ZONOE_MYSQL_PASSWORD') ?: 'root';
$db = getenv('ZONOE_MYSQL_DATABASE') ?: 'zonoe_test';

$mysqli = @new mysqli($host, $user, $pass, $db, $port);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "FAIL oc_codegen_mysql57: connect " . $mysqli->connect_error . "\n");
    exit(1);
}
$mysqli->set_charset('utf8mb4');
$mysqli->query('DROP TABLE IF EXISTS fa_auth_rule');
$create = "CREATE TABLE fa_auth_rule ("
    . "id int unsigned NOT NULL AUTO_INCREMENT,"
    . "type varchar(10) NOT NULL DEFAULT '',"
    . "pid int unsigned NOT NULL DEFAULT 0,"
    . "name varchar(100) NOT NULL DEFAULT '',"
    . "title varchar(50) NOT NULL DEFAULT '',"
    . "icon varchar(50) NOT NULL DEFAULT '',"
    . "`condition` varchar(255) NOT NULL DEFAULT '',"
    . "remark varchar(255) NOT NULL DEFAULT '',"
    . "ismenu tinyint unsigned NOT NULL DEFAULT 0,"
    . "createtime int unsigned DEFAULT NULL,"
    . "updatetime int unsigned DEFAULT NULL,"
    . "weigh int NOT NULL DEFAULT 0,"
    . "status varchar(30) NOT NULL DEFAULT '',"
    . "PRIMARY KEY (id), UNIQUE KEY uniq_name (name)"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if (!$mysqli->query($create)) {
    fwrite(STDERR, "FAIL oc_codegen_mysql57: create table " . $mysqli->error . "\n");
    exit(1);
}

$sqlFile = dirname(__DIR__) . '/release/sql/2026092407_oc_codegen.sql';
$sql = @file_get_contents($sqlFile);
if ($sql === false) {
    fwrite(STDERR, "FAIL oc_codegen_mysql57: migration missing\n");
    exit(1);
}

function run_sql($mysqli, $sql)
{
    if (!$mysqli->multi_query($sql)) {
        return false;
    }
    do {
        if ($result = $mysqli->store_result()) {
            $result->free();
        }
        if (!$mysqli->more_results()) {
            break;
        }
    } while ($mysqli->next_result());
    return $mysqli->errno === 0;
}

if (!run_sql($mysqli, $sql) || !run_sql($mysqli, $sql)) {
    fwrite(STDERR, "FAIL oc_codegen_mysql57: migration execution " . $mysqli->error . "\n");
    exit(1);
}

$res = $mysqli->query("SELECT id FROM fa_auth_rule WHERE name='general/occodegen' LIMIT 1");
$parent = $res ? $res->fetch_assoc() : null;
if (!$parent) {
    fwrite(STDERR, "FAIL oc_codegen_mysql57: parent menu missing\n");
    exit(1);
}
$pid = (int)$parent['id'];
$res = $mysqli->query("SELECT COUNT(*) AS c FROM fa_auth_rule WHERE pid={$pid}");
$row = $res ? $res->fetch_assoc() : null;
if (!$row || (int)$row['c'] !== 5) {
    fwrite(STDERR, "FAIL oc_codegen_mysql57: expected 5 child permissions\n");
    exit(1);
}
$res = $mysqli->query("SELECT COUNT(*) AS c FROM fa_auth_rule WHERE name LIKE 'general/occodegen%'");
$row = $res ? $res->fetch_assoc() : null;
if (!$row || (int)$row['c'] !== 6) {
    fwrite(STDERR, "FAIL oc_codegen_mysql57: migration is not idempotent\n");
    exit(1);
}

fwrite(STDOUT, "OK oc_codegen_mysql57 rules=6 parent_id={$pid}\n");
