<?php

function p183Assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL phase18_3_v3_toggle_contract_test: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/application/index/controller/SourceV3.php');
$route = file_get_contents($root . '/application/route.php');
$migration = file_get_contents($root . '/release/sql/2026091714_source_v3_toggle.sql');

p183Assert($controller !== false, 'SourceV3 controller missing');
p183Assert($route !== false, 'route file missing');
p183Assert($migration !== false, 'V3 toggle migration missing');

// Existing installations without source_v3 must preserve 1712/1713 behavior.
p183Assert(strpos($controller, "!array_key_exists('source_v3', \$configValues)") !== false, 'missing config must default V3 to enabled');
p183Assert(strpos($controller, "(string)\$configValues['source_v3'] === '1'") !== false, 'source_v3 enabled value contract missing');

// Disabled V3 is an explicit capability failure, not a generic source failure.
p183Assert(strpos($controller, "'supported' => 0") !== false, 'disabled V3 must report supported=0');
p183Assert(strpos($controller, "'fallback' => 'appstore'") !== false, 'disabled V3 must advertise legacy fallback');
p183Assert(strpos($controller, 'V3软件源已关闭，请回退到appstore') !== false, 'disabled V3 message missing');

// Healthy V3 reports capability positively.
p183Assert(substr_count($controller, "'supported' => 1") >= 3, 'healthy V3 endpoints must report supported=1');

// Legacy route must remain unchanged and V3 stays additive.
p183Assert(strpos($route, "Route::rule('appstore','index/App/list');") !== false, 'legacy /appstore route changed or missing');
p183Assert(strpos($route, "Route::rule('appstore/v3/meta','index/SourceV3/meta');") !== false, 'V3 meta route missing');
p183Assert(strpos($route, "Route::rule('appstore/v3/apps','index/SourceV3/apps');") !== false, 'V3 apps route missing');
p183Assert(strpos($route, "Route::rule('appstore/v3/delta','index/SourceV3/delta');") !== false, 'V3 delta route missing');

// Config is a single switch and defaults on for upgrade compatibility.
p183Assert(strpos($migration, "'source_v3','basic','V3软件源'") !== false, 'source_v3 config row missing');
p183Assert(strpos($migration, "'switch','1'") !== false, 'source_v3 must default enabled');
p183Assert(strpos($migration, "WHEN `value` IN ('0','1')") !== false, 'source_v3 migration must preserve valid values');

echo "OK phase18_3_v3_toggle_contract_test toggle=passed fallback=appstore legacy_route=passed default_on=passed\n";
