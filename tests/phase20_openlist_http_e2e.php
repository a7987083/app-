<?php

$root = dirname(__DIR__);
require_once $root . '/application/common/library/IpaRemoteFile.php';
require_once $root . '/application/common/library/IpaOpenListClient.php';

use app\common\library\IpaOpenListClient;

function phase20HttpAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL phase20_openlist_http_e2e: {$message}\n");
        exit(1);
    }
}

$base = getenv('PHASE20_OPENLIST_BASE');
$stateFile = getenv('PHASE20_OPENLIST_STATE');
phase20HttpAssert(is_string($base) && $base !== '', 'mock base missing');
phase20HttpAssert(is_string($stateFile) && is_file($stateFile), 'state file missing');

$client = new IpaOpenListClient($base, '/api', 'phase20-test-token', 5, 0);
$health = $client->health('/');
phase20HttpAssert(!empty($health['ok']), 'health failed');

$scan = $client->listIpaFiles('/', 'https://cdn.example/{path}', false, true, 20);
phase20HttpAssert(isset($scan['files']) && count($scan['files']) === 2, 'recursive IPA discovery count');
$paths = [];
foreach ($scan['files'] as $file) $paths[] = $file['remote_path'];
sort($paths);
phase20HttpAssert($paths === ['/games/AppOne.ipa','/games/nested/AppTwo.IPA'], 'discovered IPA paths');
phase20HttpAssert($scan['files'][0]['source_key'] === 'openlist', 'source key');

$file = $client->getFile('/games/AppOne.ipa');
phase20HttpAssert(isset($file['size']) && (int)$file['size'] === 111, 'get file metadata');

phase20HttpAssert($client->rename('/games/AppOne.ipa', 'AppOne-renamed.ipa') === true, 'rename request');
$afterRename = json_decode(file_get_contents($stateFile), true);
phase20HttpAssert(isset($afterRename['files']['/games/AppOne-renamed.ipa']), 'rename state');
phase20HttpAssert(!isset($afterRename['files']['/games/AppOne.ipa']), 'rename old path removed');

phase20HttpAssert($client->move('/games', '/archive', ['AppOne-renamed.ipa']) === true, 'move request');
$afterMove = json_decode(file_get_contents($stateFile), true);
phase20HttpAssert(isset($afterMove['files']['/archive/AppOne-renamed.ipa']), 'move target state');
phase20HttpAssert(!isset($afterMove['files']['/games/AppOne-renamed.ipa']), 'move source removed');

$requests = isset($afterMove['requests']) ? $afterMove['requests'] : [];
phase20HttpAssert(count($requests) >= 5, 'HTTP requests recorded');
foreach ($requests as $request) {
    phase20HttpAssert(isset($request['authorization']) && $request['authorization'] === 'phase20-test-token', 'Authorization header propagated');
}

$thrown = false;
try { $client->rename('/archive/AppOne-renamed.ipa', '../bad.ipa'); } catch (RuntimeException $e) { $thrown = true; }
phase20HttpAssert($thrown, 'rename traversal rejected client-side');

fwrite(STDOUT, "OK phase20_openlist_http_e2e discovery=passed get=passed rename=passed move=passed auth=passed\n");
