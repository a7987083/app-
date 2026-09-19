<?php

function phase20RcAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL phase20_rc_release_contract_test: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$manifest = file_get_contents($root . '/release/online-update-files.txt');
$builder = file_get_contents($root . '/tools/build_online_update.php');
$checklist = file_get_contents($root . '/release/PHASE20_RC_CHECKLIST.md');
$acceptanceExample = file_get_contents($root . '/release/PHASE20_EXTERNAL_ACCEPTANCE.example.json');

phase20RcAssert($manifest !== false, 'online update manifest readable');
phase20RcAssert($builder !== false, 'online update builder readable');
phase20RcAssert($checklist !== false, 'RC checklist readable');
phase20RcAssert($acceptanceExample !== false, 'external acceptance example readable');

$requiredProgramFiles = [
    'application/admin/controller/IpaCenter.php',
    'application/admin/controller/IpaRecovery.php',
    'application/admin/controller/IpaProduction.php',
    'application/admin/controller/IpaLifecycle.php',
    'application/admin/command/IpaScan.php',
    'application/admin/command/IpaParse.php',
    'application/admin/view/ipa_center/governance.html',
    'application/common/library/IpaGovernanceService.php',
    'application/common/library/IpaGovernanceBatchService.php',
    'application/common/library/IpaGovernanceRecoveryService.php',
    'application/common/library/IpaGovernanceLifecycleService.php',
    'application/common/library/IpaRangeMetricsService.php',
    'application/common/library/IpaRetentionService.php',
    'scripts/ipa-range-info.py',
    'public/assets/js/backend/ipa_center.js',
    'public/assets/js/backend/ipa_governance_production.js',
];

foreach ($requiredProgramFiles as $path) {
    phase20RcAssert(strpos($manifest, $path) !== false, 'manifest contains ' . $path);
    phase20RcAssert(is_file($root . '/' . $path), 'program source exists ' . $path);
}

$orderedSql = [
    '2026091901_phase20_ipa_foundation.sql' => 'application/admin/command/Install/phase20_ipa_foundation.sql',
    '2026091902_phase20_ipa_center.sql' => 'application/admin/command/Install/phase20_ipa_center.sql',
    '2026091903_phase20_ipa_scan.sql' => 'application/admin/command/Install/phase20_ipa_scan.sql',
    '2026091904_phase20_ipa_parser.sql' => 'application/admin/command/Install/phase20_ipa_parser.sql',
    '2026091905_phase20_ipa_binding.sql' => 'application/admin/command/Install/phase20_ipa_binding.sql',
    '2026091906_phase20_ipa_writeback.sql' => 'application/admin/command/Install/phase20_ipa_writeback.sql',
    '2026091907_phase20_ipa_governance.sql' => 'application/admin/command/Install/phase20_ipa_governance.sql',
    '2026091908_phase20_ipa_production.sql' => 'application/admin/command/Install/phase20_ipa_production.sql',
    '2026091909_phase20_ipa_lifecycle.sql' => 'application/admin/command/Install/phase20_ipa_lifecycle.sql',
];

$lastPosition = -1;
foreach ($orderedSql as $target => $source) {
    phase20RcAssert(is_file($root . '/' . $source), 'canonical SQL exists ' . $source);
    phase20RcAssert(strpos($manifest, $source) !== false, 'manifest contains SQL source ' . $source);
    $needle = "'" . $target . "' => \$root . '/" . $source . "'";
    $position = strpos($builder, $needle);
    phase20RcAssert($position !== false, 'builder packages ordered SQL ' . $target);
    phase20RcAssert($position > $lastPosition, 'builder SQL order preserved at ' . $target);
    $lastPosition = $position;
}

phase20RcAssert(strpos($builder, "'mysql/' . \$targetName") !== false, 'builder writes Phase 20 migrations to mysql payload');
phase20RcAssert(strpos($builder, "GITHUB_WORKFLOW') !== 'ZONOE Source Release'") !== false, 'formal release guard scoped to release workflow');
phase20RcAssert(strpos($builder, 'PHASE20_EXTERNAL_ACCEPTANCE.json') !== false, 'formal release requires external acceptance record');
phase20RcAssert(strpos($builder, "\$status !== 'passed'") !== false, 'formal release requires passed external acceptance');
phase20RcAssert(strpos($builder, 'readiness_run') !== false && strpos($builder, 'acceptance_commit') !== false, 'formal release acceptance carries readiness evidence');
phase20RcAssert(strpos($builder, "intval(\$readinessRun) <= 0") !== false, 'formal release rejects placeholder readiness run');
phase20RcAssert(strpos($builder, "preg_match('/^0{40}$/', \$acceptanceCommit)") !== false, 'formal release rejects zero commit placeholder');
phase20RcAssert(strpos($builder, 'requiredEvidence') !== false && strpos($builder, 'replace-with-') !== false, 'formal release rejects placeholder evidence');

$example = json_decode($acceptanceExample, true);
phase20RcAssert(is_array($example), 'external acceptance example valid JSON');
foreach (['status','environment','verified_at','acceptance_commit','readiness_run','operator','evidence'] as $field) {
    phase20RcAssert(array_key_exists($field, $example), 'external acceptance example field ' . $field);
}
phase20RcAssert(isset($example['status']) && $example['status'] === 'pending', 'external acceptance example fails closed');
phase20RcAssert(isset($example['readiness_run']) && (string)$example['readiness_run'] === '0', 'external acceptance example cannot satisfy readiness gate');
phase20RcAssert(isset($example['acceptance_commit']) && preg_match('/^0{40}$/', $example['acceptance_commit']) === 1, 'external acceptance example cannot satisfy commit gate');

$hasExternalAcceptance = strpos($checklist, 'External pre-production acceptance') !== false ||
    strpos($checklist, 'Pre-production E2E') !== false ||
    strpos($checklist, 'Production E2E') !== false;
phase20RcAssert($hasExternalAcceptance, 'checklist contains external environment acceptance gate');
phase20RcAssert(strpos($checklist, 'External rollback / usability gate') !== false || strpos($checklist, 'Rollback') !== false, 'checklist contains rollback gate');
phase20RcAssert(strpos($checklist, 'Do not change `VERSION`') !== false, 'checklist protects formal version metadata before RC selection');
phase20RcAssert(strpos($checklist, 'must not be marked complete from CI mocks alone') !== false, 'checklist separates CI evidence from external acceptance');

echo "OK phase20_rc_release_contract_test\n";
