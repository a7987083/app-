<?php

$root = dirname(__DIR__);

function requireContains($content, $needle, $label)
{
    if (strpos($content, $needle) === false) {
        fwrite(STDERR, "missing contract: {$label}\n");
        exit(1);
    }
}

function requireOrdered($content, array $needles, $label)
{
    $last = -1;
    foreach ($needles as $needle) {
        $pos = strpos($content, $needle);
        if ($pos === false || $pos <= $last) {
            fwrite(STDERR, "invalid order: {$label} ({$needle})\n");
            exit(1);
        }
        $last = $pos;
    }
}

$controller = file_get_contents($root . '/application/admin/controller/DylibCenter.php');
$service = file_get_contents($root . '/application/common/library/Ipa/DylibVerificationService.php');
$publicController = file_get_contents($root . '/application/index/controller/DylibVerify.php');
$view = file_get_contents($root . '/application/admin/view/dylib_center/index.html');
$js = file_get_contents($root . '/public/assets/js/backend/dylib_center.js');
$client = file_get_contents($root . '/clients/ios/ZONDylibVerify/ZONVerifyClient.m');
$readme = file_get_contents($root . '/clients/ios/ZONDylibVerify/README.md');

foreach ([$controller, $service, $publicController, $view, $js, $client, $readme] as $index => $content) {
    if ($content === false) {
        fwrite(STDERR, "unable to load contract source {$index}\n");
        exit(1);
    }
}

// Dylib enable/disable must remain a verification-chain decision, not a UI-only state.
requireContains($service, "->where('enabled', 1)->find()", 'verification rejects disabled dylib');
requireContains($service, "'dylib_unknown', 'block'", 'disabled/unknown response remains fail-closed');
requireContains($controller, 'public function setDylibEnabled()', 'admin toggle endpoint');
requireContains($controller, "'enabled' => \$enabled", 'toggle persists enabled flag');

// Delete policy: preserve any registration that already owns business/audit history.
requireContains($controller, "Db::name('dylib_version')->where('dylib_id', \$id)->count()", 'version reference check');
requireContains($controller, "Db::name('dylib_app_binding')->where('dylib_id', \$id)->count()", 'BundleID reference check');
requireContains($controller, "Db::name('dylib_verify_log')->where('dylib_key'", 'verification history reference check');
requireContains($controller, '为保留历史禁止删除，请改为停用', 'referenced delete blocked');
requireContains($js, 'Layer.confirm(', 'delete second confirmation');
requireContains($js, "url: 'dylib_center/deleteDylib'", 'delete uses existing Fast.api.ajax path');

// Dylib key is part of the signed lookup contract and must not mutate during ordinary edits.
requireContains($controller, 'Dylib key cannot be changed after registration', 'immutable dylib key');
requireContains($js, ".prop('readonly', true)", 'edit UI keeps dylib key read-only');

// Page structure required by the 2405 workflow.
requireOrdered($view, [
    '1. Dylib 注册',
    '2. 接入说明',
    '3. 游戏授权（BundleID）',
    '4. 版本控制',
    '5. 验证记录',
], 'Dylib center section order');

// Integration documentation must be extracted from the real current protocol.
requireContains($readme, 'POST /index/dylib_verify/verify', 'documented Objective-C endpoint');
requireContains($view, 'POST /index/dylib_verify/verify', 'admin integration endpoint');
requireContains($view, 'udid\\nbundle_id\\ndylib_key\\ndylib_version\\ndylib_build\\ndylib_sha256\\ntimestamp\\nnonce', 'canonical signing order');
requireContains($client, '@"dylib_key": self.configuration.dylibKey', 'legacy/current client dylib_key payload');
requireContains($client, '@"bundle_id": bundleID', 'legacy/current client BundleID payload');
requireContains($service, "->where('dylib_id', (int)\$dylib['id'])", 'BundleID/version remain scoped to dylib id');
requireContains($service, "->where('bundle_id', \$bundleId)", 'BundleID binding behavior unchanged');

// Chinese UI is presentation-only; raw protocol/storage enums remain present unchanged.
foreach (['active', 'testing', 'deprecated', 'blocked', 'revoked'] as $state) {
    requireContains($js, $state . ':', "UI state mapping {$state}");
}
foreach (['allow', 'disable_feature', 'show_message', 'block'] as $action) {
    requireContains($js, $action . ':', "UI action mapping {$action}");
}
foreach (['dylib_unknown', 'bundle_not_allowed', 'version_blocked', 'version_revoked', 'integrity_mismatch'] as $code) {
    requireContains($js, $code . ':', "UI result mapping {$code}");
}
requireContains($view, 'value="active"', 'raw active enum retained');
requireContains($view, 'value="testing"', 'raw testing enum retained');
requireContains($view, 'value="disable_feature"', 'raw disable_feature enum retained');

// Public controller contract remains POST-only and still delegates to the same service.
requireContains($publicController, 'if (!$this->request->isPost())', 'verification POST-only contract');
requireContains($publicController, 'DylibVerificationService::verify', 'verification service delegation unchanged');

echo "phase2405 dylib lifecycle contract ok\n";
