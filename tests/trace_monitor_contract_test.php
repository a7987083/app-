<?php

function traceMonitorContractFail($message)
{
    fwrite(STDERR, "FAIL trace_monitor_contract_test: {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$app = file_get_contents($root . '/application/index/controller/App.php');
$monitorJs = file_get_contents($root . '/public/assets/js/backend/monitor.js');
$policy = file_get_contents($root . '/application/common/library/TraceMonitorPolicy.php');

foreach (array(
    'TraceMonitorPolicy::entries',
    'TraceMonitorPolicy::isSupportedUdid',
    "'openblack'",
    "'openblack2'",
) as $needle) {
    if (strpos($app . $policy, $needle) === false) {
        traceMonitorContractFail('missing trace monitor contract: ' . $needle);
    }
}

if (strpos($app, "base64_decode($traceValue)") !== false) {
    traceMonitorContractFail('App controller still duplicates trace payload decoding');
}
if (strpos($app, "strlen($udid)") !== false) {
    traceMonitorContractFail('App controller still duplicates legacy UDID length gate');
}

foreach (array('来源身份', '来源记录次数', '首次记录时间', '拉黑') as $label) {
    if (strpos($monitorJs, $label) === false) {
        traceMonitorContractFail('monitor UI missing clarified label: ' . $label);
    }
}

echo "OK trace_monitor_contract_test\n";
