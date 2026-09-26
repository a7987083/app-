<?php

$root = dirname(__DIR__);

function requireContains($content, $needle, $label)
{
    if (strpos($content, $needle) === false) {
        fwrite(STDERR, "missing contract: {$label}\n");
        exit(1);
    }
}

function requireNotContains($content, $needle, $label)
{
    if (strpos($content, $needle) !== false) {
        fwrite(STDERR, "unexpected contract: {$label}\n");
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
$jsCompact = preg_replace('/\s+/', '', $js);

// 2405 lifecycle invariants that remain valid after later authorization changes.
requireContains($service, "->where('enabled', 1)->find()", 'verification rejects disabled dylib');
requireContains($service, "'dylib_unknown', 'block'", 'disabled/unknown response remains fail-closed');
requireContains($controller, 'public function setDylibEnabled()', 'admin toggle endpoint');
requireContains($controller, "'enabled' => \$enabled", 'toggle persists enabled flag');

// 2410 intentionally changes the old history-protected delete contract: the
// operator may now permanently delete a Dylib and its related historical rows.
requireContains($controller, 'public function deleteDylib()', 'admin delete endpoint');
requireContains($controller, 'Db::startTrans();', 'destructive delete starts transaction');
requireContains($controller, "Db::name('dylib_version')->where('dylib_id', \$id)->delete()", 'delete cascades versions');
requireContains($controller, "Db::name('dylib_app_binding')->where('dylib_id', \$id)->delete()", 'delete cascades legacy bindings');
requireContains($controller, "Db::name('dylib_verify_log')->where('dylib_key'", 'delete cascades verification logs');
requireContains($controller, "Db::name('dylib')->where('id', \$id)->delete()", 'delete removes dylib row');
requireContains($controller, 'Db::commit();', 'destructive delete commits transaction');
requireContains($controller, 'Db::rollback();', 'destructive delete rolls back on failure');
requireNotContains($controller, '为保留历史禁止删除，请改为停用', 'old referenced-delete blocker removed');
requireContains($js, 'Layer.confirm(', 'delete second confirmation');
requireContains($jsCompact, "url:'dylib_center/deleteDylib'", 'delete uses Fast.api.ajax path');

// 2410 version CRUD: editing reuses saveVersion(id), deletion has a dedicated endpoint.
requireContains($controller, 'public function deleteVersion()', 'version delete endpoint');
requireContains($controller, "Db::name('dylib_version')->where('id', \$id)->delete()", 'version physical delete');
requireContains($js, 'js-version-edit', 'version edit control');
requireContains($js, 'js-version-delete', 'version delete control');
requireContains($jsCompact, "url:'dylib_center/deleteVersion'", 'version delete AJAX endpoint');

// Dylib key remains immutable after registration.
requireContains($controller, 'Dylib key cannot be changed after registration', 'immutable dylib key');
requireContains($jsCompact, ".prop('readonly',true)", 'edit UI keeps dylib key read-only');

// Legacy v1 signing contract and endpoint remain present for already shipped clients.
// 2412 replaces the old one-line compatibility sentence with a detailed API guide,
// so assert the actual documented protocol rather than stale copy text.
requireContains($readme, 'Protocol v1', 'legacy protocol documented');
requireContains($view, 'Protocol v1 canonical', 'legacy protocol visible in API integration guide');
requireContains($view, 'udid\\nbundle_id\\ndylib_key\\ndylib_version\\ndylib_build\\ndylib_sha256\\ntimestamp\\nnonce', 'v1 canonical ordering visible in API integration guide');
requireContains($view, '业务判断使用 <code>ok</code> + <code>code</code>', '2412 result-code handling visible in API integration guide');
requireContains($client, '@"dylib_key": self.configuration.dylibKey', 'client dylib_key payload');
requireContains($client, '@"bundle_id": bundleID', 'client BundleID payload');
requireContains($publicController, 'if (!$this->request->isPost())', 'verification POST-only contract');
requireContains($publicController, 'DylibVerificationService::verify', 'verification service delegation');

// Chinese UI is presentation-only; raw storage/protocol enums stay unchanged.
foreach (['active', 'testing', 'deprecated', 'blocked', 'revoked'] as $state) {
    requireContains($js, $state . ':', "UI state mapping {$state}");
}
foreach (['allow', 'disable_feature', 'show_message', 'block'] as $action) {
    requireContains($js, $action . ':', "UI action mapping {$action}");
}
foreach (['dylib_unknown', 'bundle_not_allowed', 'version_blocked', 'version_revoked', 'integrity_mismatch'] as $code) {
    requireContains($js, $code . ':', "historical/current result mapping {$code}");
}
requireContains($view, 'value="active"', 'raw active enum retained');
requireContains($view, 'value="testing"', 'raw testing enum retained');
requireContains($view, 'value="disable_feature"', 'raw disable_feature enum retained');

echo "phase2405 dylib lifecycle contract ok\n";
