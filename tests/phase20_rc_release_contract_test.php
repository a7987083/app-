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
$releaseWorkflow = file_get_contents($root . '/.github/workflows/phase13_github_release.yml');

phase20RcAssert($manifest !== false, 'online update manifest readable');
phase20RcAssert($builder !== false, 'online update builder readable');
phase20RcAssert($checklist !== false, 'RC checklist readable');
phase20RcAssert($releaseWorkflow !== false, 'historical source release workflow readable');

$requiredProgramFiles = [
    'application/admin/controller/IpaCenter.php',
    'application/admin/controller/IpaRecovery.php',
    'application/admin/controller/IpaProduction.php',
    'application/admin/controller/IpaLifecycle.php',
    'application/admin/command/IpaScan.php',
    'application/admin/command/IpaParse.php',
    'application/admin/view/ipa_center/governance.html',
    'application/common/library/IpaMetadataQueryService.php',
    'application/common/library/IpaMetadataWorksetService.php',
    'application/common/library/IpaParseCache.php',
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
    '2026091910_phase20_ipa_sources_v2.sql' => 'application/admin/command/Install/phase20_ipa_sources_v2.sql',
    '2026091911_phase20_ipa_workset.sql' => 'application/admin/command/Install/phase20_ipa_workset.sql',
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

foreach (array_slice(array_keys($orderedSql),0,9) as $target) {
    phase20RcAssert(strpos($releaseWorkflow, "mysql/" . $target) !== false, 'formal release verifies legacy packaged SQL ' . $target);
}
phase20RcAssert(strpos($builder, "2026091910_phase20_ipa_sources_v2.sql") !== false, 'builder packages software-source registry migration');
phase20RcAssert(strpos($builder, "2026091911_phase20_ipa_workset.sql") !== false, 'builder packages active-workset/parse-cache migration');

$builderCompact = preg_replace('/\s+/', '', $builder);
phase20RcAssert(strpos($builderCompact, "'mysql/'." . '$targetName') !== false, 'builder writes Phase 20 migrations to mysql payload');
phase20RcAssert(strpos($builder, 'PHASE20_EXTERNAL_ACCEPTANCE') === false, 'builder keeps historical manifest-driven release flow');
phase20RcAssert(strpos($releaseWorkflow, 'name: ZONOE Source Release') !== false, 'Phase 20 stays on historical ZONOE Source Release workflow');
phase20RcAssert(strpos($releaseWorkflow, 'contents: write') !== false, 'historical release permission remains unchanged');
phase20RcAssert(strpos($releaseWorkflow, 'phase20-integration:') !== false, 'formal release includes Phase 20 integration gate');
phase20RcAssert(strpos($releaseWorkflow, 'needs: [php70-regression, mysql57-migration, phase19-3-1-http-load, phase20-integration]') !== false, 'package waits for existing gates plus Phase 20 integration');
phase20RcAssert(strpos($releaseWorkflow, 'php tests/phase20_governance_contract_test.php') !== false, 'formal PHP 7.0 regression runs Phase 20 governance contract');
phase20RcAssert(strpos($releaseWorkflow, 'python3 scripts/ipa-range-info.py --self-test') !== false, 'formal regression runs IPA parser self-test');
phase20RcAssert(strpos($releaseWorkflow, 'phase20_ipa_lifecycle.sql') !== false, 'formal MySQL 5.7 gate runs Phase 20 lifecycle migration');
phase20RcAssert(strpos($releaseWorkflow, 'program/application/admin/controller/IpaCenter.php') !== false, 'formal package gate verifies Phase 20 program payload');
phase20RcAssert(strpos($releaseWorkflow, 'PHASE20_EXTERNAL_ACCEPTANCE') === false, 'formal release has no extra acceptance-file gate');
phase20RcAssert(strpos($checklist, 'Rollback') !== false || strpos($checklist, 'rollback') !== false, 'checklist contains rollback validation');

echo "OK phase20_rc_release_contract_test\n";
