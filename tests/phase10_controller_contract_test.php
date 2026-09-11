<?php

function phase10Assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL phase10_controller_contract_test: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$app = file_get_contents($root . '/application/index/controller/App.php');
$payload = file_get_contents($root . '/application/common/library/AppStorePayload.php');

foreach (array(
    'SourceHttpClient::postForm',
    'SourceResponse::encryptedBody',
    'SourceResponse::plainBody',
    'SourceAppRecord::publicSourceColumns',
    'CardEntitlementPolicy::activeEndTime',
    'CardEntitlementPolicy::activationState',
    '->lock(true)',
) as $needle) {
    phase10Assert(strpos($app, $needle) !== false, 'App controller missing: ' . $needle);
}
foreach (array('public function curl(', 'protected function codeTimes(') as $legacy) {
    phase10Assert(strpos($app, $legacy) === false, 'legacy controller helper remains: ' . $legacy);
}
foreach (array("\$row['bt1a']", "\$row['bt1b']", "\$row['bt2a']", "\$row['bt2b']") as $legacy) {
    phase10Assert(strpos($payload, $legacy) === false, 'AppStorePayload still uses raw legacy field: ' . $legacy);
}
phase10Assert(strpos($payload, 'SourceAppRecord::value') !== false, 'semantic source-app layer not used');

echo "OK phase10_controller_contract_test\n";
