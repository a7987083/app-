<?php

function p191MysqlFail($message)
{
    fwrite(STDERR, "FAIL phase19_1_client_sync_mysql57_test: {$message}\n");
    exit(1);
}

function p191MysqlAssert($condition, $message)
{
    if (!$condition) {
        p191MysqlFail($message);
    }
}

$host = getenv('ZONOE_MYSQL_HOST') ?: '127.0.0.1';
$port = (int)(getenv('ZONOE_MYSQL_PORT') ?: 3306);
$user = getenv('ZONOE_MYSQL_USER') ?: 'root';
$pass = getenv('ZONOE_MYSQL_PASSWORD') ?: 'root';
$db = getenv('ZONOE_MYSQL_DATABASE') ?: 'zonoe_test';

$mysqli = @new mysqli($host, $user, $pass, $db, $port);
if ($mysqli->connect_errno) {
    p191MysqlFail('connect failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8');

function p191MysqlExec(mysqli $db, $sql)
{
    if (!$db->multi_query($sql)) {
        p191MysqlFail('SQL failed: ' . $db->error);
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
        p191MysqlFail('SQL result failed: ' . $db->error);
    }
}

p191MysqlExec($mysqli, 'DROP TABLE IF EXISTS `fa_source_change`;');
$sql = file_get_contents(dirname(__DIR__) . '/release/sql/2026091712_source_sync_v3.sql');
p191MysqlAssert($sql !== false && trim($sql) !== '', 'Phase 18 V3 migration missing');
p191MysqlExec($mysqli, $sql);

p191MysqlAssert($mysqli->query("INSERT INTO fa_source_change (app_id,action,changed_at) VALUES (1,'add',100),(2,'add',101),(1,'update',102),(3,'add',103),(2,'delete',104)") === true, 'seed change log failed');
$res = $mysqli->query('SELECT MIN(revision) AS min_revision, MAX(revision) AS current_revision FROM fa_source_change');
p191MysqlAssert($res !== false, 'initial revision window query failed');
$row = $res->fetch_assoc();
$res->free();
p191MysqlAssert((int)$row['min_revision'] === 1 && (int)$row['current_revision'] === 5, 'initial revision window mismatch');
p191MysqlAssert(max(0, (int)$row['min_revision'] - 1) === 0, 'initial min_since must be zero');

// Simulate future retention cleanup. A client on revision 2 can still consume
// revision 3+, while revision 1 would have an unrecoverable history gap.
p191MysqlAssert($mysqli->query('DELETE FROM fa_source_change WHERE revision <= 2') === true, 'retention simulation failed');
$res = $mysqli->query('SELECT MIN(revision) AS min_revision, MAX(revision) AS current_revision FROM fa_source_change');
p191MysqlAssert($res !== false, 'retained revision window query failed');
$row = $res->fetch_assoc();
$res->free();
$minSince = max(0, (int)$row['min_revision'] - 1);
$current = (int)$row['current_revision'];
p191MysqlAssert($minSince === 2 && $current === 5, 'retained min_since/current_revision mismatch');
p191MysqlAssert(1 < $minSince, 'stale client must be forced to full sync');
p191MysqlAssert(2 >= $minSince && 2 <= $current, 'boundary client revision must remain delta-resumable');
p191MysqlAssert(6 > $current, 'future client revision must be rejected');

$res = $mysqli->query('SELECT revision FROM fa_source_change WHERE revision > 2 ORDER BY revision ASC');
p191MysqlAssert($res !== false && $res->num_rows === 3, 'delta after retention boundary incomplete');
$expected = 3;
while ($r = $res->fetch_assoc()) {
    p191MysqlAssert((int)$r['revision'] === $expected, 'delta ordering after retention cleanup invalid');
    $expected++;
}
$res->free();

$mysqli->close();
fwrite(STDOUT, "OK phase19_1_client_sync_mysql57_test min_since=passed history_gap=passed future_revision=passed delta_boundary=passed\n");
