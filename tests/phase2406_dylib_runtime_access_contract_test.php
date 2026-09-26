<?php

$root = dirname(__DIR__);

$service = file_get_contents($root . '/application/common/library/Ipa/DylibVerificationService.php');
$runtime = file_get_contents($root . '/application/common/library/Ipa/DylibRuntimeAccessService.php');
$configService = file_get_contents($root . '/application/common/library/Ipa/DylibRuntimeConfigService.php');
$parser = file_get_contents($root . '/application/common/library/Ipa/IpaParserService.php');
$inspector = file_get_contents($root . '/application/common/library/Ipa/MachOInspector.php');
$publicController = file_get_contents($root . '/application/index/controller/DylibVerify.php');
$adminController = file_get_contents($root . '/application/admin/controller/DylibCenter.php');
$view = file_get_contents($root . '/application/admin/view/dylib_center/index.html');
$js = file_get_contents($root . '/public/assets/js/backend/dylib_center.js');
$clientH = file_get_contents($root . '/clients/ios/ZONDylibVerify/ZONVerifyClient.h');
$clientM = file_get_contents($root . '/clients/ios/ZONDylibVerify/ZONVerifyClient.m');
$readme = file_get_contents($root . '/clients/ios/ZONDylibVerify/README.md');
$sql = file_get_contents($root . '/release/sql/2026092406_dylib_runtime_access.sql');
$cleanSql = file_get_contents($root . '/database/ipa_data_center_v1_3_dylib_runtime_access.sql');
$manifest = file_get_contents($root . '/release/online-update-files.txt');

foreach ([$service,$runtime,$configService,$parser,$inspector,$publicController,$adminController,$view,$js,$clientH,$clientM,$readme,$sql,$cleanSql,$manifest] as $index => $content) {
    if ($content === false) {
        fwrite(STDERR, "unable to load 2406 contract source {$index}\n");
        exit(1);
    }
}

// Old per-Dylib BundleID whitelist is history only; it must not gate active verification.
requireNotContains($service, "Db::name('dylib_app_binding')", 'active verify must not query legacy dylib_app_binding');
requireNotContains($service, "'bundle_not_allowed'", 'active verify must not emit retired BundleID whitelist result');
requireContains($service, 'DylibRuntimeAccessService::resolveAppIdentity', 'verification resolves server-side App identity');
requireContains($service, 'DylibRuntimeAccessService::resolveAccess', 'verification derives scoped card access');

// Three card scopes and highest applicable permission are explicit server-side policy.
foreach (['ACCESS_BASIC', 'ACCESS_APP_PLUS', 'ACCESS_GLOBAL_PLUS'] as $symbol) {
    requireContains($runtime, $symbol, "runtime access level {$symbol}");
}
requireContains($runtime, 'CardAccessPolicy::SCOPE_VERIFY', 'scope=2 basic card');
requireContains($runtime, 'CardAccessPolicy::SCOPE_APPS', 'scope=3 App card');
requireContains($runtime, 'CardAccessPolicy::SCOPE_SOURCE', 'scope=1 global card');
requireContains($runtime, "Db::table('fa_kami_app')", 'scope=3 uses existing fa_kami_app mapping');
requireContains($runtime, "->where('status', 'parsed')", 'only live parsed IPA identity is trusted');
requireContains($runtime, "'normal_menu'", 'normal menu permission');
requireContains($runtime, "'extra_menu'", 'extra menu permission');
requireContains($runtime, "'extra_features'", 'extra feature permission');

// App identity is parsed evidence, not client-declared app_id / BundleID alone.
requireContains($inspector, 'const LC_UUID = 0x1b', 'Mach-O LC_UUID parser');
requireContains($parser, "Db::name('ipa_app_identity')", 'parser persists runtime identity');
requireContains($runtime, "->where('bundle_id', \$bundleId)", 'BundleID is one identity component');
requireContains($runtime, "->where('executable', \$executable)", 'executable is identity component');
requireContains($runtime, "->where('macho_uuid', \$uuid)", 'Mach-O UUID is identity component');
requireContains($runtime, "Db::name('ipa_category_binding')", 'identity resolves through IPA to fa_category binding');
requireNotContains($clientM, '@"app_id"', 'client never asserts authoritative app_id');

// v1 signing remains immutable; v2 app identity fields are appended.
requireContains($service, 'public static function canonicalRequestV2', 'server v2 canonical signing method');
foreach (['protocol_version', 'app_executable', 'app_macho_uuid', 'app_version', 'app_build'] as $field) {
    requireContains($clientM, '@"' . $field . '"', "client v2 payload {$field}");
}
requireContains($clientM, 'canonicalV2UDID', 'client v2 canonical signing method');
requireContains($clientM, 'currentAppMachOUUID', 'client reads running main Mach-O UUID');

// scope=3 app_plus offline grace must keep the same identity strength as online verification.
requireContains($clientM, '[accessLevel isEqualToString:@"app_plus"]', 'app_plus offline cache has a dedicated identity gate');
requireContains($clientM, 'cachedIdentity[@"bundle_id"]', 'app_plus offline cache binds BundleID');
requireContains($clientM, 'cachedIdentity[@"executable"]', 'app_plus offline cache binds executable');
requireContains($clientM, 'cachedIdentity[@"macho_uuid"]', 'app_plus offline cache binds Mach-O UUID');
requireContains($clientM, '[cachedBundleID isEqualToString:bundleID]', 'offline app_plus requires current BundleID match');
requireContains($clientM, '[cachedExecutable isEqualToString:currentExecutable]', 'offline app_plus requires current executable match');
requireContains($clientM, '[cachedUUID caseInsensitiveCompare:currentUUID] == NSOrderedSame', 'offline app_plus requires current Mach-O UUID match');

// Update/notice data are independent additive response fields.
requireContains($service, "'app_update' => \$appUpdate", 'verification returns App update info');
requireContains($service, "'notice' => \$notice", 'verification returns remote notice');
requireContains($runtime, 'update_message', 'server-controlled update message');
requireContains($adminController, 'public function saveNotice()', 'admin notice save endpoint');
requireContains($adminController, 'public function saveRuntimeConfig()', 'admin runtime config save endpoint');
requireContains($adminController, 'if ($bootstrapUrls && !$apiEndpoints)', 'bootstrap config cannot be saved without a verification API endpoint');

// Server/domain migration uses signed discovery + multiple endpoints + Last-Known-Good cache.
requireContains($publicController, 'DylibRuntimeConfigService::bootstrap', 'public config discovery endpoint');
requireContains($configService, "'signature_alg' => 'hmac-sha256'", 'bootstrap config signing algorithm');
requireContains($clientH, 'bootstrapURLs', 'client supports multiple bootstrap URLs');
requireContains($clientM, 'cachedRuntimeConfigAllowStale', 'client Last-Known-Good runtime config');
requireContains($clientM, 'verificationEndpointsFromRuntimeConfig', 'client runtime endpoint failover');
requireContains($clientM, 'self.configuration.endpointURL', 'legacy direct endpoint remains final fallback');

// 2409 changes only the admin information architecture: preserve active controls while
// replacing the old numbered flat section order with the five-tab UX contract.
requireOrdered($view, [
    'href="#tab-overview"',
    'href="#tab-versions"',
    'href="#tab-notices"',
    'href="#tab-logs"',
    'href="#tab-advanced"',
], '2409 Dylib center tab order');
requireNotContains($view, '3. 游戏授权（BundleID）', 'retired binding section hidden from active UI');
requireNotContains($view, 'id="binding-form"', 'retired binding form removed');
requireContains($view, 'id="runtime-config-form"', 'runtime migration config UI');
requireContains($view, 'id="notice-form"', 'server-controlled notice UI');
requireNotContains($js, "url: 'dylib_center/bindings'", 'frontend no longer refreshes legacy binding table');
requireNotContains($js, "url: 'dylib_center/saveBinding'", 'frontend no longer writes legacy binding table');

// Online-update package includes program files and schema migration.
foreach (['fa_ipa_app_identity', 'fa_dylib_runtime_config', 'fa_dylib_runtime_notice'] as $table) {
    requireContains($sql, $table, "2406 schema {$table}");
}
$safeRuntimeIndex = 'KEY `idx_runtime_identity` (`bundle_id`(64),`executable`(64),`macho_uuid`(36))';
requireContains($sql, $safeRuntimeIndex, 'online migration uses legacy-safe utf8mb4 identity index prefixes');
requireContains($cleanSql, $safeRuntimeIndex, 'clean schema mirrors legacy-safe identity index prefixes');
requireNotContains($sql, '`bundle_id`(191),`executable`(191),`macho_uuid`', 'online migration must not restore oversized composite index');
requireNotContains($cleanSql, '`bundle_id`(191),`executable`(191),`macho_uuid`', 'clean schema must not restore oversized composite index');
requireContains($manifest, 'application/common/library/Ipa/DylibRuntimeAccessService.php', 'runtime access service packaged');
requireContains($manifest, 'application/common/library/Ipa/DylibRuntimeConfigService.php', 'runtime config service packaged');
requireContains($readme, 'Last-Known-Good', 'integration guide documents migration fallback');
requireContains($readme, 'global_plus > app_plus > basic > block', 'integration guide documents access priority');

echo "phase2406 dylib runtime access contract ok\n";
