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
$controller = file_get_contents($root . '/application/admin/controller/DylibCenter.php');
$docsController = file_get_contents($root . '/application/admin/controller/DylibApiDocs.php');
$docs = file_get_contents($root . '/application/common/library/Ipa/DylibApiDocumentation.php');

ux_assert($view !== false, 'Dylib Center view missing');
ux_assert($js !== false, 'Dylib Center JS missing');
ux_assert($codegen !== false, 'inline codegen JS missing');
ux_assert($controller !== false, 'Dylib Center controller missing');
ux_assert($docsController !== false, 'API docs download controller missing');
ux_assert($docs !== false, 'API documentation catalog missing');

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

// 2413: the exact Advanced/API page must expose the complete catalog, not only
// Runtime Config + Verify + signing. Signing is its own protocol tab.
ux_assert(strpos($view, '8 个入口') !== false, 'full API count missing');
ux_assert(strpos($view, '全部下载 ZIP') !== false, 'download-all control missing');
ux_assert(strpos($view, 'href="#api-doc-signature"') !== false, 'signature must be a separate protocol tab');
foreach ([
    '/authorization',
    '/appstore',
    '/index/index/apiface',
    '/index/index/dylib',
    '/unbind',
    '/unbind/query',
    '/index/dylib_verify/config',
] as $path) {
    ux_assert(strpos($view, $path) !== false, 'full API catalog missing ' . $path);
}
ux_assert(strpos($view, '{$runtimeConfig.verify_path|htmlentities}') !== false, 'verify path must remain dynamic');
ux_assert(strpos($view, 'HTML 页面兼容入口') !== false || strpos($view, 'HTML 页面') !== false, 'page routes must not be mislabeled as JSON APIs');

foreach (['README.md', 'API_OVERVIEW.md', 'API_REFERENCE.md', 'ERROR_CODES.md', 'SIGNATURE.md', 'RESPONSE_MODEL.md', 'FLOW.md', 'schemas/api.json', 'schemas/error_codes.json'] as $name) {
    ux_assert(strpos($docs, $name) !== false, 'download bundle missing ' . $name);
}
foreach (['Objective-C.md', 'Swift.md', 'curl.md', 'Python.md'] as $name) {
    ux_assert(strpos($docs, $name) !== false, 'example bundle missing ' . $name);
}
ux_assert(strpos($docs, '<VERIFY_SECRET>') !== false, 'download bundle must use secret placeholder');
ux_assert(strpos($docsController, 'verify_secret_ciphertext') === false, 'download controller must never read encrypted Verify Secret');
ux_assert(strpos($docsController, 'DylibApiDocumentation::exportFiles') !== false, 'download controller must use canonical docs exporter');
ux_assert(strpos($docsController, "header('Content-Type: application/zip')") !== false, 'download response must be a ZIP');

ux_assert(strpos($codegen, "$('#codegen-slot').html(panelHtml())") !== false, 'codegen must render into overview slot');
ux_assert(strpos($codegen, 'id="dcg-options" class="collapse"') !== false, 'codegen advanced options should be collapsed');
ux_assert(strpos($codegen, 'id="dcg-preview-area" class="hidden"') !== false, 'code preview should be hidden until requested');

foreach (['global-dylib-select', 'syncCurrentDylib', 'integration-config-url', 'version-help-2411', 'version-advanced-options', 'notice-help-2411', 'log-filter-bar', 'log-page-size', 'log-delete-selected', 'log-delete-filtered'] as $needle) {
    ux_assert(strpos($js, $needle) !== false, '2411 UX contract missing ' . $needle);
}
foreach (['普通授权', '指定 App 高级授权', '全软件源高级授权', '内部构建号', '文件 SHA256', '禁用受保护功能'] as $needle) {
    ux_assert(strpos($js, $needle) !== false, 'Chinese UX help missing ' . $needle);
}
ux_assert(strpos($js, "pageSize:1000") !== false && strpos($js, "pageList:[100,500,1000]") !== false, 'verification log 1000/page option missing');
ux_assert(strpos($js, "activateTab('#tab-advanced')") !== false, 'integration action must switch to Advanced tab');
ux_assert(strpos($js, "$('#integration-detail').collapse('show')") !== false, 'integration action must expand documentation');
ux_assert(strpos($js, "activateTab('#tab-overview')") !== false, 'edit action must return to Overview');
ux_assert(strpos($js, "shown.bs.tab") !== false && strpos($js, "bootstrapTable('resetView')") !== false, 'hidden tab tables must reset layout when shown');

foreach (['public function setNoticeEnabled()', 'public function deleteNotice()', 'public function deleteLogs()', 'protected function buildLogQuery()'] as $needle) {
    ux_assert(strpos($controller, $needle) !== false, 'backend operation missing ' . $needle);
}
foreach (["param('dylib_key'", "param('bundle_id'", "param('result_code'", "param('dylib_version'", "param('udid_hash'", "param('created_from'", "param('created_to'"] as $needle) {
    ux_assert(strpos($controller, $needle) !== false, 'log filter missing ' . $needle);
}
ux_assert(strpos($controller, 'min(1000') !== false, 'server pagination must allow 1000 rows');

fwrite(STDOUT, "OK dylib_center_ux_contract 2413 full-api-catalog docs-zip shared-dylib version-help notice-crud log-filter-delete\n");
