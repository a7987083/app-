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
phase17Assert(strpos($appSource, "'expire' => $expire") !== false, 'expire response field missing');
phase17Assert(strpos($appSource, "'ts' => $ts") !== false, 'ts response field missing');
phase17Assert(strpos($appSource, "'nonce' => $nonce") !== false, 'nonce response field missing');
phase17Assert(strpos($appSource, "hash_hmac('sha256'") !== false, 'HMAC-SHA256 call missing');

fwrite(STDOUT, "OK phase17_unlock_signing_contract_test fields=passed hmac=passed config_key=passed\n");
