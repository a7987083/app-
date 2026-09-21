<?php

namespace app\common\library\Ipa;

class HttpRangeClient
{
    protected $url;
    protected $headers;
    protected $timeout;
    protected $size;
    protected $maxRequestBytes;

    public function __construct($url, array $headers = [], $timeout = 30, $knownSize = 0, $maxRequestBytes = 67108864)
    {
        $this->url = (string)$url;
        $this->headers = $headers;
        $this->timeout = max(3, (int)$timeout);
        $this->size = max(0, (int)$knownSize);
        $this->maxRequestBytes = max(1048576, (int)$maxRequestBytes);
        if ($this->url === '' || !preg_match('#^https?://#i', $this->url)) {
            throw new \InvalidArgumentException('Invalid HTTP URL');
        }
    }

    public function size()
    {
        if ($this->size > 0) {
            return $this->size;
        }
        $ch = curl_init($this->url);
        if ($ch === false) {
            throw new \RuntimeException('Unable to initialize curl');
        }
        $headers = $this->headers;
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $ok = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $length = (int)curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        curl_close($ch);
        if ($ok === false || $status < 200 || $status >= 400 || $length <= 0) {
            throw new \RuntimeException('Unable to determine remote size: ' . $error);
        }
        $this->size = $length;
        return $this->size;
    }

    public function getRange($start, $length)
    {
        $start = max(0, (int)$start);
        $length = (int)$length;
        if ($length <= 0 || $length > $this->maxRequestBytes) {
            throw new \InvalidArgumentException('Invalid range length');
        }
        $end = $start + $length - 1;
        $headers = $this->headers;
        $headers[] = 'Range: bytes=' . $start . '-' . $end;
        $ch = curl_init($this->url);
        if ($ch === false) {
            throw new \RuntimeException('Unable to initialize curl');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_ENCODING => 'identity',
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $errno !== 0) {
            throw new \RuntimeException('Range request failed: ' . $error, $errno);
        }
        if ($status !== 206) {
            throw new \RuntimeException('Remote server did not honor Range request, HTTP ' . $status);
        }
        if (strlen($body) !== $length) {
            throw new \RuntimeException('Range response length mismatch');
        }
        return $body;
    }
}
