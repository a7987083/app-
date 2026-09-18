<?php

namespace app\common\library;

use RuntimeException;

class IpaOpenListClient
{
    protected $baseUrl;
    protected $apiBase;
    protected $token;
    protected $timeout;
    protected $retries;

    public function __construct($baseUrl, $apiBase = '/api', $token = '', $timeout = 15, $retries = 2)
    {
        $this->baseUrl = rtrim(trim((string)$baseUrl), '/');
        $this->apiBase = '/' . trim((string)$apiBase, '/');
        $this->token = trim((string)$token);
        $this->timeout = max(3, min(120, (int)$timeout));
        $this->retries = max(0, min(5, (int)$retries));
        if ($this->baseUrl === '') throw new RuntimeException('OpenList base URL is empty');
    }

    public function listDirectory($path, $page = 1, $perPage = 200, $refresh = false)
    {
        $response = $this->request('/fs/list', [
            'path'=>IpaRemoteFile::normalizePath($path),'password'=>'','page'=>max(1,(int)$page),
            'per_page'=>max(1,min(1000,(int)$perPage)),'refresh'=>(bool)$refresh,
        ]);
        $data = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];
        $content = isset($data['content']) && is_array($data['content']) ? $data['content'] : [];
        return ['content'=>$content,'total'=>isset($data['total'])?(int)$data['total']:count($content),'raw'=>$data];
    }

    public function listIpaFiles($rootPath, $publicUrlTemplate = '', $refresh = false, $recursive = true, $maxDirectories = 5000)
    {
        $rootPath = IpaRemoteFile::normalizePath($rootPath);
        $queue = [$rootPath]; $seenDirs = []; $files = []; $dirCount = 0;
        while ($queue) {
            $dir = array_shift($queue);
            if (isset($seenDirs[$dir])) continue;
            $seenDirs[$dir] = true;
            if (++$dirCount > (int)$maxDirectories) throw new RuntimeException('OpenList directory safety limit exceeded');
            $page = 1; $perPage = 500;
            do {
                $listed = $this->listDirectory($dir, $page, $perPage, $refresh);
                $entries = $listed['content'];
                foreach ($entries as $entry) {
                    if (!is_array($entry) || empty($entry['name'])) continue;
                    if (!empty($entry['is_dir'])) {
                        if ($recursive) $queue[] = IpaRemoteFile::joinPath($dir, $entry['name']);
                        continue;
                    }
                    if (!IpaRemoteFile::isIpaName($entry['name'])) continue;
                    $files[] = IpaRemoteFile::fromOpenListEntry('openlist', $dir, $entry, $publicUrlTemplate);
                }
                $page++;
                $total = (int)$listed['total'];
                $moreByTotal = $total > 0 && (($page - 1) * $perPage) < $total;
                $moreByPageSize = $total <= 0 && count($entries) >= $perPage;
            } while ($moreByTotal || $moreByPageSize);
        }
        return ['root'=>$rootPath,'directories'=>$dirCount,'files'=>$files];
    }

    public function health($path = '/')
    {
        $started = microtime(true);
        $result = $this->listDirectory($path, 1, 1, false);
        return ['ok'=>true,'latency_ms'=>(int)round((microtime(true)-$started)*1000),'visible_total'=>$result['total']];
    }

    protected function request($endpoint, array $payload)
    {
        if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL extension is required');
        $url = $this->baseUrl . $this->apiBase . '/' . ltrim($endpoint, '/');
        $attempt = 0; $lastError = '';
        do {
            $attempt++;
            $ch = curl_init($url);
            $headers = ['Content-Type: application/json','Accept: application/json'];
            if ($this->token !== '') $headers[] = 'Authorization: ' . $this->token;
            curl_setopt_array($ch, [
                CURLOPT_POST=>true,
                CURLOPT_POSTFIELDS=>json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_CONNECTTIMEOUT=>min(10,$this->timeout),CURLOPT_TIMEOUT=>$this->timeout,
                CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HEADER=>false,
            ]);
            $body = curl_exec($ch); $errno = curl_errno($ch); $error = curl_error($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            if ($errno===0 && $status>=200 && $status<300 && is_string($body)) {
                $decoded = json_decode($body,true);
                if (is_array($decoded)) {
                    $code = isset($decoded['code']) ? (int)$decoded['code'] : 200;
                    if ($code===200 || $code===0) return $decoded;
                    $lastError = isset($decoded['message']) ? (string)$decoded['message'] : ('OpenList code '.$code);
                } else $lastError = 'OpenList returned invalid JSON';
            } else $lastError = $error!=='' ? $error : ('HTTP '.$status);
            if ($attempt <= $this->retries) usleep(150000*$attempt);
        } while ($attempt <= $this->retries + 1);
        throw new RuntimeException('OpenList request failed: '.$lastError);
    }
}
