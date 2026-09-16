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

p143ui_assert(strpos($controller, "public function panel()") !== false, 'visual maintenance panel action missing');
p143ui_assert(strpos($controller, "public function cleanup()") !== false, 'cleanup endpoint missing');
p143ui_assert(strpos($controller, "intval(\$this->request->param('apply', 0)) === 1") !== false, 'cleanup must default to dry-run');
p143ui_assert(strpos($controller, "'panel'") !== false, 'panel route must be allowed by controller');

foreach (['ops-version', 'ops-backups', 'ops-running', 'ops-lock', 'ops-storage', 'ops-preview', 'ops-clean', 'ops-jobs'] as $id) {
    p143ui_assert(strpos($view, 'id="' . $id . '"') !== false, 'panel element missing: ' . $id);
}

p143ui_assert(strpos($js, "general/updatemaintenance/index") !== false, 'panel snapshot endpoint is not wired');
p143ui_assert(strpos($js, "general/updatemaintenance/cleanup") !== false, 'panel cleanup endpoint is not wired');
p143ui_assert(strpos($js, "data: {apply: apply ? 1 : 0}") !== false, 'cleanup apply flag is not explicit');
p143ui_assert(strpos($js, "cleanup(false)") !== false, 'dry-run preview action missing');
p143ui_assert(strpos($js, "cleanup(true)") !== false, 'confirmed cleanup action missing');
p143ui_assert(strpos($js, "layer.confirm") !== false, 'destructive cleanup must require confirmation');

fwrite(STDOUT, "OK phase14_update_ops_ui_contract_test panel=passed diagnostics=passed cleanup_guard=passed\n");
