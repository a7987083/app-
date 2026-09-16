<?php

function apifaceSigningFail($message)
{
    fwrite(STDERR, "FAIL phase17_apiface_signing_contract_test: {$message}\n");
    exit(1);
}

function apifaceSigningAssert($condition, $message)
{
    if (!$condition) {
        apifaceSigningFail($message);
    }
}

$root = dirname(__DIR__);
$indexFile = $root . '/application/index/controller/Index.php';
$source = file_get_contents($indexFile);
apifaceSigningAssert($source !== false, 'Index.php missing');

$start = strpos($source, 'public function apiface()');
$end = strpos($source, 'public function unbind()', $start === false ? 0 : $start);
apifaceSigningAssert($start !== false && $end !== false && $end > $start, 'apiface method not found');
$apiface = substr($source, $start, $end - $start);

apifaceSigningAssert(strpos($apiface, 'signedApifacePayload') !== false, 'apiface does not build signed response');
apifaceSigningAssert(strpos($apiface, "echo json_encode(['msg' => 'ok']") === false, 'legacy msg-only success response still present');
apifaceSigningAssert(strpos($source, "SourceConfigRepository::get('unlock_sign_key'") !== false, 'unlock_sign_key not loaded from server config');
apifaceSigningAssert(strpos($source, "'expire' => \$expire") !== false, 'expire field missing');
apifaceSigningAssert(strpos($source, "'ts' => \$ts") !== false, 'ts field missing');
apifaceSigningAssert(strpos($source, "'nonce' => \$nonce") !== false, 'nonce field missing');
apifaceSigningAssert(strpos($source, "hash_hmac('sha256'") !== false, 'HMAC-SHA256 missing');
apifaceSigningAssert(strpos($source, "\$udid . '|' . \$expire . '|' . \$ts . '|' . \$nonce") !== false, 'signature input format changed');

$manifest = file_get_contents($root . '/release/online-update-files.txt');
apifaceSigningAssert($manifest !== false, 'online update manifest missing');
apifaceSigningAssert(strpos($manifest, "application/index/controller/Index.php\n") !== false, 'Index.php missing from online update package');

fwrite(STDOUT, "OK phase17_apiface_signing_contract_test signed_fields=passed manifest=passed\n");
