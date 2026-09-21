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
    public function __construct($bytes) { $this->bytes = $bytes; }
    public function size() { return strlen($this->bytes); }
    public function getRange($start, $length) { return substr($this->bytes, $start, $length); }
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

$binaryFixture = '/tmp/ipa_binary_plist_fixture.bin';
if (is_file($binaryFixture)) {
    $binary = (new PlistDecoder())->decode(file_get_contents($binaryFixture));
    if (!isset($binary['CFBundleIdentifier']) || $binary['CFBundleIdentifier'] !== 'com.example.binary') {
        fwrite(STDERR, "Binary plist contract failed\n"); exit(1);
    }
}

echo "ipa range parser contracts passed\n";
