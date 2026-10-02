<?php

require_once __DIR__ . '/../application/common/library/Ipa/DylibDeviceAuthService.php';

use app\common\library\Ipa\DylibDeviceAuthService;

$payload = [
    'auth_proof' => 'proof-v1.test-signature',
    'udid' => '00008120-001A55A93AF0201E',
    'bundle_id' => 'com.example.game',
    'dylib_key' => 'zonoe.main',
    'dylib_version' => '1.2.3',
    'dylib_build' => '45',
    'dylib_sha256' => str_repeat('A', 64),
    'app_executable' => 'ExampleGame',
    'app_macho_uuid' => '12345678-90ab-cdef-1234-567890abcdef',
    'app_version' => '9.8.7',
    'app_build' => '987',
];

$challengeId = '0123456789abcdef0123456789abcdef';
$challenge = 'challenge-value';

$canonical = DylibDeviceAuthService::canonicalProof($payload, $challengeId, $challenge);
$expectedCanonical = implode("\n", [
    'zonoe-dylib-auth-v3',
    $challengeId,
    $challenge,
    'proof-v1.test-signature',
    '00008120-001A55A93AF0201E',
    'com.example.game',
    'zonoe.main',
    '1.2.3',
    '45',
    str_repeat('a', 64),
    'ExampleGame',
    '12345678-90AB-CDEF-1234-567890ABCDEF',
    '9.8.7',
    '987',
]);

if ($canonical !== $expectedCanonical) {
    fwrite(STDERR, "v3 canonical proof mismatch\n");
    exit(1);
}

$withoutProof = $payload;
unset($withoutProof['auth_proof']);
$canonicalWithoutProof = DylibDeviceAuthService::canonicalProof($withoutProof, $challengeId, $challenge);
$expectedWithoutProof = implode("\n", [
    'zonoe-dylib-auth-v3',
    $challengeId,
    $challenge,
    '00008120-001A55A93AF0201E',
    'com.example.game',
    'zonoe.main',
    '1.2.3',
    '45',
    str_repeat('a', 64),
    'ExampleGame',
    '12345678-90AB-CDEF-1234-567890ABCDEF',
    '9.8.7',
    '987',
]);

if ($canonicalWithoutProof !== $expectedWithoutProof) {
    fwrite(STDERR, "v3 compatibility canonical proof mismatch\n");
    exit(1);
}

$objc = file_get_contents(__DIR__ . '/../clients/ios/ZONDylibVerify/ZONVerifyClient.m');
foreach ([
    'canonicalProof',
    'challenge_id',
    'challenge',
    'auth_proof',
    'device_public_key',
    'device_signature',
    'protocol_version',
    'app_executable',
    'app_macho_uuid'
] as $needle) {
    if (strpos($objc, $needle) === false) {
        fwrite(STDERR, "Objective-C v3 client contract missing {$needle}\n");
        exit(1);
    }
}

require __DIR__ . '/phase2405_dylib_lifecycle_contract_test.php';
require __DIR__ . '/phase2406_dylib_runtime_access_contract_test.php';

echo "dylib signing contract v3 ok\n";
