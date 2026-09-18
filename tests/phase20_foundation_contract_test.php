<?php
require_once dirname(__DIR__) . '/application/common/library/IpaFoundation.php';

use app\common\library\IpaFoundation;

function phase20Assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL phase20_foundation_contract_test: {$message}\n");
        exit(1);
    }
}

phase20Assert(IpaFoundation::PARSER_VERSION === 1, 'parser version baseline');
phase20Assert(IpaFoundation::normalizeRemotePath('a\\app//Demo.ipa') === '/a/app/Demo.ipa', 'remote path normalization');
phase20Assert(IpaFoundation::remotePathHash('openlist', '/a/app/Demo.ipa') === IpaFoundation::remotePathHash('openlist', 'a/app//Demo.ipa'), 'stable remote path identity');
phase20Assert(IpaFoundation::isTaskState('interrupted'), 'interrupted task state reserved');
phase20Assert(!IpaFoundation::isTaskState('unknown'), 'unknown task state rejected');
phase20Assert(IpaFoundation::isConfidence('exact') && IpaFoundation::isConfidence('derived') && IpaFoundation::isConfidence('fallback'), 'confidence levels');

$key1 = IpaFoundation::idempotencyKey('writeback', ['metadata_id' => 5, 'category_id' => 9]);
$key2 = IpaFoundation::idempotencyKey('writeback', ['category_id' => 9, 'metadata_id' => 5]);
phase20Assert($key1 === $key2, 'idempotency identity order independent');

$planA = ['target' => ['path' => '/a.ipa', 'md5' => 'ABC'], 'action' => 'rename'];
$planB = ['action' => 'rename', 'target' => ['md5' => 'ABC', 'path' => '/a.ipa']];
phase20Assert(IpaFoundation::planHash($planA) === IpaFoundation::planHash($planB), 'plan hash canonical for associative keys');

$sql = file_get_contents(dirname(__DIR__) . '/application/admin/command/Install/phase20_ipa_foundation.sql');
foreach (['fa_ipa_metadata','fa_ipa_binding','fa_ipa_scan_task','fa_ipa_scan_task_item','fa_ipa_governance_issue','fa_ipa_operation_log','fa_ipa_writeback_rule'] as $table) {
    phase20Assert(strpos($sql, 'CREATE TABLE IF NOT EXISTS `' . $table . '`') !== false, "migration contains {$table}");
}
phase20Assert(strpos($sql, 'uniq_idempotency') !== false, 'operation idempotency unique constraint');
phase20Assert(strpos($sql, 'uniq_category') !== false && strpos($sql, 'uniq_metadata') !== false, 'one-to-one binding constraints');
phase20Assert(strpos($sql, 'openlist_mutate') !== false && strpos($sql, 'writeback_apply') !== false, 'high-risk permission nodes');

echo "OK phase20_foundation_contract_test\n";
