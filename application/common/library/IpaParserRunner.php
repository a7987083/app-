<?php

namespace app\common\library;

use RuntimeException;

class IpaParserRunner
{
    const DEFAULT_ENDPOINT = 'tcp://127.0.0.1:19191';
    const CONNECT_TIMEOUT = 3.0;
    const READ_TIMEOUT = 65;
    const MAX_RESPONSE_BYTES = 4194304;

    public static function parse($rawUrl, $fileSize, $maxFetch = 25165824)
    {
        $rawUrl = trim((string)$rawUrl);
        $fileSize = (int)$fileSize;
        if (!preg_match('#^https?://#i', $rawUrl) || $fileSize <= 0) {
            throw new RuntimeException('Parser raw_url/size 参数无效');
        }

        $decoded = self::request([
            'op' => 'parse',
            'url' => $rawUrl,
            'size' => $fileSize,
            'max_fetch' => max(4 * 1024 * 1024, min(64 * 1024 * 1024, (int)$maxFetch)),
        ], self::READ_TIMEOUT);

        if (empty($decoded['ok'])) {
            $message = !empty($decoded['error']) ? (string)$decoded['error'] : 'IPA parser service failed';
            throw new RuntimeException(mb_substr($message, 0, 1000, 'UTF-8'));
        }

        return isset($decoded['metadata']) && is_array($decoded['metadata']) ? $decoded['metadata'] : [];
    }

    public static function health($connectTimeout = 1.0)
    {
        try {
            $decoded = self::request(['op' => 'health'], 3, max(0.1, (float)$connectTimeout));
            return [
                'ok' => !empty($decoded['ok']),
                'service' => isset($decoded['service']) ? (string)$decoded['service'] : '',
                'version' => isset($decoded['version']) ? (string)$decoded['version'] : '',
                'endpoint' => self::endpoint(),
                'error' => empty($decoded['ok']) && !empty($decoded['error']) ? (string)$decoded['error'] : '',
            ];
        } catch (\Exception $e) {
            return ['ok' => false, 'service' => '', 'version' => '', 'endpoint' => self::endpoint(), 'error' => $e->getMessage()];
        }
    }

    public static function endpoint()
    {
        $configured = getenv('ZONOE_IPA_PARSER_ENDPOINT');
        $configured = is_string($configured) ? trim($configured) : '';
        return $configured !== '' ? $configured : self::DEFAULT_ENDPOINT;
    }

    protected static function request(array $payload, $readTimeout, $connectTimeout = null)
    {
        $last = null;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                return self::requestOnce($payload, $readTimeout, $connectTimeout);
            } catch (RuntimeException $e) {
                $last = $e;
                if ($attempt === 0) {
                    usleep(200000);
                    continue;
                }
            }
        }
        throw $last ?: new RuntimeException('IPA parser service request failed');
    }

    protected static function requestOnce(array $payload, $readTimeout, $connectTimeout = null)
    {
        $connectTimeout = $connectTimeout === null ? self::CONNECT_TIMEOUT : max(0.1, (float)$connectTimeout);
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(self::endpoint(), $errno, $errstr, $connectTimeout, STREAM_CLIENT_CONNECT);
        if (!is_resource($socket)) {
            $detail = trim((string)$errstr);
            if ($detail === '') $detail = 'connection failed';
            throw new RuntimeException('IPA parser service unavailable (' . self::endpoint() . '): ' . $detail . '. Run: sudo bash scripts/install-ipa-parser-service.sh');
        }

        stream_set_blocking($socket, true);
        stream_set_timeout($socket, max(1, (int)$readTimeout));
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            fclose($socket);
            throw new RuntimeException('IPA parser service request encode failed');
        }

        try {
            self::writeAll($socket, $json . "\n");
            $line = stream_get_line($socket, self::MAX_RESPONSE_BYTES, "\n");
            $meta = stream_get_meta_data($socket);
            if (!empty($meta['timed_out'])) {
                throw new RuntimeException('IPA parser service timeout');
            }
            if ($line === false || trim((string)$line) === '') {
                throw new RuntimeException('IPA parser service returned empty response');
            }
            $decoded = json_decode(trim((string)$line), true);
            if (!is_array($decoded)) {
                throw new RuntimeException('IPA parser service returned invalid JSON');
            }
            return $decoded;
        } finally {
            fclose($socket);
        }
    }

    protected static function writeAll($socket, $data)
    {
        $length = strlen($data);
        $offset = 0;
        while ($offset < $length) {
            $written = fwrite($socket, substr($data, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('IPA parser service request write failed');
            }
            $offset += $written;
        }
    }
}
