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
        $packages = $this->releasePackageMetadata();
        if ($packages === false) {
            return false;
        }
        if (!$packages) {
            return null;
        }
        for ($i = count($packages) - 1; $i >= 0; $i--) {
            $package = $this->resolveSha256($packages[$i]);
            if ($package !== null) {
                return $package;
            }
        }
        return null;
    }

    public function packagesAfter($localVersion, $force = false)
    {
        $packages = $this->releasePackageMetadata();
        if ($packages === false) {
            return false;
        }
        $candidates = [];
        foreach ($packages as $package) {
            if ($force ? intval($package['version']) >= intval($localVersion) : intval($package['version']) > intval($localVersion)) {
                $candidates[] = $package;
            }
        }
        if ($force && !$candidates && $packages) {
            $candidates[] = end($packages);
        }

        $result = [];
        foreach ($candidates as $package) {
            $resolved = $this->resolveSha256($package);
            if ($resolved !== null) {
                $result[] = $resolved;
            }
        }
        return $result;
    }

    protected function releasePackageMetadata()
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
            $assetDigest = '';
            $assets = isset($release['assets']) && is_array($release['assets']) ? $release['assets'] : [];
            foreach ($assets as $asset) {
                if (!is_array($asset) || empty($asset['name']) || empty($asset['browser_download_url'])) {
                    continue;
                }
                if ($asset['name'] === self::UPDATE_ASSET) {
                    $zipUrl = (string)$asset['browser_download_url'];
                    $digest = isset($asset['digest']) ? trim((string)$asset['digest']) : '';
                    if (preg_match('/^sha256:([a-fA-F0-9]{64})$/', $digest, $match)) {
                        $assetDigest = strtolower($match[1]);
                    }
                } elseif ($asset['name'] === self::SHA_ASSET) {
                    $shaUrl = (string)$asset['browser_download_url'];
                }
            }
            if ($zipUrl === '' || ($assetDigest === '' && $shaUrl === '')) {
                continue;
            }
            $packages[] = [
                'source' => $this->name(),
                'version' => $version,
                'download' => $zipUrl,
                'sha256' => $assetDigest,
                '_sha_url' => $shaUrl,
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

    protected function resolveSha256(array $package)
    {
        if (!empty($package['sha256']) && preg_match('/^[a-f0-9]{64}$/', $package['sha256'])) {
            unset($package['_sha_url']);
            return $package;
        }
        $shaUrl = isset($package['_sha_url']) ? trim((string)$package['_sha_url']) : '';
        if ($shaUrl === '') {
            return null;
        }
        $shaText = $this->http->get($shaUrl, 4096);
        if ($shaText === false || !preg_match('/\b([a-fA-F0-9]{64})\b/', $shaText, $match)) {
            return null;
        }
        $package['sha256'] = strtolower($match[1]);
        unset($package['_sha_url']);
        return $package;
    }

    public static function versionFromTag($tag)
    {
        if (preg_match('/(?:source[-_v]*)?(20\d{6,12})/i', (string)$tag, $match)) {
            return $match[1];
        }
        return '';
    }
}
