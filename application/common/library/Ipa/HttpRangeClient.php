<?php

namespace app\common\library\Ipa;

/**
 * Bounded HTTP Range reader for one IPA parse transaction.
 *
 * 2026092427:
 * - cache fixed-size blocks so EOCD/central-directory/local-header reads do not
 *   repeatedly hit OpenList for overlapping byte ranges;
 * - enforce a cumulative network-read budget per IPA, not only a per-request cap;
 * - expose physical network byte/request counters for parse telemetry.
 */
class HttpRangeClient
{
    const DEFAULT_BLOCK_BYTES = 262144;       // 256 KiB
    const DEFAULT_TOTAL_BUDGET = 16777216;    // 16 MiB

    protected $url;
    protected $headers;
    protected $timeout;
    protected $size;
    protected $maxRequestBytes;
    protected $maxTotalBytes;
    protected $blockSize;
    protected $cache = [];
    protected $networkBytes = 0;
    protected $networkRequests = 0;

    public function __construct($url, array $headers = [], $timeout = 30, $knownSize = 0, $maxRequestBytes = 67108864, $maxTotalBytes = self::DEFAULT_TOTAL_BUDGET, $blockSize = self::DEFAULT_BLOCK_BYTES)
    {
        $this->url = (string)$url;
        $this->headers = $headers;
        $this->timeout = max(3, (int)$timeout);
        $this->size = max(0, (int)$knownSize);
        $this->maxRequestBytes = max(1048576, (int)$maxRequestBytes);
        $this->maxTotalBytes = max(1048576, (int)$maxTotalBytes);
        $this->blockSize = max(65536, min(1048576, (int)$blockSize));
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
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $this->headers,
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

        $size = $this->size();
        if ($start >= $size || $length > $size - $start) {
            throw new \InvalidArgumentException('Range exceeds remote file size');
        }

        $endExclusive = $start + $length;
        $cursor = $start;
        $out = '';
        while ($cursor < $endExclusive) {
            $blockStart = (int)(floor($cursor / $this->blockSize) * $this->blockSize);
            $block = $this->getBlock($blockStart, $size);
            $offsetInBlock = $cursor - $blockStart;
            $take = min($endExclusive - $cursor, strlen($block) - $offsetInBlock);
            if ($take <= 0) {
                throw new \RuntimeException('Range cache produced an invalid slice');
            }
            $out .= substr($block, $offsetInBlock, $take);
            $cursor += $take;
        }

        if (strlen($out) !== $length) {
            throw new \RuntimeException('Range response length mismatch');
        }
        return $out;
    }

    public function stats()
    {
        return [
            'network_bytes' => (int)$this->networkBytes,
            'network_requests' => (int)$this->networkRequests,
            'cached_blocks' => count($this->cache),
            'block_bytes' => (int)$this->blockSize,
            'budget_bytes' => (int)$this->maxTotalBytes,
        ];
    }

    protected function getBlock($blockStart, $size)
    {
        $key = (string)$blockStart;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $length = min($this->blockSize, $size - $blockStart);
        if ($length <= 0) {
            throw new \RuntimeException('Invalid Range cache block');
        }
        if ($this->networkBytes + $length > $this->maxTotalBytes) {
            throw new \RuntimeException(sprintf(
                'IPA Range read budget exceeded: %d + %d > %d bytes',
                $this->networkBytes,
                $length,
                $this->maxTotalBytes
            ));
        }

        $body = $this->fetchRange($blockStart, $length);
        $this->networkBytes += strlen($body);
        $this->networkRequests++;
        $this->cache[$key] = $body;
        return $body;
    }

    protected function fetchRange($start, $length)
    {
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
