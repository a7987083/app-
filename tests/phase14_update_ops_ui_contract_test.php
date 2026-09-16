<?php

function p143ui_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL phase14_update_ops_ui_contract_test: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/application/admin/controller/general/Updatemaintenance.php');
$view = file_get_contents($root . '/application/admin/view/general/updatemaintenance/panel.html');
$js = file_get_contents($root . '/public/assets/js/backend/general/updatemaintenance.js');
$configJs = file_get_contents($root . '/public/assets/js/backend/general/config.js');

p143ui_assert(strpos($controller, "protected \$noNeedRight = ['index', 'panel'];") !== false, 'destructive cleanup/scan must not bypass backend rights');
p143ui_assert(strpos($controller, "public function panel()") !== false, 'visual maintenance panel action missing');
p143ui_assert(strpos($controller, "public function cleanup()") !== false, 'cleanup endpoint missing');
p143ui_assert(strpos($controller, "public function scan()") !== false, 'scan endpoint missing');
p143ui_assert(strpos($controller, "if (!\$this->request->isPost())") !== false, 'destructive endpoints must be POST-only');
p143ui_assert(strpos($controller, "intval(\$this->request->param('apply', 0)) === 1") !== false, 'cleanup must default to preview mode');

foreach (['ops-version', 'site-total-bytes', 'site-storage-summary', 'site-review-list', 'site-largest', 'ops-preview', 'ops-clean', 'ops-jobs', 'scan-progress-bar', 'cleanup-progress-bar'] as $id) {
    p143ui_assert(strpos($view, 'id="' . $id . '"') !== false, 'panel element missing: ' . $id);
}

p143ui_assert(strpos($js, "general/updatemaintenance/index") !== false, 'panel snapshot endpoint is not wired');
p143ui_assert(strpos($js, "general/updatemaintenance/scan") !== false, 'async scan endpoint is not wired');
p143ui_assert(strpos($js, "general/updatemaintenance/cleanup") !== false, 'panel cleanup endpoint is not wired');
p143ui_assert(strpos($js, "type:'POST'") !== false || strpos($js, "type: 'POST'") !== false, 'destructive request must use POST');
p143ui_assert(strpos($js, "data:{apply:0}") !== false || strpos($js, "data: {apply:0}") !== false, 'dry-run preview flag missing');
p143ui_assert(strpos($js, "data:{apply:1}") !== false || strpos($js, "data: {apply:1}") !== false, 'confirmed cleanup flag missing');
p143ui_assert(strpos($js, "setInterval(pollStatus, 1000)") !== false, 'progress polling missing');
p143ui_assert(strpos($js, "layer.confirm") !== false, 'destructive cleanup must require confirmation');

p143ui_assert(strpos($configJs, 'id="zonoe-update-ops"') !== false, 'config screen update-operations entry button missing');
p143ui_assert(strpos($configJs, "general/updatemaintenance/panel") !== false, 'config screen is not wired to the update-operations panel');
p143ui_assert(strpos($configJs, "openUpdateOperations") !== false, 'update-operations entry handler missing');
p143ui_assert(strpos($configJs, "更新运维中心") !== false, 'update-operations entry label missing');

fwrite(STDOUT, "OK phase14_update_ops_ui_contract_test panel=passed diagnostics=passed async_cleanup_guard=passed entry_point=passed\n");
