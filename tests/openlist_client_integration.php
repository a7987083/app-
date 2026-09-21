<?php

require __DIR__ . '/../application/common/library/Ipa/OpenListClient.php';

use app\common\library\Ipa\OpenListClient;

function check($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$client = new OpenListClient('http://127.0.0.1:18081', 'test-openlist-token', 5);

$page1 = $client->listDirectory('/IPA', 1, 2, false);
check(isset($page1['total']) && (int)$page1['total'] === 3, 'list total mismatch');
check(isset($page1['content']) && count($page1['content']) === 2, 'page1 content mismatch');
check($page1['content'][0]['name'] === 'A.ipa', 'page1 first file mismatch');
check(!empty($page1['content'][1]['is_dir']), 'directory flag mismatch');

$page2 = $client->listDirectory('//IPA//', 2, 2, false);
check(count($page2['content']) === 1, 'page2 content mismatch');
check($page2['content'][0]['name'] === 'B.ipa', 'page2 file mismatch');

$file = $client->getFile('/IPA/A.ipa');
check((int)$file['size'] === 3145728, 'getFile size mismatch');
check(strpos($file['raw_url'], 'A.ipa') !== false, 'getFile raw_url mismatch');

echo "OpenList client integration OK\n";
