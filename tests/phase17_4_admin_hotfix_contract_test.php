<?php

function phase174Fail($message)
{
    fwrite(STDERR, "FAIL phase17_4_admin_hotfix_contract_test: {$message}\n");
    exit(1);
}

function phase174Assert($condition, $message)
{
    if (!$condition) {
        phase174Fail($message);
    }
}

$root = dirname(__DIR__);
$category = file_get_contents($root . '/application/admin/controller/Category.php');
$edit = file_get_contents($root . '/application/admin/view/category/edit.html');
$add = file_get_contents($root . '/application/admin/view/category/add.html');
$authorization = file_get_contents($root . '/application/admin/controller/Authorization.php');
$transfers = file_get_contents($root . '/application/admin/view/authorization/transfers.html');
$events = file_get_contents($root . '/application/admin/view/authorization/events.html');
$manifest = file_get_contents($root . '/release/online-update-files.txt');

foreach ([$category, $edit, $add, $authorization, $transfers, $events, $manifest] as $source) {
    phase174Assert($source !== false, 'required source file missing');
}

phase174Assert(strpos($category, 'ensureRenewalEntryColumn') !== false, 'category editor must self-heal renewal schema');
phase174Assert(strpos($category, "SHOW COLUMNS FROM `fa_category` LIKE 'renewal_entry'") !== false, 'renewal schema probe missing');
phase174Assert(strpos($category, "unset(\$params['renewal_entry'])") !== false, 'write guard missing when renewal schema unavailable');
phase174Assert(strpos($edit, '$renewalEntryAvailable') !== false, 'edit view schema guard missing');
phase174Assert(strpos($add, '$renewalEntryAvailable') !== false, 'add view schema guard missing');

phase174Assert(strpos($authorization, "DELETE FROM `fa_card_transfer_log`") !== false, 'transfer DELETE missing');
phase174Assert(strpos($authorization, "DELETE FROM `fa_authorization_event`") !== false, 'authorization event DELETE missing');
phase174Assert(strpos($authorization, "\$this->success('换绑记录已清空") !== false, 'transfer clear must use native backend success response');
phase174Assert(strpos($authorization, "\$this->success('授权事件已清空") !== false, 'event clear must use native backend success response');

phase174Assert(strpos($transfers, 'method="post"') !== false && strpos($transfers, 'name="clear" value="1"') !== false, 'transfer clear native POST form missing');
phase174Assert(strpos($events, 'method="post"') !== false && strpos($events, 'name="clear" value="1"') !== false, 'event clear native POST form missing');
phase174Assert(strpos($transfers, '$.ajax') === false, 'transfer clear must not depend on inline jQuery ajax');
phase174Assert(strpos($events, '$.ajax') === false, 'event clear must not depend on inline jQuery ajax');

phase174Assert(strpos($manifest, 'application/admin/controller/Category.php') !== false, 'online update manifest must include Category controller hotfix');

fwrite(STDOUT, "OK phase17_4_admin_hotfix_contract_test editor=passed clear_forms=passed manifest=passed\n");
