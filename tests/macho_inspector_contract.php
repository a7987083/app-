<?php

require __DIR__ . '/../application/common/library/Ipa/MachOInspector.php';

use app\common\library\Ipa\MachOInspector;

function put32le($v) { return pack('V', $v); }

// Minimal arm64 MH_DYLIB with one LC_ID_DYLIB command.
$name = '@rpath/TestKit.framework/TestKit';
$nameBytes = $name . "\0";
$cmdSize = 24 + strlen($nameBytes);
$cmdSize = ($cmdSize + 7) & ~7;
$cmd = put32le(0x0d) . put32le($cmdSize) . put32le(24) . put32le(0) . put32le(0) . put32le(0);
$cmd .= str_pad($nameBytes, $cmdSize - 24, "\0");
$header = put32le(0xfeedfacf) . put32le(0x0100000c) . put32le(0) . put32le(6) . put32le(1) . put32le($cmdSize) . put32le(0) . put32le(0);
$bytes = $header . $cmd;

$result = (new MachOInspector())->inspect($bytes);
if ($result['architectures'] !== ['arm64']) {
    fwrite(STDERR, 'unexpected architectures: ' . json_encode($result) . PHP_EOL);
    exit(1);
}
if ($result['install_name'] !== $name) {
    fwrite(STDERR, 'unexpected install_name: ' . json_encode($result) . PHP_EOL);
    exit(1);
}
if (!empty($result['is_fat'])) {
    fwrite(STDERR, 'expected thin Mach-O' . PHP_EOL);
    exit(1);
}

echo "Mach-O inspector contract ok\n";
