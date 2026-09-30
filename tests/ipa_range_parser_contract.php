<?php

require __DIR__ . '/../application/common/library/Ipa/HttpRangeClient.php';
require __DIR__ . '/../application/common/library/Ipa/RemoteZipReader.php';
require __DIR__ . '/../application/common/library/Ipa/PlistDecoder.php';

use app\common\library\Ipa\HttpRangeClient;
use app\common\library\Ipa\RemoteZipReader;
use app\common\library\Ipa\PlistDecoder;

class MemoryRangeClient extends HttpRangeClient
{
    private $bytes;
    public $requestedBytes = 0;
    public $maxRequestBytes = 0;
    public function __construct($bytes) { $this->bytes = $bytes; }
    public function size() { return strlen($this->bytes); }
    public function getRange($start, $length) {
        $this->requestedBytes += (int)$length;
        $this->maxRequestBytes = max($this->maxRequestBytes, (int)$length);
        return substr($this->bytes, $start, $length);
    }
}

class CachedMemoryRangeClient extends HttpRangeClient
{
    private $bytes;
    public $fetchCount = 0;

    public function __construct($bytes, $budgetBytes = 16777216, $blockBytes = 65536)
    {
        $this->bytes = $bytes;
        parent::__construct(
            'http://range-contract.invalid/fixture.ipa',
            [],
            3,
            strlen($bytes),
            67108864,
            $budgetBytes,
            $blockBytes
        );
    }

    protected function fetchRange($start, $length)
    {
        $this->fetchCount++;
        return substr($this->bytes, $start, $length);
    }
}

function buildZipOne($name, $data)
{
    $compressed = gzdeflate($data);
    $crc = crc32($data);
    $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 8, 0, 0, $crc, strlen($compressed), strlen($data), strlen($name), 0) . $name . $compressed;
    $central = pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 8, 0, 0, $crc, strlen($compressed), strlen($data), strlen($name), 0, 0, 0, 0, 0, 0) . $name;
    $eocd = pack('VvvvvVVv', 0x06054b50, 0, 0, 1, 1, strlen($central), strlen($local), 0);
    return $local . $central . $eocd;
}

$xml = '<?xml version="1.0" encoding="UTF-8"?><plist version="1.0"><dict><key>CFBundleIdentifier</key><string>com.example.game</string><key>CFBundleShortVersionString</key><string>1.2.3</string><key>CFBundleExecutable</key><string>Game</string></dict></plist>';
$zipBytes = buildZipOne('Payload/Game.app/Info.plist', $xml);
$zip = new RemoteZipReader(new MemoryRangeClient($zipBytes));
$entry = $zip->findFirst('#^Payload/[^/]+\\.app/Info\\.plist$#');
if (!$entry) { fwrite(STDERR, "Info.plist entry not found\n"); exit(1); }
$decoded = (new PlistDecoder())->decode($zip->extract($entry));
if ($decoded['CFBundleIdentifier'] !== 'com.example.game' || $decoded['CFBundleShortVersionString'] !== '1.2.3') {
    fwrite(STDERR, "XML plist contract failed\n"); exit(1);
}

// 2026092427: overlapping logical reads in the same 64 KiB block must issue
// exactly one physical range fetch.
$cacheFixture = str_repeat('R', 256 * 1024);
$cachedClient = new CachedMemoryRangeClient($cacheFixture, 1024 * 1024, 65536);
if ($cachedClient->getRange(1024, 4096) !== substr($cacheFixture, 1024, 4096)) {
    fwrite(STDERR, "Range cache first slice mismatch\n"); exit(1);
}
if ($cachedClient->getRange(2048, 2048) !== substr($cacheFixture, 2048, 2048)) {
    fwrite(STDERR, "Range cache overlapping slice mismatch\n"); exit(1);
}
$cacheStats = $cachedClient->stats();
if ($cachedClient->fetchCount !== 1 || $cacheStats['network_requests'] !== 1 || $cacheStats['network_bytes'] !== 65536) {
    fwrite(STDERR, "Range block cache did not collapse overlapping reads\n"); exit(1);
}

// The client must stop before physical Range traffic exceeds the per-IPA budget.
$budgetFixture = str_repeat('B', 2 * 1024 * 1024);
$budgetClient = new CachedMemoryRangeClient($budgetFixture, 1024 * 1024, 65536);
$budgetRejected = false;
try {
    for ($offset = 0; $offset <= 1024 * 1024; $offset += 65536) {
        $budgetClient->getRange($offset, 1);
    }
} catch (RuntimeException $e) {
    $budgetRejected = strpos($e->getMessage(), 'budget exceeded') !== false;
}
if (!$budgetRejected) {
    fwrite(STDERR, "Range cumulative budget was not enforced\n"); exit(1);
}
$budgetStats = $budgetClient->stats();
if ($budgetStats['network_bytes'] > 1024 * 1024) {
    fwrite(STDERR, "Range cumulative budget over-read fixture\n"); exit(1);
}
unset($cacheFixture, $budgetFixture);

// Exercise the production OOM fix against a genuinely large deflated ZIP member. The parser
// must materialize only the requested 2 MiB prefix, not the 32 MiB uncompressed member.
$large = '';
for ($i = 0; $i < 32768; $i++) {
    // Deterministic but deliberately poorly compressible enough to require multiple input chunks.
    $large .= hash('sha256', 'ipa-prefix-fixture-' . $i, true) . str_repeat(chr($i & 0xff), 992);
}
$large = substr($large, 0, 32 * 1024 * 1024);
$largeZipBytes = buildZipOne('Payload/Game.app/Game', $large);
$memory = new MemoryRangeClient($largeZipBytes);
$largeZip = new RemoteZipReader($memory);
$largeEntry = $largeZip->findFirst('#^Payload/Game\\.app/Game$#');
if (!$largeEntry) { fwrite(STDERR, "Large binary entry not found\n"); exit(1); }
$prefixLimit = 2 * 1024 * 1024;
$prefix = $largeZip->extractPrefix($largeEntry, $prefixLimit, 128 * 1024);
if (strlen($prefix) !== $prefixLimit || $prefix !== substr($large, 0, $prefixLimit)) {
    fwrite(STDERR, "Bounded deflate prefix extraction failed\n"); exit(1);
}
if ($memory->maxRequestBytes > 2 * 1024 * 1024) {
    fwrite(STDERR, "Bounded prefix used an oversized HTTP range\n"); exit(1);
}
unset($large, $largeZipBytes, $prefix);

$binaryFixture = '/tmp/ipa_binary_plist_fixture.bin';
if (is_file($binaryFixture)) {
    $binary = (new PlistDecoder())->decode(file_get_contents($binaryFixture));
    if (!isset($binary['CFBundleIdentifier']) || $binary['CFBundleIdentifier'] !== 'com.example.binary') {
        fwrite(STDERR, "Binary plist contract failed\n"); exit(1);
    }
}

echo "ipa range parser contracts passed\n";
