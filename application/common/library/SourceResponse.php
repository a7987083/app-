<?php

namespace app\common\library;

/**
 * Single response encoder for the public software-source protocol.
 * Application body compatibility is intentionally preserved byte-for-byte.
 * Optional gzip happens only at the HTTP transport layer and is transparently
 * decoded by clients that advertise Accept-Encoding: gzip.
 */
class SourceResponse
{
    const GZIP_MIN_BYTES = 1024;

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

    /**
     * Returns the exact bytes to write plus whether Content-Encoding: gzip is
     * required. The application payload is unchanged after HTTP decompression.
     */
    public static function transportBody($body, $acceptEncoding = null)
    {
        $body = (string)$body;
        if (!self::gzipEnabled() || !function_exists('gzencode') || strlen($body) < self::gzipMinBytes()) {
            return ['body' => $body, 'gzip' => false];
        }

        if ($acceptEncoding === null) {
            $acceptEncoding = isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? (string)$_SERVER['HTTP_ACCEPT_ENCODING'] : '';
        }
        if (!self::acceptsGzip($acceptEncoding)) {
            return ['body' => $body, 'gzip' => false];
        }

        // Do not fight PHP-level transparent compression if hosting has it on.
        $zlib = ini_get('zlib.output_compression');
        if ($zlib && strtolower((string)$zlib) !== 'off' && (string)$zlib !== '0') {
            return ['body' => $body, 'gzip' => false];
        }

        $compressed = gzencode($body, 1);
        if (!is_string($compressed) || strlen($compressed) >= strlen($body)) {
            return ['body' => $body, 'gzip' => false];
        }
        return ['body' => $compressed, 'gzip' => true];
    }

    public static function acceptsGzip($header)
    {
        foreach (explode(',', strtolower((string)$header)) as $part) {
            $segments = array_map('trim', explode(';', $part));
            $encoding = isset($segments[0]) ? $segments[0] : '';
            if ($encoding !== 'gzip' && $encoding !== '*') {
                continue;
            }
            $quality = 1.0;
            foreach (array_slice($segments, 1) as $segment) {
                if (strpos($segment, 'q=') === 0) {
                    $quality = (float)substr($segment, 2);
                }
            }
            if ($quality > 0) {
                return true;
            }
        }
        return false;
    }

    public static function send($body)
    {
        // If any previous code has already flushed headers/body, compression
        // would be unsafe because Content-Encoding could no longer be added.
        if (headers_sent()) {
            echo $body;
            die;
        }

        $transport = self::transportBody($body);
        if ($transport['gzip']) {
            header('Content-Encoding: gzip');
            header('Vary: Accept-Encoding');
        }
        echo $transport['body'];
        die;
    }

    protected static function gzipEnabled()
    {
        $value = getenv('SOURCE_HTTP_GZIP');
        if ($value === false || trim((string)$value) === '') {
            return true;
        }
        return !in_array(strtolower(trim((string)$value)), ['0', 'false', 'off', 'no'], true);
    }

    protected static function gzipMinBytes()
    {
        $value = getenv('SOURCE_HTTP_GZIP_MIN_BYTES');
        if ($value === false || trim((string)$value) === '') {
            return self::GZIP_MIN_BYTES;
        }
        $bytes = (int)$value;
        return $bytes > 0 ? $bytes : self::GZIP_MIN_BYTES;
    }
}
