<?php
require_once dirname(__DIR__) . '/application/common/library/SourceHttpClient.php';

use app\common\library\SourceHttpClient;

function httpAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL source_http_client_contract_test: {$message}\n");
        exit(1);
    }
}

putenv('SOURCE_HTTP_VERIFY_TLS');
httpAssert(SourceHttpClient::envBool('SOURCE_HTTP_VERIFY_TLS', true) === true, 'TLS verify defaults on');
putenv('SOURCE_HTTP_VERIFY_TLS=0');
httpAssert(SourceHttpClient::envBool('SOURCE_HTTP_VERIFY_TLS', true) === false, 'emergency TLS override off');
putenv('SOURCE_HTTP_VERIFY_TLS=true');
httpAssert(SourceHttpClient::envBool('SOURCE_HTTP_VERIFY_TLS', false) === true, 'TLS explicit on');
putenv('SOURCE_HTTP_VERIFY_TLS');

$source = file_get_contents(dirname(__DIR__) . '/application/common/library/SourceHttpClient.php');
foreach (array('CURLOPT_CONNECTTIMEOUT', 'CURLOPT_TIMEOUT', 'CURLOPT_SSL_VERIFYPEER', 'CURLOPT_SSL_VERIFYHOST', 'error_log', 'CURLINFO_TOTAL_TIME', "'elapsed_ms'") as $needle) {
    httpAssert(strpos($source, $needle) !== false, 'missing HTTP hardening: ' . $needle);
}

echo "OK source_http_client_contract_test\n";
