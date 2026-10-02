<?php

// Current Parser V2 contract: IPA metadata parsing is intentionally limited to
// Payload/*.app/Info.plist. Mach-O enrichment/hashing from the retired parser
// must not re-enter the synchronous metadata parse path.
$parser = file_get_contents(__DIR__ . '/../application/common/library/Ipa/IpaParserV2Service.php');
$zip = file_get_contents(__DIR__ . '/../application/common/library/Ipa/RemoteZipReader.php');

if ($parser === false || $zip === false) {
    fwrite(STDERR, "unable to load parser v2 contract sources\n");
    exit(1);
}

$required = [
    'const PLIST_MAX_BYTES = 4194304',
    'const RANGE_MAX_BYTES = 16777216',
    "findFirst('#^Payload/[^/]+\\\\.app/Info\\\\.plist$#i')",
    'CFBundleIdentifier',
    'CFBundleExecutable',
    "'parser' => 'v2-fast-plist'",
    "Db::name('ipa_binary')->where('asset_id'",
    "Db::name('ipa_app_identity')->where('asset_id'",
    "Db::name('ipa_compare_result')->where('asset_id'",
];
foreach ($required as $needle) {
    if (strpos($parser, $needle) === false) {
        fwrite(STDERR, "missing parser v2 contract: {$needle}\n");
        exit(1);
    }
}

$forbidden = [
    'MachOInspector',
    'extractPrefix($entry',
    "hash('sha256',",
    'binaryHeaderLimit',
    'binaryHashLimit',
];
foreach ($forbidden as $needle) {
    if (strpos($parser, $needle) !== false) {
        fwrite(STDERR, "retired parser enrichment leaked into parser v2: {$needle}\n");
        exit(1);
    }
}

// RemoteZipReader may retain bounded prefix extraction as a reusable primitive;
// Parser V2 simply must not call it during metadata parsing.
foreach (['function extractPrefix', 'inflate_init', 'inflate_add', 'ZLIB_ENCODING_RAW'] as $needle) {
    if (strpos($zip, $needle) === false) {
        fwrite(STDERR, "missing streaming ZIP primitive: {$needle}\n");
        exit(1);
    }
}

echo "IPA parser v2 metadata-only contract ok\n";
