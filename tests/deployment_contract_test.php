<?php

function deployFail($message)
{
    fwrite(STDERR, "FAIL deployment_contract_test: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$autoFile = $root . '/auto_install.json';
$dbFile = $root . '/application/database.php';
$nginxRewriteFile = $root . '/nginx.rewrite';
$phase11Upgrade = $root . '/tools/phase11_upgrade.sql';

if (!is_file($autoFile)) {
    deployFail('missing auto_install.json');
}
if (!is_file($dbFile)) {
    deployFail('missing application/database.php');
}
if (!is_file($nginxRewriteFile)) {
    deployFail('missing nginx.rewrite');
}
if (!is_file($phase11Upgrade)) {
    deployFail('missing tools/phase11_upgrade.sql');
}

$auto = json_decode(file_get_contents($autoFile), true);
if (!is_array($auto)) {
    deployFail('auto_install.json is invalid JSON');
}

if (!isset($auto['db_config']) || $auto['db_config'] !== 'application/database.php') {
    deployFail('db_config must point to application/database.php');
}
if (!isset($auto['run_path']) || $auto['run_path'] !== '/public') {
    deployFail('run_path must be /public');
}
if (!isset($auto['admin_username']) || $auto['admin_username'] !== 'admin') {
    deployFail('admin_username must remain admin');
}
if (!isset($auto['admin_password']) || $auto['admin_password'] !== '123456') {
    deployFail('admin_password metadata must remain 123456');
}

$db = file_get_contents($dbFile);
foreach (array('BT_DB_NAME', 'BT_DB_USERNAME', 'BT_DB_PASSWORD') as $placeholder) {
    if (strpos($db, $placeholder) === false) {
        deployFail('missing BaoTa placeholder ' . $placeholder);
    }
}

$forbidden = array(
    "'database.database', 'user'",
    "'database.username', 'dbname'",
    "'database.password', 'pwd'",
);
foreach ($forbidden as $needle) {
    if (strpos($db, $needle) !== false) {
        deployFail('legacy database placeholder leaked into release config: ' . $needle);
    }
}

$rewrite = preg_replace('/\s+/', ' ', trim(file_get_contents($nginxRewriteFile)));
if (strpos($rewrite, 'location / {') === false) {
    deployFail('nginx.rewrite missing location / block');
}
if (strpos($rewrite, 'if (!-e $request_filename)') === false) {
    deployFail('nginx.rewrite missing file-existence guard');
}
if (strpos($rewrite, 'rewrite ^(.*)$ /index.php?s=$1 last; break;') === false) {
    deployFail('nginx.rewrite missing ThinkPHP rewrite target');
}

$upgrade = file_get_contents($phase11Upgrade);
foreach (array('transfer_count', 'fa_card_transfer_log', 'fa_authorization_event', 'unbind_max_count') as $needle) {
    if (strpos($upgrade, $needle) === false) {
        deployFail('phase11 upgrade missing ' . $needle);
    }
}

echo "OK deployment_contract_test\n";
