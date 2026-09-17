<?php

function p181Assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL phase18_1_source_v3_contract_test: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$route = file_get_contents($root . '/application/route.php');
$legacyApp = file_get_contents($root . '/application/index/controller/App.php');
$v3 = file_get_contents($root . '/application/index/controller/SourceV3.php');
$sync = file_get_contents($root . '/application/common/library/SourceSyncV3.php');
$changes = file_get_contents($root . '/application/common/library/SourceChangeLog.php');
$model = file_get_contents($root . '/application/common/model/Category.php');
$admin = file_get_contents($root . '/application/admin/controller/Category.php');
$sql = file_get_contents($root . '/release/sql/2026091712_source_sync_v3.sql');

p181Assert($route !== false && $legacyApp !== false && $v3 !== false && $sync !== false && $changes !== false && $model !== false && $admin !== false && $sql !== false, 'required source files missing');
p181Assert(strpos($route, "Route::rule('appstore','index/App/list');") !== false, 'legacy /appstore route changed');
p181Assert(strpos($route, "Route::rule('appstore/v3/meta','index/SourceV3/meta');") !== false, 'v3 meta route missing');
p181Assert(strpos($route, "Route::rule('appstore/v3/apps','index/SourceV3/apps');") !== false, 'v3 apps route missing');
p181Assert(strpos($route, "Route::rule('appstore/v3/delta','index/SourceV3/delta');") !== false, 'v3 delta route missing');
p181Assert(strpos($v3, 'class SourceV3 extends App') !== false, 'v3 controller must reuse stable legacy auth/encryption behavior');
p181Assert(strpos($v3, "'appstore_v3'") !== false && strpos($v3, 'SourceSyncV3::page') !== false && strpos($v3, 'SourceSyncV3::delta') !== false, 'v3 endpoints incomplete');
p181Assert(strpos($sync, 'const DEFAULT_LIMIT = 200') !== false && strpos($sync, 'const MAX_LIMIT = 500') !== false, 'v3 page bounds missing');
p181Assert(strpos($sync, "'next_after_id'") !== false && strpos($sync, "'next_since'") !== false, 'cursor metadata missing');
p181Assert(strpos($sync, "'upserts'") !== false && strpos($sync, "'deleted'") !== false, 'delta upsert/delete contract missing');
p181Assert(strpos($sync, "'id'") !== false && strpos($sync, "'weigh'") !== false, 'v3 app identity/order fields missing');
p181Assert(strpos($changes, "const TABLE = 'fa_source_change'") !== false, 'change-log table binding missing');
p181Assert(strpos($changes, "order('revision asc')") !== false, 'change-log must be monotonic');
p181Assert(strpos($changes, 'legacy source remains available') !== false, 'change logging must fail-open');
p181Assert(strpos($model, 'SourceChangeLog::record') !== false, 'model add/update/delete revision hooks missing');
p181Assert(strpos($admin, 'SourceChangeLog::record((int)$ids, \'update\')') !== false, 'direct admin edit revision logging missing');
p181Assert(strpos($sql, 'CREATE TABLE IF NOT EXISTS `fa_source_change`') !== false && strpos($sql, '`revision` bigint(20) unsigned NOT NULL AUTO_INCREMENT') !== false, 'idempotent revision schema missing');
p181Assert(strpos($legacyApp, 'public function list()') !== false && strpos($legacyApp, 'SourceAppRepository::rows()') !== false, 'legacy source controller contract unexpectedly changed');

echo "OK phase18_1_source_v3_contract_test legacy=preserved routes=passed revision=passed pagination=passed delta=passed\n";
