<?php
require_once dirname(__DIR__) . '/application/common/library/SourceAppRecord.php';
require_once dirname(__DIR__) . '/application/common/library/AppStorePayload.php';
require_once dirname(__DIR__) . '/application/common/library/SourceAnnouncementTemplate.php';
require_once dirname(__DIR__) . '/application/common/library/SourceResponse.php';

use app\common\library\SourceAnnouncementTemplate;
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

SourceAnnouncementTemplate::begin(['刷新时间' => '2026-09-18 10:20:30']);
$dynamic = [
    'name' => '源',
    'message' => '刷新：[刷新时间]',
    'UDID' => 'U',
    'Time' => 'T',
    'apps' => [],
];
$dynamicPlain = SourceResponse::plainBody($dynamic, 320, true);
sourceResponseAssert(strpos($dynamicPlain, '2026-09-18 10:20:30') !== false, 'dynamic announcement rendered after static body build');
sourceResponseAssert(strpos($dynamicPlain, '[刷新时间]') === false, 'dynamic announcement token removed');
sourceResponseAssert(strpos($dynamicPlain, SourceAnnouncementTemplate::SENTINEL) === false, 'dynamic announcement sentinel must never leak');
sourceResponseAssert(strpos($dynamicPlain, '"UDID"') === false, 'dynamic plain body still strips UDID');
SourceAnnouncementTemplate::reset();

echo "OK source_response_test\n";
