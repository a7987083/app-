<?php

namespace app\common\library;

/**
 * Single response encoder for the public software-source protocol.
 * Body compatibility is intentionally preserved byte-for-byte.
 */
class SourceResponse
{
    public static function plainBody(array $payload, $jsonFlags = 320, $replaceMarkers = true)
    {
        $payload = AppStorePayload::withoutRuntimeFields($payload);
        $json = json_encode($payload, $jsonFlags);
        return $replaceMarkers ? str_replace('@@@', '\\n', $json) : $json;
    }

    public static function encryptedBody($appType, $encryptedPayload, $replaceMarkers = true)
    {
        $key = $appType === 'appstore_v2' ? 'appstore_v2' : 'appstore';
        $json = json_encode([$key => $encryptedPayload]);
        return $replaceMarkers ? str_replace('@@@', '\\n', $json) : $json;
    }

    public static function send($body, $status = 200)
    {
        if (!headers_sent()) {
            header('Content-Type: application/json;charset=utf-8');
            http_response_code((int)$status);
        }
        echo $body;
        die;
    }
}
