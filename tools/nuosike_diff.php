<?php

require_once __DIR__ . '/../application/common/library/SourceEncryptionProvider.php';

use app\common\library\SourceEncryptionProvider;

/**
 * Manual, finite differential probe against the legacy Nuosike endpoint.
 * This tool is intentionally NOT run by normal CI. It uses three harmless
 * synthetic payloads and exits non-zero on transport or ciphertext mismatch.
 *
 * Usage:
 *   php tools/nuosike_diff.php
 *   NUOSIKE_DIFF_URL=https://api.nuosike.com/api.php php tools/nuosike_diff.php
 */

$url = getenv('NUOSIKE_DIFF_URL');
if ($url === false || trim($url) === '') {
    $url = 'https://api.nuosike.com/api.php';
}

if (!function_exists('curl_init')) {
    fwrite(STDERR, "curl extension is required\n");
    exit(2);
}

$vectors = [
    '{}',
    '{"name":"zonoe"}',
    '{"name":"测试","apps":[]}',
];

function postContent($url, $content)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['content' => $content], '', '&'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $errno !== 0 || $status < 200 || $status >= 300) {
        throw new RuntimeException('Nuosike request failed: http=' . $status . ' errno=' . $errno . ' error=' . $error);
    }

    return trim((string)$body);
}

if (!SourceEncryptionProvider::localAvailable()) {
    fwrite(STDERR, "Local DES-CBC provider unavailable\n");
    exit(3);
}

$failed = 0;
foreach ($vectors as $index => $json) {
    $content = base64_encode($json);
    try {
        $remote = postContent($url, $content);
        $local = SourceEncryptionProvider::encryptEncodedContent($content);
    } catch (Exception $e) {
        fwrite(STDERR, 'vector #' . ($index + 1) . ': ERROR ' . $e->getMessage() . "\n");
        $failed++;
        continue;
    }

    $same = hash_equals($local, $remote);
    echo 'vector #' . ($index + 1)
        . ': ' . ($same ? 'MATCH' : 'MISMATCH')
        . ' local_sha256=' . hash('sha256', $local)
        . ' remote_sha256=' . hash('sha256', $remote)
        . PHP_EOL;

    if (!$same) {
        $failed++;
    }
}

if ($failed > 0) {
    fwrite(STDERR, 'nuosike_diff: FAIL (' . $failed . " vector(s))\n");
    exit(1);
}

echo "nuosike_diff: PASS\n";
