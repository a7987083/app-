<?php

function p194xMysqlFail($message)
{
    fwrite(STDERR, "FAIL phase19_4_x_mysql57_test: {$message}\n");
    exit(1);
}

function p194xMysqlAssert($condition, $message)
{
    if (!$condition) {
        p194xMysqlFail($message);
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
    p194xMysqlFail('connect failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8');

foreach (['fa_api_endpoint', 'fa_config'] as $table) {
    p194xMysqlAssert($mysqli->query("DROP TABLE IF EXISTS `{$table}`") === true, 'drop failed: ' . $table);
}

p194xMysqlAssert($mysqli->query("CREATE TABLE `fa_api_endpoint` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `endpoint_key` varchar(64) NOT NULL DEFAULT '',
    `name` varchar(100) NOT NULL DEFAULT '',
    `path` varchar(190) NOT NULL DEFAULT '',
    `method` varchar(32) NOT NULL DEFAULT '',
    `source` varchar(32) NOT NULL DEFAULT '',
    `auth` varchar(100) NOT NULL DEFAULT '',
    `handler_key` varchar(64) NOT NULL DEFAULT '',
    `enabled` tinyint unsigned NOT NULL DEFAULT 1,
    `description` varchar(255) NOT NULL DEFAULT '',
    `createtime` int unsigned NOT NULL DEFAULT 0,
    `updatetime` int unsigned NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_endpoint_key` (`endpoint_key`),
    UNIQUE KEY `uniq_path` (`path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8") === true, 'fa_api_endpoint create failed');

p194xMysqlAssert($mysqli->query("CREATE TABLE `fa_config` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(64) NOT NULL DEFAULT '',
    `value` text,
    PRIMARY KEY (`id`),
    KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8") === true, 'fa_config create failed');

p194xMysqlAssert($mysqli->query("INSERT INTO `fa_api_endpoint`
(`endpoint_key`,`name`,`path`,`method`,`source`,`auth`,`handler_key`,`enabled`,`description`,`createtime`,`updatetime`)
VALUES ('license','旧授权查询','/license','GET','system','UDID','license',0,'旧说明',1,1)") === true, 'seed license endpoint failed');

$message = '[授权摘要]|[到期时间]|[源名称]|[指定APP数量]|[全源到期时间]|[全源剩余时间]|[部分到期时间]|[部分剩余时间]|[验证到期时间]|[验证剩余时间]|[服务器时间]|[剩余时间]';
$stmt = $mysqli->prepare("INSERT INTO `fa_config` (`name`,`value`) VALUES ('message', ?)");
p194xMysqlAssert($stmt !== false, 'prepare config insert failed');
$stmt->bind_param('s', $message);
p194xMysqlAssert($stmt->execute() === true, 'seed config failed');
$stmt->close();

$sql = file_get_contents(dirname(__DIR__) . '/release/sql/2026091808_phase19_4_x_closeout.sql');
p194xMysqlAssert($sql !== false && trim($sql) !== '', '2026091808 migration missing');
$statements = \app\common\library\update\UpdateSqlRunner::splitStatements($sql);
p194xMysqlAssert(count($statements) >= 10, 'migration splitter returned too few statements');

for ($passNo = 1; $passNo <= 2; $passNo++) {
    foreach ($statements as $statement) {
        $result = $mysqli->query($statement);
        if ($result === false) {
            p194xMysqlFail('statement failed on pass ' . $passNo . ': ' . $mysqli->error . ' SQL=' . $statement);
        }
        if ($result instanceof mysqli_result) {
            $result->free();
        }
    }
}

$res = $mysqli->query("SELECT `name`,`path`,`method`,`source`,`auth`,`handler_key`,`enabled`,`description` FROM `fa_api_endpoint` WHERE `endpoint_key`='license' LIMIT 1");
p194xMysqlAssert($res && $res->num_rows === 1, 'license endpoint missing');
$row = $res->fetch_assoc();
$res->free();
p194xMysqlAssert($row['name'] === '授权查询', 'license name not synchronized');
p194xMysqlAssert($row['path'] === '/authorization', 'license path not migrated');
p194xMysqlAssert($row['method'] === 'GET,POST', 'license method not migrated');
p194xMysqlAssert($row['source'] === 'system', 'license source not normalized');
p194xMysqlAssert($row['auth'] === '卡密+UDID', 'license auth not migrated');
p194xMysqlAssert($row['handler_key'] === 'license', 'license handler not migrated');
p194xMysqlAssert((int)$row['enabled'] === 0, 'license enabled state must be preserved');
p194xMysqlAssert(strpos($row['description'], '/authorization') !== false, 'license description missing safe route');

$res = $mysqli->query("SELECT `value` FROM `fa_config` WHERE `name`='message' LIMIT 1");
p194xMysqlAssert($res && $res->num_rows === 1, 'message config missing');
$row = $res->fetch_assoc();
$res->free();
$value = (string)$row['value'];

p194xMysqlAssert(strpos($value, '[服务器运行时间]') !== false, 'server runtime token not migrated');
p194xMysqlAssert(strpos($value, '[服务器时间]') === false, 'old server time token still present');
p194xMysqlAssert(strpos($value, '[剩余时间]') !== false, 'remaining time token should be preserved');

foreach ([
    '[授权摘要]', '[到期时间]', '[源名称]', '[指定APP数量]',
    '[全源到期时间]', '[全源剩余时间]', '[部分到期时间]', '[部分剩余时间]',
    '[验证到期时间]', '[验证剩余时间]'
] as $retired) {
    p194xMysqlAssert(strpos($value, $retired) === false, 'retired token still present: ' . $retired);
}

$mysqli->close();
fwrite(STDOUT, "OK phase19_4_x_mysql57_test idempotent=passed api_sync=passed announcement_cleanup=passed runtime_token=passed\n");
