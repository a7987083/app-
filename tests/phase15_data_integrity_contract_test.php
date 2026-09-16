<?php

function p15Assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL phase15_data_integrity_contract_test: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$required = [
    'application/common/library/DataIntegrityAudit.php',
    'application/admin/controller/Integrity.php',
    'application/admin/view/authorization/integrity.html',
    'application/common/library/AuthorizationSchema.php',
    'application/common/library/CardDeviceTransfer.php',
];
foreach ($required as $path) {
    p15Assert(is_file($root . '/' . $path), 'missing ' . $path);
}

$audit = file_get_contents($root . '/application/common/library/DataIntegrityAudit.php');
foreach ([
    'GROUP BY `kami` HAVING COUNT(*) > 1',
    "TRIM(COALESCE(`kami`,''))=''",
    "CHAR_LENGTH(`udid`) NOT IN (25,40)",
    'invalid_scope',
    'activated_missing_usetime',
    'end_before_use',
    'orphan_kami',
    'orphan_app',
    'wrong_scope',
    'app_scope_without_mapping',
    'authorization_event_orphans',
    'transfer_log_orphans',
    'active_duplicate_udid_groups',
    'unique_index_ready',
    'expired_blacklist',
    'old_authorization_events',
    'old_transfer_logs',
    "'mode' => 'audit_only'",
    "'stable_semantics' => 'active_entitlements_only'",
] as $needle) {
    p15Assert(strpos($audit, $needle) !== false, 'audit contract missing ' . $needle);
}
p15Assert(strpos($audit, 'ALTER TABLE') === false, 'audit must not mutate schema directly');
p15Assert(strpos($audit, 'DELETE FROM') === false, 'audit must not delete production data');

$schema = file_get_contents($root . '/application/common/library/AuthorizationSchema.php');
p15Assert(strpos($schema, "['integrity/index', '数据完整性'") !== false, 'data-integrity admin menu missing');

$controller = file_get_contents($root . '/application/admin/controller/Integrity.php');
p15Assert(strpos($controller, 'DataIntegrityAudit::snapshot()') !== false, 'integrity controller does not run audit');

$view = file_get_contents($root . '/application/admin/view/authorization/integrity.html');
p15Assert(strpos($view, '不会自动创建 UNIQUE INDEX') !== false, 'UI must state unique index is not automatic');
p15Assert(strpos($view, '换绑稳定语义') !== false, 'UI must expose unbind semantics section');
p15Assert(strpos($view, "url('integrity/index')") !== false, 'reaudit route must target Integrity::index');
p15Assert(strpos($view, "url('authorization/integrity')") === false, 'stale reaudit 404 route remains');

// Freeze the existing /unbind semantics for Phase 15: only currently active
// entitlement rows move; expired historical rows stay on their original UDID.
$transfer = file_get_contents($root . '/application/common/library/CardDeviceTransfer.php');
p15Assert(strpos($transfer, "->where('udid', \$oldUdid)\n                ->where('jh', 1)\n                ->where('endtime', '>', \$now)") !== false, 'unbind must select only active entitlement rows');
p15Assert(strpos($transfer, 'foreach ($activeRows as $row)') !== false, 'unbind must move the active entitlement set');
p15Assert(strpos($transfer, "'udid' => \$newUdid") !== false, 'unbind move target missing');

echo "OK phase15_data_integrity_contract_test\n";
