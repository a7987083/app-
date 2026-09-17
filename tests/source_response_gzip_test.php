<?php

require_once __DIR__ . '/../application/common/library/SourceResponse.php';

use app\common\library\SourceResponse;

function gzipAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL source_response_gzip_test: {$message}\n");
        exit(1);
    }
}

putenv('SOURCE_HTTP_GZIP=1');
putenv('SOURCE_HTTP_GZIP_MIN_BYTES=1024');

$body = str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', 5000);
$plain = SourceResponse::transportBody($body, 'identity');
gzipAssert($plain['gzip'] === false, 'identity client must not be gzipped');
gzipAssert($plain['body'] === $body, 'identity body must remain byte-identical');

$gzip = SourceResponse::transportBody($body, 'br, gzip');
gzipAssert($gzip['gzip'] === true, 'gzip-capable client must receive gzip transport');
gzipAssert(gzdecode($gzip['body']) === $body, 'gzip transport must decode to byte-identical application body');
gzipAssert(strlen($gzip['body']) < strlen($body), 'gzip transport must be smaller');

$disabledHeader = SourceResponse::transportBody($body, 'gzip;q=0');
gzipAssert($disabledHeader['gzip'] === false, 'gzip;q=0 must disable gzip');

gzipAssert(SourceResponse::acceptsGzip('br, gzip;q=1.0') === true, 'gzip accept parser failed');
gzipAssert(SourceResponse::acceptsGzip('gzip;q=0') === false, 'gzip q=0 parser failed');

putenv('SOURCE_HTTP_GZIP=0');
$disabled = SourceResponse::transportBody($body, 'gzip');
gzipAssert($disabled['gzip'] === false, 'SOURCE_HTTP_GZIP=0 must disable gzip');

putenv('SOURCE_HTTP_GZIP');
putenv('SOURCE_HTTP_GZIP_MIN_BYTES');
echo "source_response_gzip_test: PASS\n";
