<?php
require_once dirname(__DIR__) . '/application/common/library/SourceAppRecord.php';
require_once dirname(__DIR__) . '/application/common/library/AppStorePayload.php';
require_once dirname(__DIR__) . '/application/common/library/SourceResponse.php';

use app\common\library\SourceResponse;

function sourceResponseAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL source_response_test: {$message}\n");
        exit(1);
    }
}

$payload = [
    'name' => '源',
    'UDID' => 'U',
    'Time' => 'T',
    'apps' => [['versionDescription' => 'a@@@b']],
];
$plain = SourceResponse::plainBody($payload, 320, true);
sourceResponseAssert(strpos($plain, '"UDID"') === false, 'plain body strips UDID');
sourceResponseAssert(strpos($plain, '"Time"') === false, 'plain body strips Time');
sourceResponseAssert(strpos($plain, 'a\\nb') !== false, 'plain body preserves newline marker');
sourceResponseAssert(SourceResponse::encryptedBody('appstore', 'abc', true) === '{"appstore":"abc"}', 'appstore wrapper');
sourceResponseAssert(SourceResponse::encryptedBody('appstore_v2', 'xyz', true) === '{"appstore_v2":"xyz"}', 'appstore_v2 wrapper');
sourceResponseAssert(SourceResponse::encryptedBody('other', false, true) === '{"appstore":false}', 'legacy transport failure envelope');

echo "OK source_response_test\n";
