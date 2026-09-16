<?php

function phase17Fail($message)
{
    fwrite(STDERR, "FAIL phase17_unlock_signing_contract_test: {$message}\n");
    exit(1);
}

function phase17Assert($condition, $message)
{
    if (!$condition) {
        phase17Fail($message);
    }
}

require_once dirname(__DIR__) . '/application/index/controller/App.php';

$class = new ReflectionClass('app\\index\\controller\\App');
$method = $class->getMethod('signedActivationPayload');
$method->setAccessible(true);
$app = $class->newInstanceWithoutConstructor();

$key = 'phase17-test-key';
$udid = '0000000000000000000000000000000000000000';
$expire = 1791212773;
$payload = $method->invoke($app, 'ok，解锁成功', $udid, $expire, $key);

phase17Assert(is_array($payload), 'signedActivationPayload must return array');
phase17Assert(isset($payload['code']) && (int)$payload['code'] === 0, 'code compatibility changed');
phase17Assert(isset($payload['msg']) && $payload['msg'] === 'ok，解锁成功', 'success message changed');
phase17Assert(isset($payload['expire']) && (int)$payload['expire'] === $expire, 'expire missing or changed');
phase17Assert(isset($payload['ts']) && ctype_digit((string)$payload['ts']), 'ts missing or invalid');
phase17Assert(isset($payload['nonce']) && preg_match('/^[a-f0-9]{16}$/', $payload['nonce']), 'nonce must be 8 random bytes encoded as hex');
phase17Assert(isset($payload['sign']) && preg_match('/^[a-f0-9]{64}$/', $payload['sign']), 'sign must be HMAC-SHA256 hex');

$signData = $udid . '|' . $payload['expire'] . '|' . $payload['ts'] . '|' . $payload['nonce'];
$expected = hash_hmac('sha256', $signData, $key);
phase17Assert(hash_equals($expected, $payload['sign']), 'HMAC signature mismatch');

$appSource = file_get_contents(dirname(__DIR__) . '/application/index/controller/App.php');
phase17Assert($appSource !== false, 'App.php missing');
phase17Assert(strpos($appSource, "SourceConfigRepository::get('unlock_sign_key'") !== false, 'unlock_sign_key is not loaded from server config');
phase17Assert(strpos($appSource, "'expire' => \$expire") !== false, 'expire response field missing');
phase17Assert(strpos($appSource, "'ts' => \$ts") !== false, 'ts response field missing');
phase17Assert(strpos($appSource, "'nonce' => \$nonce") !== false, 'nonce response field missing');
phase17Assert(strpos($appSource, "hash_hmac('sha256'") !== false, 'HMAC-SHA256 call missing');
phase17Assert(strpos($appSource, "if (intval(\$kdata['jh']))") !== false, 'used-card guard missing');
phase17Assert(strpos($appSource, "'msg' => '解锁码已使用'") !== false, 'used-card rejection missing');
phase17Assert(strpos($appSource, "trim((string)\$kdata['udid']) === \$udid") === false, 'verification-card idempotent reuse must stay disabled');

$indexSource = file_get_contents(dirname(__DIR__) . '/application/index/controller/Index.php');
phase17Assert($indexSource !== false, 'Index.php missing');
phase17Assert(strpos($indexSource, 'public function apiface()') !== false, 'apiface missing');
phase17Assert(strpos($indexSource, "->where('jh', 1)") !== false, 'apiface must require activated cards');
phase17Assert(strpos($indexSource, "->where('endtime', '>', \$now)") !== false, 'apiface must require active expiry');
phase17Assert(strpos($indexSource, 'authorizationSummaries') !== false, 'apiface authorization scope summary missing');
phase17Assert(strpos($indexSource, "'authorizations' =>") === false, 'authorizations must be appended after legacy sign payload, not inserted into legacy literal');
$signPos = strpos($indexSource, "'sign' => hash_hmac('sha256'");
$authPos = strpos($indexSource, "\$payload['authorizations'] = \$authorizations");
phase17Assert($signPos !== false && $authPos !== false && $authPos > $signPos, 'authorizations must be appended after sign');
phase17Assert(strpos($indexSource, 'CardAccessPolicy::SCOPE_SOURCE') !== false, 'source scope summary missing');
phase17Assert(strpos($indexSource, 'CardAccessPolicy::SCOPE_VERIFY') !== false, 'verification scope summary missing');
phase17Assert(strpos($indexSource, 'CardAccessPolicy::SCOPE_APPS') !== false, 'App scope summary missing');
phase17Assert(strpos($indexSource, "'code' => 1") !== false, 'apiface success code missing');
phase17Assert(strpos($indexSource, "'msg' => 'ok'") !== false, 'apiface success message missing');
phase17Assert(strpos($indexSource, "hash_hmac('sha256'") !== false, 'apiface HMAC-SHA256 missing');

$dashboard = file_get_contents(dirname(__DIR__) . '/application/admin/view/authorization/index.html');
phase17Assert($dashboard !== false, 'authorization dashboard missing');
phase17Assert(strpos($dashboard, 'href="#transfer-preview"') !== false, 'transfer preview anchor missing');
phase17Assert(strpos($dashboard, 'href="#event-preview"') !== false, 'event preview anchor missing');
phase17Assert(strpos($dashboard, 'href="#diagnostic-preview"') !== false, 'diagnostic preview anchor missing');

$integrity = file_get_contents(dirname(__DIR__) . '/application/admin/view/authorization/integrity.html');
phase17Assert($integrity !== false, 'integrity view missing');
phase17Assert(strpos($integrity, "url('integrity/index')") !== false, 'reaudit route must target Integrity::index');
phase17Assert(strpos($integrity, "url('authorization/integrity')") === false, 'stale 404 integrity route remains');

$manifest = file_get_contents(dirname(__DIR__) . '/release/online-update-files.txt');
phase17Assert($manifest !== false, 'online update manifest missing');
foreach ([
    'application/index/controller/App.php',
    'application/index/controller/Index.php',
    'application/admin/controller/Authorization.php',
    'application/admin/view/authorization/index.html',
    'application/common/library/DataIntegrityAudit.php',
    'application/admin/view/authorization/integrity.html',
] as $requiredFile) {
    phase17Assert(strpos($manifest, $requiredFile) !== false, $requiredFile . ' missing from online update manifest');
}

fwrite(STDOUT, "OK phase17_unlock_signing_contract_test fields=passed hmac=passed one_time_cards=passed scoped_apiface=passed dashboard_preview=passed integrity_route=passed manifest=passed\n");
