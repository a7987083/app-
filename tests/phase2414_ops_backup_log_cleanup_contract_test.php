<?php

$root = dirname(__DIR__);

function read_file_or_fail($path)
{
    $content = @file_get_contents($path);
    if ($content === false) {
        fwrite(STDERR, "missing file: {$path}\n");
        exit(1);
    }
    return $content;
}

function must_contain($content, $needle, $label)
{
    if (strpos($content, $needle) === false) {
        fwrite(STDERR, "missing contract: {$label} ({$needle})\n");
        exit(1);
    }
}

function must_not_contain($content, $needle, $label)
{
    if (strpos($content, $needle) !== false) {
        fwrite(STDERR, "forbidden contract survived: {$label} ({$needle})\n");
        exit(1);
    }
}

$manager = read_file_or_fail($root . '/application/common/library/update/UpdateBackupManager.php');
$ops = read_file_or_fail($root . '/application/common/library/update/UpdateOps.php');
$controller = read_file_or_fail($root . '/application/admin/controller/general/Updatemaintenance.php');
$panel = read_file_or_fail($root . '/application/admin/view/general/updatemaintenance/panel.html');
$opsJs = read_file_or_fail($root . '/public/assets/js/backend/general/updatemaintenance.js');
$apiLogController = read_file_or_fail($root . '/application/admin/controller/general/Apilogmaintenance.php');
$commonScript = read_file_or_fail($root . '/application/admin/view/common/script.html');

must_contain($manager, "runtime' . DIRECTORY_SEPARATOR . 'update_backup", 'fixed updater backup root');
must_not_contain($manager, 'protectedBackupIds()', 'history-based rollback protection removed');
must_not_contain($manager, '仍被更新历史引用，禁止删除', 'history delete block removed');
must_contain($manager, 'realpath($this->backupDir)', 'backup root realpath validation');
must_contain($manager, 'strpos($targetNormalized, $base) !== 0', 'off-root deletion rejection');
must_contain($manager, 'is_link($path)', 'symlink deletion protection');
must_contain($manager, '单次最多处理 500 个备份', 'bounded bulk delete');
must_contain($manager, "'disk_free_bytes'", 'disk free space reporting');

must_not_contain($ops, '$referencedBackups', 'retention cleanup history reference protection removed');
must_not_contain($ops, '$protected = isset($row', 'successful update history protection removed');
must_contain($ops, "row['status'] === 'running'", 'running job status still protected');

must_contain($controller, "mode === 'backup_selected'", 'selected backup cleanup mode');
must_contain($controller, 'UpdateBackupManager(ROOT_PATH)', 'backup manager integration');
must_contain($controller, "mode !== 'retention'", 'cleanup mode allowlist');

foreach (['ops-backup-body', 'ops-backup-delete-selected', 'ops-backup-delete-all-free', 'ops-backup-check-all'] as $id) {
    must_contain($panel, $id, 'backup UI ' . $id);
}
must_not_contain($panel, '未保护备份', 'unprotected-only label removed');
must_not_contain($panel, '系统会锁定并禁止手动删除', 'rollback protection copy removed');
must_not_contain($opsJs, '回滚保护', 'protected backup UI removed');
must_not_contain($opsJs, ':not(:disabled)', 'protected checkbox exclusion removed');
must_contain($opsJs, "mode:'backup_selected'", 'backup delete request mode');
must_contain($opsJs, '.ops-backup-check:checked', 'selected backup collection');
must_contain($opsJs, '对应历史记录将无法回滚', 'delete consequence warning');

must_contain($apiLogController, "fa_api_request_log", 'dedicated API request log table');
must_contain($apiLogController, "DELETE_ALL_API_LOGS", 'server confirmation phrase');
must_contain($apiLogController, "isPost()", 'POST-only destructive action');
must_contain($apiLogController, "DELETE FROM `fa_api_request_log`", 'request-log-only delete');
if (strpos($apiLogController, 'dylib_verify_log') !== false) {
    fwrite(STDERR, "API log cleanup must not reference dylib_verify_log\n");
    exit(1);
}

must_contain($commonScript, 'project-api-logs-clear-all', 'clear-all log button');
must_contain($commonScript, '第一次确认', 'first destructive confirmation');
must_contain($commonScript, '第二次确认', 'second destructive confirmation');
must_contain($commonScript, 'general/apilogmaintenance/clear', 'dedicated log cleanup endpoint');

fwrite(STDOUT, "phase2415 rollback-protection removal contract: OK\n");
