<?php

function p191Assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL phase19_1_client_sync_contract_test: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$route = file_get_contents($root . '/application/route.php');
$v3 = file_get_contents($root . '/application/index/controller/SourceV3.php');
$sync = file_get_contents($root . '/application/common/library/SourceSyncV3.php');
$changes = file_get_contents($root . '/application/common/library/SourceChangeLog.php');
$manifest = file_get_contents($root . '/release/online-update-files.txt');

p191Assert($route !== false && $v3 !== false && $sync !== false && $changes !== false && $manifest !== false, 'required Phase 19 files missing');
p191Assert(strpos($route, "Route::rule('appstore','index/App/list');") !== false, 'legacy /appstore route changed');
p191Assert(strpos($route, "Route::rule('appstore/v3/apps','index/SourceV3/apps');") !== false, 'V3 apps route missing');
p191Assert(strpos($route, "Route::rule('appstore/v3/delta','index/SourceV3/delta');") !== false, 'V3 delta route missing');

p191Assert(strpos($changes, 'public static function minDeltaSince()') !== false, 'delta retention floor missing');
p191Assert(strpos($changes, "'min_since' => self::minDeltaSince()") !== false, 'revision window minimum missing');
p191Assert(strpos($changes, "order('revision asc')") !== false, 'retained history floor must use earliest revision');

p191Assert(strpos($sync, '$snapshotRevision = null') !== false, 'full sync snapshot argument missing');
p191Assert(strpos($sync, "'snapshot_valid' => 0") !== false, 'snapshot invalid state missing');
p191Assert(strpos($sync, "'restart_required' => 1") !== false, 'snapshot restart signal missing');
p191Assert(strpos($sync, "'reset_required' => 1") !== false, 'delta reset signal missing');
p191Assert(strpos($sync, "'history_gap'") !== false, 'delta history-gap reason missing');
p191Assert(strpos($sync, "'future_revision'") !== false, 'future client revision recovery missing');
p191Assert(strpos($sync, 'SourceChangeLog::currentRevision()') !== false, 'snapshot must verify current revision');

p191Assert(strpos($v3, "\$_GET['snapshot_revision']") !== false, 'controller snapshot_revision input missing');
p191Assert(strpos($v3, "'snapshot_revision' => \$page['snapshot_revision']") !== false, 'snapshot revision output missing');
p191Assert(strpos($v3, "'reset_required' => \$delta['reset_required']") !== false, 'delta reset output missing');
p191Assert(strpos($v3, "'min_delta_since' => \$meta['min_delta_since']") !== false, 'meta delta window missing');
p191Assert(strpos($v3, "'supported' => 1") !== false, 'business/sync errors must remain V3-supported');

p191Assert(strpos($manifest, 'application/index/controller/SourceV3.php') !== false, 'SourceV3 missing from online update manifest');
p191Assert(strpos($manifest, 'application/common/library/SourceSyncV3.php') !== false, 'SourceSyncV3 missing from online update manifest');
p191Assert(strpos($manifest, 'application/common/library/SourceChangeLog.php') !== false, 'SourceChangeLog missing from online update manifest');

echo "OK phase19_1_client_sync_contract_test snapshot=passed delta-window=passed reset=passed online-update=passed legacy=preserved\n";
