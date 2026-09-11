<?php
require_once dirname(__DIR__) . '/application/common/library/CardDeviceTransfer.php';

use app\common\library\CardDeviceTransfer;

function transferAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL card_device_transfer_contract_test: {$message}\n");
        exit(1);
    }
}

transferAssert(CardDeviceTransfer::isSupportedUdid('00008110-001229DE2E82802E'), '25-char UDID accepted');
transferAssert(CardDeviceTransfer::isSupportedUdid(str_repeat('A', 40)), '40-char UDID accepted');
transferAssert(!CardDeviceTransfer::isSupportedUdid('short'), 'invalid UDID rejected');

$root = dirname(__DIR__);
$transfer = file_get_contents($root . '/application/common/library/CardDeviceTransfer.php');
$index = file_get_contents($root . '/application/index/controller/Index.php');
$route = file_get_contents($root . '/application/route.php');
$view = file_get_contents($root . '/application/index/view/index/unbind.html');
foreach (["where('kami', \$code)", "where('udid', \$oldUdid)", "where('endtime', '>', \$now)", "'udid' => \$newUdid", "'transfer_count' => \$newCount", 'BlacklistPolicy::findActive', 'AuthorizationPolicy::maxTransfers', 'successCountForUdid', 'ipAttempts'] as $needle) {
    transferAssert(strpos($transfer, $needle) !== false, 'transfer contract missing: ' . $needle);
}
transferAssert(strpos($index, 'CardDeviceTransfer::transfer') !== false, 'Index unbind action missing transfer service');
transferAssert(strpos($index, 'CardDeviceTransfer::queryStatus') !== false, 'Index unbind query missing');
transferAssert(strpos($route, "Route::rule('unbind','index/Index/unbind');") !== false, 'unbind route missing');
transferAssert(strpos($route, "Route::rule('unbind/query','index/Index/unbindQuery');") !== false, 'unbind query route missing');
foreach (['name="code"', 'name="old_udid"', 'name="new_udid"', 'query-udid', 'query-btn'] as $needle) {
    transferAssert(strpos($view, $needle) !== false, 'unbind form missing: ' . $needle);
}

echo "OK card_device_transfer_contract_test\n";
