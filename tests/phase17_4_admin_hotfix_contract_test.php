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
$authorizationJs = file_get_contents($root . '/public/assets/js/backend/authorization.js');
$manifest = file_get_contents($root . '/release/online-update-files.txt');

foreach ([$category, $edit, $add, $authorization, $transfers, $events, $authorizationJs, $manifest] as $source) {
    phase174Assert($source !== false, 'required source file missing');
}

phase174Assert(strpos($category, 'ensureRenewalEntryColumn') !== false, 'category editor must self-heal renewal schema');
phase174Assert(strpos($category, "SHOW COLUMNS FROM `fa_category` LIKE 'renewal_entry'") !== false, 'renewal schema probe missing');
phase174Assert(strpos($category, "unset(\$params['renewal_entry'])") !== false, 'write guard missing when renewal schema unavailable');
phase174Assert(strpos($edit, '$renewalEntryAvailable') !== false, 'edit view schema guard missing');
phase174Assert(strpos($add, '$renewalEntryAvailable') !== false, 'add view schema guard missing');

phase174Assert(strpos($authorization, "DELETE FROM `fa_card_transfer_log`") !== false, 'transfer DELETE missing');
phase174Assert(strpos($authorization, "DELETE FROM `fa_authorization_event`") !== false, 'authorization event DELETE missing');
phase174Assert(strpos($authorization, "\$this->success(\n                    '换绑记录已清空") !== false, 'transfer clear must use backend success response');
phase174Assert(strpos($authorization, "\$this->success(\n                    '授权事件已清空") !== false, 'event clear must use backend success response');
phase174Assert(strpos($authorization, "['deleted' => (int)\$deleted, 'resource' => 'transfers']") !== false, 'transfer AJAX result payload missing');
phase174Assert(strpos($authorization, "['deleted' => (int)\$deleted, 'resource' => 'events']") !== false, 'event AJAX result payload missing');
phase174Assert(strpos($authorization, "\$target = url('authorization/index'") === false, 'legacy one-second redirect target must be removed');

phase174Assert(strpos($transfers, 'method="post"') === false, 'transfer clear must not use native POST form');
phase174Assert(strpos($events, 'method="post"') === false, 'event clear must not use native POST form');
phase174Assert(strpos($transfers, 'onsubmit="return confirm') === false, 'transfer clear must not use browser confirm');
phase174Assert(strpos($events, 'onsubmit="return confirm') === false, 'event clear must not use browser confirm');
phase174Assert(strpos($transfers, 'btn-clear-auth-log') !== false, 'transfer FastAdmin clear action missing');
phase174Assert(strpos($events, 'btn-clear-auth-log') !== false, 'event FastAdmin clear action missing');
phase174Assert(strpos($transfers, 'id="transfer-log-body"') !== false, 'transfer live tbody target missing');
phase174Assert(strpos($events, 'id="event-log-body"') !== false, 'event live tbody target missing');

phase174Assert(strpos($authorizationJs, 'Backend.api.ajax') !== false, 'authorization actions must use FastAdmin AJAX');
phase174Assert(strpos($authorizationJs, 'Layer.confirm') !== false, 'authorization actions must use FastAdmin Layer confirmation');
phase174Assert(strpos($authorizationJs, "Backend.api.closetabs('authorization/index')") !== false, 'overview stale tab invalidation missing');
phase174Assert(strpos($authorizationJs, 'Controller.api.emptyRows(button)') !== false, 'current log page must update immediately');

phase174Assert(strpos($manifest, 'application/admin/controller/Category.php') !== false, 'online update manifest must include Category controller hotfix');
phase174Assert(strpos($manifest, 'public/assets/js/backend/authorization.js') !== false, 'online update manifest must include authorization FastAdmin controller');

fwrite(STDOUT, "OK phase17_4_admin_hotfix_contract_test editor=passed fastadmin_ajax=passed live_refresh=passed manifest=passed\n");
