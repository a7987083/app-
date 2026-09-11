<?php

namespace app\common\library;

/**
 * HTTP client for source-side upstream services.
 *
 * TLS verification is enabled by default. For emergency compatibility only,
 * SOURCE_HTTP_VERIFY_TLS=0 can temporarily restore the legacy behavior.
 */
class SourceHttpClient
{
    const DEFAULT_CONNECT_TIMEOUT = 5;
    const DEFAULT_TIMEOUT = 20;

    public static function postForm($url, array $data, array $options = [])
    {
        $connectTimeout = self::positiveInt(
            isset($options['connect_timeout']) ? $options['connect_timeout'] : getenv('SOURCE_HTTP_CONNECT_TIMEOUT'),
            self::DEFAULT_CONNECT_TIMEOUT
        );
        $timeout = self::positiveInt(
            isset($options['timeout']) ? $options['timeout'] : getenv('SOURCE_HTTP_TIMEOUT'),
            self::DEFAULT_TIMEOUT
        );
        $verifyTls = array_key_exists('verify_tls', $options)
            ? (bool)$options['verify_tls']
            : self::envBool('SOURCE_HTTP_VERIFY_TLS', true);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $verifyTls);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $verifyTls ? 2 : 0);
        if (defined('CURLOPT_NOSIGNAL')) {
            curl_setopt($curl, CURLOPT_NOSIGNAL, true);
        }

        $body = curl_exec($curl);
        $errno = curl_errno($curl);
        $error = curl_error($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $transportOk = $body !== false && $errno === 0;
        $httpOk = $status >= 200 && $status < 300;
        $ok = $transportOk && $httpOk;

        if (!$ok) {
            error_log(sprintf(
                '[SourceHttpClient] POST %s failed status=%d errno=%d error=%s',
                $url,
                $status,
                $errno,
                $error
            ));
        }

        return [
            'ok' => $ok,
            'transport_ok' => $transportOk,
            'http_ok' => $httpOk,
            'status' => $status,
            'errno' => $errno,
            'error' => $error,
            'body' => $body,
            'verify_tls' => $verifyTls,
        ];
    }

    public static function envBool($name, $default)
    {
        $value = getenv($name);
        if ($value === false || trim((string)$value) === '') {
            return (bool)$default;
        }
        return !in_array(strtolower(trim((string)$value)), ['0', 'false', 'off', 'no'], true);
    }

    protected static function positiveInt($value, $default)
    {
        $value = (int)$value;
        return $value > 0 ? $value : (int)$default;
    }
}
