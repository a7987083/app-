<?php

require_once __DIR__ . '/../application/common/library/Ipa/DylibVerificationService.php';

use app\common\library\Ipa\DylibVerificationService;

$canonical = DylibVerificationService::canonicalRequest(
    '00008120-001A55A93AF0201E',
    'com.example.game',
    'zonoe.main',
    '1.2.3',
    '45',
    str_repeat('a', 64),
    1780000000,
    '0123456789abcdef0123456789abcdef'
);

$expectedCanonical = implode("\n", [
    '00008120-001A55A93AF0201E',
    'com.example.game',
    'zonoe.main',
    '1.2.3',
    '45',
    str_repeat('a', 64),
    '1780000000',
    '0123456789abcdef0123456789abcdef',
]);

if ($canonical !== $expectedCanonical) {
    fwrite(STDERR, "canonical request mismatch\n");
    exit(1);
}

$secret = '0123456789abcdef0123456789abcdef';
$signature = hash_hmac('sha256', $canonical, $secret);
$expectedSignature = '817aed89d0ed0ea3edfe5b25679fd0b279d29dc8ba0ff9e3f9d57935833e0e93';
if (!hash_equals($expectedSignature, $signature)) {
    fwrite(STDERR, "signature vector mismatch: {$signature}\n");
    exit(1);
}

$objc = file_get_contents(__DIR__ . '/../clients/ios/ZONDylibVerify/ZONVerifyClient.m');
foreach (['bundleID', 'dylibKey', 'dylibVersion', 'dylibBuild', 'dylib_sha256', 'signature'] as $needle) {
    if (strpos($objc, $needle) === false) {
        fwrite(STDERR, "Objective-C client contract missing {$needle}\n");
        exit(1);
    }
}

require __DIR__ . '/phase2405_dylib_lifecycle_contract_test.php';

echo "dylib signing contract ok\n";
