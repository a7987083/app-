<?php

function ux_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL dylib_center_ux_contract: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$view = file_get_contents($root . '/application/admin/view/dylib_center/index.html');
$js = file_get_contents($root . '/public/assets/js/backend/dylib_center.js');
$codegen = file_get_contents($root . '/public/assets/js/backend/dylib_codegen_inline.js');

ux_assert($view !== false, 'Dylib Center view missing');
ux_assert($js !== false, 'Dylib Center JS missing');
ux_assert($codegen !== false, 'inline codegen JS missing');

foreach (['#tab-overview', '#tab-versions', '#tab-notices', '#tab-logs', '#tab-advanced'] as $tab) {
    ux_assert(strpos($view, 'href="' . $tab . '"') !== false, 'missing navigation tab ' . $tab);
}

foreach (['id="dylib-form"', 'id="version-form"', 'id="notice-form"', 'id="runtime-config-form"', 'id="verify-log-table"', 'id="codegen-slot"'] as $required) {
    ux_assert(strpos($view, $required) !== false, 'missing existing UI contract ' . $required);
}

ux_assert(strpos($view, 'id="integration-detail" class="panel-collapse collapse"') !== false, 'integration documentation must be collapsed by default');
ux_assert(strpos($view, 'id="access-model-detail" class="panel-collapse collapse"') !== false, 'access model must be collapsed by default');
ux_assert(strpos($view, '<strong>1. Dylib 注册</strong>') === false, 'legacy numbered flat section remains');
ux_assert(strpos($view, '<strong>4. 运行配置与通知</strong>') === false, 'runtime and notices must no longer be one flat section');

ux_assert(strpos($codegen, "$('#codegen-slot').html(panelHtml())") !== false, 'codegen must render into overview slot');
ux_assert(strpos($codegen, "$('#section-version').before(panelHtml())") === false, 'codegen must not inject another numbered top-level section');
ux_assert(strpos($codegen, 'id="dcg-options" class="collapse"') !== false, 'codegen advanced options should be collapsed');
ux_assert(strpos($codegen, 'id="dcg-preview-area" class="hidden"') !== false, 'code preview should be hidden until requested');

ux_assert(strpos($js, "activateTab('#tab-advanced')") !== false, 'integration action must switch to Advanced tab');
ux_assert(strpos($js, "$('#integration-detail').collapse('show')") !== false, 'integration action must expand documentation');
ux_assert(strpos($js, "activateTab('#tab-overview')") !== false, 'edit action must return to Overview');
ux_assert(strpos($js, "shown.bs.tab") !== false && strpos($js, "bootstrapTable('resetView')") !== false, 'hidden tab tables must reset layout when shown');

fwrite(STDOUT, "OK dylib_center_ux_contract tabs=5 codegen=overview advanced=collapsed\n");
