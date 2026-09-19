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

function decodeV2CodecForTest($text)
{
    $alphabet = SourceEncryptionProvider::V2_ALPHABET;
    $lookup = [];
    for ($i = 0; $i < strlen($alphabet); $i++) {
        $lookup[$alphabet[$i]] = $i;
    }

    $buffer = 0;
    $bitCount = 0;
    $output = '';
    for ($i = 0; $i < strlen($text); $i++) {
        if (!isset($lookup[$text[$i]])) {
            throw new RuntimeException('invalid V2 codec character');
        }
        $value = $lookup[$text[$i]];
        $width = ($value === 30 || $value === 31) ? 5 : 6;
        $buffer |= ($value & ((1 << $width) - 1)) << $bitCount;
        $bitCount += $width;
        while ($bitCount >= 8) {
            $output .= chr($buffer & 0xff);
            $buffer >>= 8;
            $bitCount -= 8;
        }
    }
    if ($bitCount > 0) {
        $output .= chr($buffer & 0xff);
    }
    return $output;
}

if (!SourceEncryptionProvider::localAvailable()) {
    fwrite(STDERR, "Required curl/OpenSSL functions unavailable\n");
    exit(2);
}

$json = '{"name":"zonoe-test","apps":[]}';
$key = 'UthbkJctpzDlLle';
assertSameValue(
    'pCY/q/43k7//OV7Z68r92fP7Z9u/9O0bIFGroc7Dgg==',
    SourceEncryptionProvider::encryptLegacyJson($json, $key),
    'Legacy RC4 vector mismatch'
);

$v2 = SourceEncryptionProvider::encryptV2Json($json, $key);
$raw = decodeV2CodecForTest($v2);
$header = unpack('Vmagic/VrsaLength', substr($raw, 0, 8));
assertSameValue(SourceEncryptionProvider::V2_MAGIC, $header['magic'], 'V2 magic mismatch');
assertSameValue(256, $header['rsaLength'], 'V2 RSA blob must be 2048-bit');
$payloadLength = unpack('Vlength', substr($raw, 8 + $header['rsaLength'], 4));
assertSameValue(strlen($json), $payloadLength['length'], 'V2 payload length mismatch');
assertSameValue(12 + $header['rsaLength'] + strlen($json), strlen($raw), 'V2 container length mismatch');

$reflection = new ReflectionClass(SourceEncryptionProvider::class);
$rc4 = $reflection->getMethod('rc4Reference');
$rc4->setAccessible(true);
$parts = $reflection->getMethod('encodeV2Parts');
$parts->setAccessible(true);
$fused = $reflection->getMethod('encodeV2EncryptedJson');
$fused->setAccessible(true);
$fusedKey = 'AbCdEf012345678';
foreach ([0,1,2,5,6,7,31,32,63,64,65,127,128,129,255,256,1024,65536,1048576] as $size) {
    $source = '';
    $seed = '{"name":"zonoe-v2-fused","value":12345}';
    while (strlen($source) < $size) {
        $source .= $seed;
    }
    $source = substr($source, 0, $size);
    $fixedHeader = pack('V', SourceEncryptionProvider::V2_MAGIC)
        . pack('V', 7)
        . 'RSADEMO'
        . pack('V', strlen($source));
    $referencePayload = $rc4->invoke(null, $source, $fusedKey);
    $reference = $parts->invoke(null, [$fixedHeader, $referencePayload]);
    $optimized = $fused->invoke(null, $fixedHeader, $source, $fusedKey);
    assertSameValue($reference, $optimized, 'V2 fused encoder mismatch at size ' . $size);
}

$invalidBase64Rejected = false;
try {
    SourceEncryptionProvider::encryptEncodedContent('%%%not-base64%%%', 'appstore');
} catch (InvalidArgumentException $e) {
    $invalidBase64Rejected = true;
}
assertSameValue(true, $invalidBase64Rejected, 'Invalid Base64 must be rejected');

echo "source_encryption_provider_test: PASS\n";
