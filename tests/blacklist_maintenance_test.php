<?php

require_once dirname(__DIR__) . '/application/common/library/BlacklistPolicy.php';

use app\common\library\BlacklistPolicy;

function blackMaintenanceAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL blacklist_maintenance_test: {$message}\n");
        exit(1);
    }
}

$now = 2000;
blackMaintenanceAssert(!BlacklistPolicy::isExpired(array('endtime' => 0), $now), 'permanent is not expired');
blackMaintenanceAssert(!BlacklistPolicy::isExpired(array('endtime' => 2001), $now), 'future row is not expired');
blackMaintenanceAssert(BlacklistPolicy::isExpired(array('endtime' => 1999), $now), 'past row is expired');

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/application/admin/controller/Black.php');
$js = file_get_contents($root . '/public/assets/js/backend/black.js');

foreach (array(
    "->where('udid', \$udid)",
    'BlacklistPolicy::findActive($existingRows)',
    '该UDID已在有效黑名单中',
) as $needle) {
    if (strpos($controller, $needle) === false) {
        blackMaintenanceAssert(false, 'active duplicate prevention missing: ' . $needle);
    }
}

blackMaintenanceAssert(strpos($js, '已过期') !== false, 'expired history label missing');
blackMaintenanceAssert(strpos($js, 'Date.now() / 1000') !== false, 'expired history timestamp check missing');

echo "OK blacklist_maintenance_test\n";
