<?php

$root = dirname(__DIR__);
$maintenance = file_get_contents($root . '/application/admin/command/IpaMaintenance.php');
$timer = file_get_contents($root . '/deploy/systemd/zonoe-ipa-maintenance.timer');

if ($maintenance === false || $timer === false) {
    fwrite(STDERR, "unable to load retention step1 sources\n");
    exit(1);
}

$required = [
    "const CHALLENGE_RETENTION_SECONDS = 86400",
    "const DELETE_BATCH_SIZE = 5000",
    "const DELETE_MAX_BATCHES = 20",
    "const DEVICE_KEY_RETENTION_SECONDS = 31536000",
    "'dylib_auth_challenge'",
    "'dylib_device_session'",
    "'dylib_verify_log'",
    "'api_request_log'",
    "'addtime'",
    "'dylib_device_key'",
    "'last_used_at'",
    'deleteStaleDeviceKeys',
    'deleteTerminalScanItems',
    'deleteTerminalScanJobs',
    "'ipa_parse_attempt'",
    "'ipa_scan_item'",
    "'ipa_scan_job'",
    "['completed', 'completed_with_errors', 'cancelled']",
    'cleanupRuntimeLogs',
    'RUNTIME_LOG_RETENTION_SECONDS = 2592000',
    'new UpdateOps(ROOT_PATH)',
    'cleanup(false)',
    'deleteExpiredById',
    "->order('id asc')",
    '->limit(self::DELETE_BATCH_SIZE)',
];
foreach ($required as $needle) {
    if (strpos($maintenance, $needle) === false) {
        fwrite(STDERR, "missing retention step1 contract: {$needle}\n");
        exit(1);
    }
}

if (strpos($maintenance, "Db::name('dylib_nonce')") !== false) {
    fwrite(STDERR, "retired dylib_nonce cleanup still present\n");
    exit(1);
}

if (strpos($timer, 'OnCalendar=*-*-* 04:20:00') === false) {
    fwrite(STDERR, "daily maintenance timer contract missing\n");
    exit(1);
}

$config = file_get_contents($root . '/application/extra/ipa_data_center.php');
if ($config === false || strpos($config, "'api_request_log_retention_days'") === false || strpos($config, "'scan_item_retention_days'") === false || strpos($config, "'scan_job_retention_days'") === false || strpos($config, "'parse_attempt_retention_days'") === false) {
    fwrite(STDERR, "retention config missing\n");
    exit(1);
}

echo "2436 retention step1 contract ok\n";
