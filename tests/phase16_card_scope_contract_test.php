<?php

function p16Assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL phase16_card_scope_contract_test: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$schema = file_get_contents($root . '/application/common/library/AuthorizationSchema.php');
$policy = file_get_contents($root . '/application/common/library/CardAccessPolicy.php');
$app = file_get_contents($root . '/application/index/controller/App.php');
$payload = file_get_contents($root . '/application/common/library/AppStorePayload.php');
$controller = file_get_contents($root . '/application/admin/controller/Kami.php');
$add = file_get_contents($root . '/application/admin/view/kami/add.html');
$edit = file_get_contents($root . '/application/admin/view/kami/edit.html');
$js = file_get_contents($root . '/public/assets/js/backend/kami.js');
$sql = file_get_contents($root . '/release/sql/2026091611_card_scope.sql');

p16Assert(strpos($schema, "LIKE 'card_scope'") !== false, 'runtime card_scope migration missing');
p16Assert(strpos($schema, 'fa_kami_app') !== false, 'runtime app mapping table missing');
p16Assert(strpos($policy, 'SCOPE_SOURCE = 1') !== false, 'whole-source scope missing');
p16Assert(strpos($policy, 'SCOPE_VERIFY = 2') !== false, 'verification scope missing');
p16Assert(strpos($policy, 'SCOPE_APPS = 3') !== false, 'per-App scope missing');
p16Assert(strpos($policy, 'Verification-only cards are intentionally ignored') !== false, 'verification-only source isolation not documented/enforced');

p16Assert(strpos($app, 'CardAccessPolicy::sourceAccess') !== false, 'source access resolver not wired');
p16Assert(strpos($app, 'CardAccessPolicy::hasSourceCard') !== false, 'verify-only guest isolation missing');
p16Assert(strpos($app, 'stackRowsForScope') !== false, 'scope-aware stacking missing');
p16Assert(strpos($app, '该指定App卡未配置授权App') !== false, 'empty App target guard missing');
p16Assert(strpos($payload, 'CardAccessPolicy::allowsApp') !== false, 'per-App URL gate missing');

p16Assert(strpos($controller, "'card_scope' => \$scope") !== false, 'new cards do not persist scope');
p16Assert(strpos($controller, 'replaceTargetApps') !== false, 'App mapping persistence missing');
p16Assert(strpos($controller, "where('bt2b', '1')") !== false, 'App chooser must be limited to locked Apps');
foreach (['全软件源授权', '指定App授权', '仅验证', 'row[app_ids][]'] as $needle) {
    p16Assert(strpos($add, $needle) !== false, 'add form missing ' . $needle);
    p16Assert(strpos($edit, $needle) !== false, 'edit form missing ' . $needle);
}
p16Assert(strpos($js, "field: 'card_scope'") !== false, 'card scope table column missing');
p16Assert(strpos($js, 'bindScopeControls') !== false, 'App target visibility control missing');

p16Assert(strpos($sql, 'information_schema.COLUMNS') !== false, 'online migration is not idempotent');
p16Assert(strpos($sql, 'CREATE TABLE IF NOT EXISTS `fa_kami_app`') !== false, 'online mapping-table migration missing');
p16Assert(strpos($sql, "DEFAULT ''1''") !== false, 'legacy cards must default to whole-source scope');

fwrite(STDOUT, "OK phase16_card_scope_contract_test\n");
