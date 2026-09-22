<?php

// Structural contract: IPA metadata parsing must use bounded Mach-O prefix inspection for large
// binaries, while full SHA256 hashing remains restricted to small members so PHP 7/128M workers
// cannot be killed by one large executable.
$parser = file_get_contents(__DIR__ . '/../application/common/library/Ipa/IpaParserService.php');
$zip = file_get_contents(__DIR__ . '/../application/common/library/Ipa/RemoteZipReader.php');

$parserNeedles = [
    'extractPrefix($entry, $headerLimit)',
    "hash('sha256', \$bytes)",
    'binaryHeaderLimit',
    '2 * 1024 * 1024',
    'binaryHashLimit',
    '8 * 1024 * 1024',
    'architectures',
    'install_name',
    'best effort',
];
foreach ($parserNeedles as $needle) {
    if (strpos($parser, $needle) === false) {
        fwrite(STDERR, "missing parser enrichment contract: {$needle}\n");
        exit(1);
    }
}

$zipNeedles = [
    'function extractPrefix',
    'inflate_init',
    'inflate_add',
    'ZLIB_ENCODING_RAW',
];
foreach ($zipNeedles as $needle) {
    if (strpos($zip, $needle) === false) {
        fwrite(STDERR, "missing streaming ZIP prefix contract: {$needle}\n");
        exit(1);
    }
}

echo "IPA parser bounded Mach-O enrichment contract ok\n";
