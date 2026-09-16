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

p143ui_assert(strpos($controller, "protected \$noNeedRight = ['index', 'panel'];") !== false, 'destructive cleanup must not bypass backend rights');
p143ui_assert(strpos($controller, "public function panel()") !== false, 'visual maintenance panel action missing');
p143ui_assert(strpos($controller, "public function cleanup()") !== false, 'cleanup endpoint missing');
p143ui_assert(strpos($controller, "if (!\$this->request->isPost())") !== false, 'cleanup must be POST-only');
p143ui_assert(strpos($controller, "intval(\$this->request->param('apply', 0)) === 1") !== false, 'cleanup must default to dry-run');

foreach (['ops-version', 'ops-backups', 'ops-running', 'ops-lock', 'ops-storage', 'ops-preview', 'ops-clean', 'ops-jobs'] as $id) {
    p143ui_assert(strpos($view, 'id="' . $id . '"') !== false, 'panel element missing: ' . $id);
}

p143ui_assert(strpos($js, "general/updatemaintenance/index") !== false, 'panel snapshot endpoint is not wired');
p143ui_assert(strpos($js, "general/updatemaintenance/cleanup") !== false, 'panel cleanup endpoint is not wired');
p143ui_assert(strpos($js, "type: 'POST'") !== false, 'cleanup request must use POST');
p143ui_assert(strpos($js, "data: {apply: apply ? 1 : 0}") !== false, 'cleanup apply flag is not explicit');
p143ui_assert(strpos($js, "cleanup(false)") !== false, 'dry-run preview action missing');
p143ui_assert(strpos($js, "cleanup(true)") !== false, 'confirmed cleanup action missing');
p143ui_assert(strpos($js, "layer.confirm") !== false, 'destructive cleanup must require confirmation');

p143ui_assert(strpos($configJs, 'id="zonoe-update-ops"') !== false, 'config screen update-operations entry button missing');
p143ui_assert(strpos($configJs, "general/updatemaintenance/panel") !== false, 'config screen is not wired to the update-operations panel');
p143ui_assert(strpos($configJs, "type: 2") !== false, 'update-operations panel should open in an embedded admin layer');
p143ui_assert(strpos($configJs, "openUpdateOperations") !== false, 'update-operations entry handler missing');
p143ui_assert(strpos($configJs, "更新运维中心") !== false, 'update-operations entry label missing');

fwrite(STDOUT, "OK phase14_update_ops_ui_contract_test panel=passed diagnostics=passed cleanup_guard=passed entry_point=passed\n");
