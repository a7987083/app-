<?php

function p175Assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL phase17_5_mapping_cleanup_contract_test: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$kamiModel = file_get_contents($root . '/application/admin/model/Kami.php');
$categoryModel = file_get_contents($root . '/application/common/model/Category.php');
$authorization = file_get_contents($root . '/application/admin/controller/Authorization.php');
$manifest = file_get_contents($root . '/release/online-update-files.txt');

p175Assert($kamiModel !== false, 'Kami model missing');
p175Assert($categoryModel !== false, 'Category model missing');
p175Assert($authorization !== false, 'Authorization controller missing');
p175Assert($manifest !== false, 'online update manifest missing');

p175Assert(strpos($kamiModel, 'self::beforeDelete(function ($row)') !== false, 'card beforeDelete cleanup missing');
p175Assert(strpos($kamiModel, "Db::table('fa_kami_app')->where('kami_id', $id)->delete();") !== false, 'card mapping cleanup missing');
p175Assert(strpos($categoryModel, 'self::beforeDelete(function ($row)') !== false, 'app beforeDelete cleanup missing');
p175Assert(strpos($categoryModel, "Db::table('fa_kami_app')->where('app_id', $id)->delete();") !== false, 'app mapping cleanup missing');

p175Assert(strpos($authorization, "header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0')") !== false, 'dashboard no-store header missing');
p175Assert(strpos($authorization, "url('authorization/index', ['_refresh' => time()]) . '#transfer-preview'") !== false, 'transfer clear dashboard redirect missing');
p175Assert(strpos($authorization, "url('authorization/index', ['_refresh' => time()]) . '#event-preview'") !== false, 'event clear dashboard redirect missing');
p175Assert(strpos($authorization, "DELETE FROM `fa_card_transfer_log`") !== false, 'transfer DELETE missing');
p175Assert(strpos($authorization, "DELETE FROM `fa_authorization_event`") !== false, 'event DELETE missing');

p175Assert(strpos($manifest, 'application/admin/model/Kami.php') !== false, 'Kami model missing from online update manifest');
p175Assert(strpos($manifest, 'application/common/model/Category.php') !== false, 'Category model missing from online update manifest');

echo "OK phase17_5_mapping_cleanup_contract_test mapping=passed dashboard_refresh=passed manifest=passed\n";
