<?php

require_once dirname(__DIR__) . '/application/common/library/TraceMonitorPolicy.php';

use app\common\library\TraceMonitorPolicy;

function traceMonitorAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL trace_monitor_policy_test: {$message}\n");
        exit(1);
    }
}

$udid25 = '00008120-001A55A93AF0201E';
$udid40 = '0123456789abcdef0123456789abcdef01234567';

traceMonitorAssert(strlen($udid25) === 25, 'fixture udid25 must be 25 chars');
traceMonitorAssert(strlen($udid40) === 40, 'fixture udid40 must be 40 chars');
traceMonitorAssert(TraceMonitorPolicy::isSupportedUdid($udid25), '25-char UDID must be accepted');
traceMonitorAssert(TraceMonitorPolicy::isSupportedUdid($udid40), '40-char UDID must be accepted');
traceMonitorAssert(!TraceMonitorPolicy::isSupportedUdid(substr($udid25, 0, 24)), '24-char value must be rejected');
traceMonitorAssert(!TraceMonitorPolicy::isSupportedUdid($udid40 . 'x'), '41-char value must be rejected');
traceMonitorAssert(!TraceMonitorPolicy::isSupportedUdid(''), 'empty value must be rejected');
traceMonitorAssert(!TraceMonitorPolicy::isSupportedUdid(null), 'null value must be rejected');

$trace = base64_encode($udid25 . '|' . $udid40);
$entries = TraceMonitorPolicy::entries($trace);
traceMonitorAssert(count($entries) === 2, 'trace must decode into two historical positions');
traceMonitorAssert($entries[0]['udid'] === $udid25, 'first position must preserve adder UDID');
traceMonitorAssert($entries[0]['identity'] === '添加者', 'first position identity must remain 添加者');
traceMonitorAssert($entries[0]['config'] === 'openblack', 'first position switch must remain openblack');
traceMonitorAssert($entries[1]['udid'] === $udid40, 'second position must preserve cracker UDID');
traceMonitorAssert($entries[1]['identity'] === '破解者', 'second position identity must remain 破解者');
traceMonitorAssert($entries[1]['config'] === 'openblack2', 'second position switch must remain openblack2');

$single = TraceMonitorPolicy::entries(base64_encode($udid25));
traceMonitorAssert($single[0]['udid'] === $udid25, 'single-position payload must preserve first UDID');
traceMonitorAssert($single[1]['udid'] === null, 'missing second position must remain null');

$extra = TraceMonitorPolicy::entries(base64_encode($udid25 . '|' . $udid40 . '|ignored'));
traceMonitorAssert($extra[0]['udid'] === $udid25 && $extra[1]['udid'] === $udid40, 'legacy parser must ignore positions after second');
traceMonitorAssert(TraceMonitorPolicy::entries('') === array(), 'empty trace value must produce no entries');

echo "OK trace_monitor_policy_test\n";
