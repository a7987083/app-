<?php

require_once __DIR__ . '/../application/common/library/SourceEncryptionProvider.php';

use app\common\library\SourceEncryptionProvider;

function assertSameValue($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) . "\nActual:   " . var_export($actual, true) . "\n");
        exit(1);
    }
}

if (!SourceEncryptionProvider::localAvailable()) {
    fwrite(STDERR, "DES-CBC unavailable in OpenSSL runtime\n");
    exit(2);
}

$vectors = [
    ['{}', 'EyfnkNM8C30='],
    ['{"name":"zonoe"}', 'TlWK5kcleCuFp91nVFDF7XNFYWwrxPLL'],
    ['{"name":"测试","apps":[]}', 'TlWK5kcleCtbjZP0lExK1QBpTczirFbC26w3BCYUQkY='],
];

foreach ($vectors as $index => $vector) {
    assertSameValue(
        $vector[1],
        SourceEncryptionProvider::encryptJson($vector[0]),
        'Golden vector #' . ($index + 1) . ' mismatch'
    );

    assertSameValue(
        $vector[1],
        SourceEncryptionProvider::encryptEncodedContent(base64_encode($vector[0])),
        'Encoded-content vector #' . ($index + 1) . ' mismatch'
    );
}

$invalidBase64Rejected = false;
try {
    SourceEncryptionProvider::encryptEncodedContent('%%%not-base64%%%');
} catch (InvalidArgumentException $e) {
    $invalidBase64Rejected = true;
}
assertSameValue(true, $invalidBase64Rejected, 'Invalid Base64 must be rejected');

echo "source_encryption_provider_test: PASS\n";
