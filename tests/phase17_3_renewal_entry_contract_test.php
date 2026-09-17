<?php

function p173Assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL phase17_3_renewal_entry_contract_test: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$required = [
    'application/common/library/SourceAppRecord.php',
    'application/common/library/AppStorePayload.php',
    'application/admin/view/category/add.html',
    'application/admin/view/category/edit.html',
    'application/admin/controller/Authorization.php',
    'application/admin/view/authorization/transfers.html',
    'application/admin/view/authorization/events.html',
    'public/assets/js/backend/authorization.js',
    'release/sql/2026091704_renewal_entry.sql',
];
foreach ($required as $path) {
    p173Assert(is_file($root . '/' . $path), 'missing ' . $path);
}

$record = file_get_contents($root . '/application/common/library/SourceAppRecord.php');
p173Assert(strpos($record, "'renewal_entry' => 'renewal_entry'") !== false, 'renewal semantic field missing');
p173Assert(strpos($record, '$includeRenewalEntry') !== false, 'renewal source field must be optional');
p173Assert(strpos($record, 'renewalEntryColumnAvailable') !== false, 'renewal schema detection missing');
p173Assert(strpos($record, "SHOW COLUMNS FROM `fa_category` LIKE 'renewal_entry'") !== false, 'renewal schema probe missing');

$payload = file_get_contents($root . '/application/common/library/AppStorePayload.php');
p173Assert(strpos($payload, "SourceAppRecord::value(\$row, 'renewal_entry', 0)") !== false, 'payload does not read renewal flag');
p173Assert(strpos($payload, "if (\$renewalEntry)") !== false, 'renewal branch missing');
p173Assert(strpos($payload, "\$lock = '1';") !== false, 'renewal entry must stay locked');
p173Assert(strpos($payload, "\$download = '';") !== false, 'renewal entry must suppress download URL');

$add = file_get_contents($root . '/application/admin/view/category/add.html');
$edit = file_get_contents($root . '/application/admin/view/category/edit.html');
p173Assert(strpos($add, 'row[renewal_entry]') !== false, 'add form renewal selector missing');
p173Assert(strpos($edit, 'row[renewal_entry]') !== false, 'edit form renewal selector missing');
p173Assert(strpos($add, '始终锁定') !== false, 'add form renewal behavior warning missing');

$controller = file_get_contents($root . '/application/admin/controller/Authorization.php');
p173Assert(strpos($controller, "DELETE FROM `fa_card_transfer_log`") !== false, 'transfer clear DELETE missing');
p173Assert(strpos($controller, "DELETE FROM `fa_authorization_event`") !== false, 'event clear DELETE missing');
p173Assert(substr_count($controller, "post('clear', 0)") >= 2, 'clear actions must require explicit POST flag');

$transfers = file_get_contents($root . '/application/admin/view/authorization/transfers.html');
$events = file_get_contents($root . '/application/admin/view/authorization/events.html');
$authorizationJs = file_get_contents($root . '/public/assets/js/backend/authorization.js');
p173Assert(strpos($transfers, '清空全部换绑记录') !== false, 'transfer destructive clear button missing');
p173Assert(strpos($events, '清空全部授权事件') !== false, 'event destructive clear button missing');
p173Assert(strpos($transfers, 'btn-clear-auth-log') !== false && strpos($events, 'btn-clear-auth-log') !== false, 'FastAdmin clear action hook missing');
p173Assert(strpos($transfers, 'data-url=') !== false && strpos($events, 'data-url=') !== false, 'AJAX clear URL metadata missing');
p173Assert(strpos($transfers, 'data-target=') !== false && strpos($events, 'data-target=') !== false, 'live-refresh target metadata missing');
p173Assert(strpos($transfers, 'method="post"') === false, 'transfer clear must not use legacy POST form');
p173Assert(strpos($events, 'method="post"') === false, 'event clear must not use legacy POST form');
p173Assert(strpos($transfers, 'confirm(') === false, 'transfer clear must not use browser confirm');
p173Assert(strpos($events, 'confirm(') === false, 'event clear must not use browser confirm');
p173Assert(strpos($authorizationJs, 'Layer.confirm') !== false, 'FastAdmin confirmation missing');
p173Assert(strpos($authorizationJs, 'Backend.api.ajax') !== false, 'FastAdmin AJAX clear missing');
p173Assert(strpos($authorizationJs, 'data: {clear: 1}') !== false, 'explicit AJAX clear flag missing');
p173Assert(strpos($authorizationJs, 'Controller.api.emptyRows') !== false, 'immediate row refresh missing');
p173Assert(strpos($authorizationJs, "Backend.api.closetabs('authorization/index')") !== false, 'authorization overview invalidation missing');

$sql = file_get_contents($root . '/release/sql/2026091704_renewal_entry.sql');
p173Assert(strpos($sql, "COLUMN_NAME = 'renewal_entry'") !== false, 'renewal migration guard missing');
p173Assert(strpos($sql, 'ALTER TABLE `fa_category` ADD COLUMN `renewal_entry`') !== false, 'renewal migration ALTER missing');
p173Assert(strpos($sql, "DEFAULT ''0''") !== false, 'renewal migration default missing');

echo "OK phase17_3_renewal_entry_contract_test renewal=passed schema_compat=passed cleanup=fastadmin_ajax migration=passed\n";
