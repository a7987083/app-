<?php

namespace app\common\library\Ipa;

/**
 * Minimal OpenList API client used by IPA Data Center.
 *
 * OpenList expects the raw login token in Authorization (no Bearer prefix).
 */
class OpenListClient
{
    protected $baseUrl;
    protected $token;
    protected $timeout;

    public function __construct($baseUrl, $token = '', $timeout = 20)
    {
        $this->baseUrl = rtrim((string)$baseUrl, '/');
        $this->token = trim((string)$token);
        $this->timeout = max(3, (int)$timeout);
    }

    public function listDirectory($path, $page = 1, $perPage = 500, $refresh = false)
    {
        return $this->post('/api/fs/list', [
            'path' => $this->normalizePath($path),
            'password' => '',
            'page' => max(1, (int)$page),
            'per_page' => max(1, min(1000, (int)$perPage)),
            'refresh' => (bool)$refresh,
        ]);
    }

    public function getFile($path)
    {
        return $this->post('/api/fs/get', [
            'path' => $this->normalizePath($path),
            'password' => '',
        ]);
    }

    protected function post($endpoint, array $payload)
    {
        $ch = curl_init($this->baseUrl . $endpoint);
        if ($ch === false) {
            throw new \RuntimeException('Unable to initialize curl');
        }

        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($this->token !== '') {
            $headers[] = 'Authorization: ' . $this->token;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $errno !== 0) {
            throw new \RuntimeException('OpenList request failed: ' . $error, $errno);
        }
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('OpenList HTTP ' . $status);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('OpenList returned invalid JSON');
        }

        $code = isset($decoded['code']) ? (int)$decoded['code'] : 0;
        if ($code !== 200) {
            $message = isset($decoded['message']) ? (string)$decoded['message'] : 'unknown OpenList error';
            throw new \RuntimeException('OpenList API error: ' . $message, $code);
        }

        return isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : [];
    }

    protected function normalizePath($path)
    {
        $path = trim((string)$path);
        if ($path === '' || $path === '/') {
            return '/';
        }
        return '/' . ltrim(preg_replace('#/+#', '/', $path), '/');
    }
}
