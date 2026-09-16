<?php

use app\common\library\SourceEncryptionProvider;

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../application/common/library/SourceEncryptionProvider.php';

$content = isset($_POST['content']) ? (string)$_POST['content'] : '';
if ($content === '') {
    http_response_code(400);
    echo 'Missing content parameter';
    exit;
}

try {
    echo SourceEncryptionProvider::encryptEncodedContent($content, 'appstore');
} catch (\Exception $e) {
    http_response_code(500);
    error_log('[public/api.php] ' . $e->getMessage());
    echo 'Encryption failed';
}
