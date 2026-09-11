<?php

namespace app\common\library\update;

class GitHubUpdateSource implements UpdateSourceInterface
{
    const RELEASES_URL = 'https://api.github.com/repos/a7987083/app-/releases?per_page=30';
    const UPDATE_ASSET = 'zonoe-online-update.zip';
    const SHA_ASSET = 'zonoe-online-update.zip.sha256';

    protected $http;

    public function __construct(UpdateHttpClient $http = null)
    {
        $this->http = $http ?: new UpdateHttpClient();
    }

    public function name()
    {
        return 'github';
    }

    public function requiresSha256()
    {
        return true;
    }

    public function latest()
    {
        $packages = $this->releasePackages();
        if ($packages === false) {
            return false;
        }
        if (!$packages) {
            return null;
        }
        return end($packages);
    }

    public function packagesAfter($localVersion, $force = false)
    {
        $packages = $this->releasePackages();
        if ($packages === false) {
            return false;
        }
        $result = [];
        foreach ($packages as $package) {
            if ($force ? intval($package['version']) >= intval($localVersion) : intval($package['version']) > intval($localVersion)) {
                $result[] = $package;
            }
        }
        if ($force && !$result && $packages) {
            $result[] = end($packages);
        }
        return $result;
    }

    protected function releasePackages()
    {
        $raw = $this->http->get(self::RELEASES_URL);
        if ($raw === false) {
            return false;
        }
        $releases = json_decode($raw, true);
        if (!is_array($releases)) {
            return false;
        }
        $packages = [];
        foreach ($releases as $release) {
            if (!is_array($release) || !empty($release['draft']) || !empty($release['prerelease'])) {
                continue;
            }
            $version = self::versionFromTag(isset($release['tag_name']) ? $release['tag_name'] : '');
            if ($version === '') {
                continue;
            }
            $zipUrl = '';
            $shaUrl = '';
            $assets = isset($release['assets']) && is_array($release['assets']) ? $release['assets'] : [];
            foreach ($assets as $asset) {
                if (!is_array($asset) || empty($asset['name']) || empty($asset['browser_download_url'])) {
                    continue;
                }
                if ($asset['name'] === self::UPDATE_ASSET) {
                    $zipUrl = (string)$asset['browser_download_url'];
                } elseif ($asset['name'] === self::SHA_ASSET) {
                    $shaUrl = (string)$asset['browser_download_url'];
                }
            }
            if ($zipUrl === '' || $shaUrl === '') {
                continue;
            }
            $shaText = $this->http->get($shaUrl, 4096);
            if ($shaText === false || !preg_match('/\b([a-fA-F0-9]{64})\b/', $shaText, $match)) {
                continue;
            }
            $packages[] = [
                'source' => $this->name(),
                'version' => $version,
                'download' => $zipUrl,
                'sha256' => strtolower($match[1]),
                'file_sign' => '',
                'desc' => isset($release['body']) ? (string)$release['body'] : '',
                'vn' => isset($release['name']) ? (string)$release['name'] : '',
            ];
        }
        usort($packages, function ($a, $b) {
            return intval($a['version']) - intval($b['version']);
        });
        return $packages;
    }

    public static function versionFromTag($tag)
    {
        if (preg_match('/(?:source[-_v]*)?(20\d{6,12})/i', (string)$tag, $match)) {
            return $match[1];
        }
        return '';
    }
}
