<?php
function p11Assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL phase11_contract_test: {$message}\n");
        exit(1);
    }
}
$root = dirname(__DIR__);
$required = [
    'application/common/library/AuthorizationSchema.php',
    'application/common/library/AuthorizationPolicy.php',
    'application/common/library/AuthorizationEventLog.php',
    'application/common/library/AuthorizationLicense.php',
    'application/admin/controller/Authorization.php',
    'application/admin/view/authorization/index.html',
    'application/admin/view/authorization/transfers.html',
    'application/admin/view/authorization/events.html',
    'application/admin/view/authorization/diagnostic.html',
    'application/index/view/index/license.html',
    'tools/phase11_upgrade.sql',
];
foreach ($required as $path) {
    p11Assert(is_file($root . '/' . $path), 'missing ' . $path);
}
$route = file_get_contents($root . '/application/route.php');
p11Assert(strpos($route, "Route::rule('unbind/query','index/Index/unbindQuery');") !== false, 'unbind query route missing');
p11Assert(strpos($route, "Route::rule('license','index/Index/license');") !== false, 'license route missing');
$kamiJs = file_get_contents($root . '/public/assets/js/backend/kami.js');
p11Assert(strpos($kamiJs, "field: 'transfer_count'") !== false, 'card list transfer count missing');
$unbind = file_get_contents($root . '/application/index/view/index/unbind.html');
foreach (['query-udid', 'query-btn', '查询剩余次数', '/unbind/query'] as $needle) {
    p11Assert(strpos($unbind, $needle) !== false, 'unbind query UI missing ' . $needle);
}
$schema = file_get_contents($root . '/application/common/library/AuthorizationSchema.php');
foreach (['fa_card_transfer_log', 'fa_authorization_event', 'transfer_count', 'unbind_max_count', 'authorization/diagnostic'] as $needle) {
    p11Assert(strpos($schema, $needle) !== false, 'schema contract missing ' . $needle);
}
$upgrade = file_get_contents($root . '/tools/phase11_upgrade.sql');
foreach (['transfer_count', 'fa_card_transfer_log', 'fa_authorization_event', 'unbind_max_count'] as $needle) {
    p11Assert(strpos($upgrade, $needle) !== false, 'phase11 upgrade missing ' . $needle);
}
$app = file_get_contents($root . '/application/index/controller/App.php');
p11Assert(strpos($app, "'transfer_count' => \$transferCount") !== false, 'stacked cards do not inherit transfer count');
p11Assert(strpos($app, "AuthorizationEventLog::record(\$wasStacked ? 'stack' : 'activate'") !== false, 'activation event logging missing');

echo "OK phase11_contract_test\n";
