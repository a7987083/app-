<?php

require __DIR__ . '/../application/common/library/Ipa/MachOInspector.php';

use app\common\library\Ipa\MachOInspector;

function be32($v) { return pack('N', $v); }

// FAT header with arm64 + x86_64 architecture table only. Slice bodies are intentionally omitted;
// architecture discovery must still succeed from the FAT table.
$bytes = be32(0xcafebabe) . be32(2);
$bytes .= be32(0x0100000c) . be32(0) . be32(0x1000) . be32(0x2000) . be32(12);
$bytes .= be32(0x01000007) . be32(3) . be32(0x3000) . be32(0x2000) . be32(12);

$result = (new MachOInspector())->inspect($bytes);
$arch = $result['architectures'];
sort($arch);
if ($arch !== ['arm64', 'x86_64']) {
    fwrite(STDERR, 'unexpected FAT architectures: ' . json_encode($result) . PHP_EOL);
    exit(1);
}
if (empty($result['is_fat'])) {
    fwrite(STDERR, 'expected FAT Mach-O' . PHP_EOL);
    exit(1);
}

echo "FAT Mach-O inspector contract ok\n";
