<?php

namespace app\common\library\update;

class NuosikeUpdateSource implements UpdateSourceInterface
{
    const BASE_URL = 'https://update-appstore.nuosike.com/update/';

    protected $http;

    public function __construct(UpdateHttpClient $http = null)
    {
        $this->http = $http ?: new UpdateHttpClient();
    }

    public function name()
    {
        return 'nuosike';
    }

    public function requiresSha256()
    {
        // Historical provider packages did not publish SHA256. Keep compatibility;
        // if a future manifest contains sha256 it will still be verified.
        return false;
    }

    public function latest()
    {
        $raw = $this->http->get(self::BASE_URL . 'server/last_version');
        if ($raw === false) {
            return false;
        }
        $obj = json_decode($raw, true);
        if (!is_array($obj) || !array_key_exists('data', $obj) || $obj['data'] === false) {
            return null;
        }
        $version = trim((string)$obj['data']);
        if ($version === '') {
            return null;
        }
        $info = $this->fetchVersion($version);
        $desc = isset($obj['changelog']) ? (string)$obj['changelog'] : '';
        if ($desc === '' && is_array($info) && isset($info['desc'])) {
            $desc = (string)$info['desc'];
        }
        return [
            'source' => $this->name(),
            'version' => $version,
            'download' => is_array($info) && isset($info['download']) ? (string)$info['download'] : '',
            'sha256' => is_array($info) && isset($info['sha256']) ? strtolower(trim((string)$info['sha256'])) : '',
            'file_sign' => isset($obj['file_sign']) ? (string)$obj['file_sign'] : '',
            'desc' => $desc,
            'vn' => isset($obj['vn']) ? (string)$obj['vn'] : '',
        ];
    }

    public function packagesAfter($localVersion, $force = false)
    {
        $raw = $this->http->get(self::BASE_URL . 'up_log.txt');
        if ($raw === false) {
            return false;
        }
        $versions = array_values(array_filter(array_map('trim', explode(',', $raw))));
        usort($versions, function ($a, $b) {
            return intval($a) - intval($b);
        });
        $packages = [];
        foreach ($versions as $version) {
            if (!$force && intval($version) <= intval($localVersion)) {
                continue;
            }
            if ($force && intval($version) < intval($localVersion)) {
                continue;
            }
            $info = $this->fetchVersion($version);
            if (!is_array($info) || empty($info['download'])) {
                return false;
            }
            $packages[] = [
                'source' => $this->name(),
                'version' => (string)$version,
                'download' => (string)$info['download'],
                'sha256' => isset($info['sha256']) ? strtolower(trim((string)$info['sha256'])) : '',
                'file_sign' => isset($info['file_sign']) ? (string)$info['file_sign'] : '',
                'desc' => isset($info['desc']) ? (string)$info['desc'] : '',
            ];
        }
        if ($force && !$packages) {
            $latest = $this->latest();
            if (is_array($latest) && !empty($latest['download']) && intval($latest['version']) >= intval($localVersion)) {
                $packages[] = $latest;
            }
        }
        return $packages;
    }

    protected function fetchVersion($version)
    {
        if (!preg_match('/^[0-9A-Za-z._-]+$/', $version)) {
            return false;
        }
        $raw = $this->http->get(self::BASE_URL . rawurlencode($version) . '/version.json');
        if ($raw === false) {
            return false;
        }
        $obj = json_decode($raw, true);
        return is_array($obj) ? $obj : false;
    }
}
