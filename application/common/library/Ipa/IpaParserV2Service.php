<?php

namespace app\common\library\Ipa;

use think\Db;

/**
 * Fast IPA metadata parser introduced in 2026092426.
 *
 * Contract: parsing an IPA means reading Payload/*.app/Info.plist only. Binary
 * discovery, Mach-O inspection, hashing and software-source comparison are not
 * part of this transaction and must never block metadata parsing.
 *
 * 2026092427: the parser is explicitly bounded to 16 MiB of physical Range
 * traffic per IPA and returns Range telemetry to the worker. It also mirrors
 * ipaxiazaizhan-'s MD5 metadata library semantics by reusing parsed metadata
 * from an already parsed asset with the same OpenList MD5 fingerprint.
 */
class IpaParserV2Service
{
    const PLIST_MAX_BYTES = 4194304;   // 4 MiB
    const RANGE_MAX_BYTES = 16777216;  // 16 MiB

    public static function parseAsset($assetId, array $source, $token = '')
    {
        $assetId = (int)$assetId;
        $asset = Db::name('ipa_asset')->where('id', $assetId)->find();
        if (!$asset) {
            throw new \RuntimeException('IPA asset not found');
        }

        // Fastest path: if the scanner already persisted a content MD5, reuse
        // metadata before making even the OpenList /api/fs/get request.
        $fingerprint = self::normalizeFingerprint(isset($asset['etag']) ? $asset['etag'] : '');
        if ($fingerprint !== '') {
            $reused = self::reuseParsedMetadata($asset, $fingerprint);
            if ($reused !== null) {
                return $reused;
            }
        }

        $client = new OpenListClient($source['base_url'], $token, $source['request_timeout']);
        $file = $client->getFile($asset['path']);

        // Some OpenList drivers omit hash fields from directory listings but do
        // expose them from /api/fs/get. Persist that fingerprint and retry the
        // MD5-library fast path before opening the remote ZIP.
        $freshFingerprint = self::fileFingerprint($file);
        if ($freshFingerprint !== '') {
            if ($freshFingerprint !== $fingerprint) {
                Db::name('ipa_asset')->where('id', $assetId)->update([
                    'etag' => $freshFingerprint,
                    'updated_at' => time(),
                ]);
                $asset['etag'] = $freshFingerprint;
                $fingerprint = $freshFingerprint;
            }
            $reused = self::reuseParsedMetadata($asset, $freshFingerprint);
            if ($reused !== null) {
                return $reused;
            }
        }

        $rawUrl = isset($file['raw_url']) ? trim((string)$file['raw_url']) : '';
        if ($rawUrl === '') {
            throw new \RuntimeException('OpenList did not return raw_url');
        }

        $freshSize = isset($file['size']) ? max(0, (int)$file['size']) : 0;
        $storedSize = !empty($asset['size_bytes']) ? max(0, (int)$asset['size_bytes']) : 0;
        $knownSize = $freshSize > 0 ? $freshSize : $storedSize;
        if ($knownSize <= 0) {
            throw new \RuntimeException('Unable to determine IPA size');
        }

        $range = new HttpRangeClient(
            $rawUrl,
            [],
            (int)$source['request_timeout'],
            $knownSize,
            67108864,
            self::RANGE_MAX_BYTES,
            HttpRangeClient::DEFAULT_BLOCK_BYTES
        );
        $zip = new RemoteZipReader($range);
        $plistEntry = $zip->findFirst('#^Payload/[^/]+\\.app/Info\\.plist$#i');
        if (!$plistEntry) {
            throw new \RuntimeException('Payload app Info.plist not found');
        }
        if ((int)$plistEntry['uncompressed_size'] <= 0 || (int)$plistEntry['uncompressed_size'] > self::PLIST_MAX_BYTES) {
            throw new \RuntimeException('Info.plist size is invalid');
        }

        $plistBytes = $zip->extract($plistEntry, self::PLIST_MAX_BYTES);
        $plist = (new PlistDecoder())->decode($plistBytes);
        unset($plistBytes);
        if (!is_array($plist)) {
            throw new \RuntimeException('Info.plist root is not a dictionary');
        }

        $bundleId = self::str($plist, 'CFBundleIdentifier');
        $appName = self::str($plist, 'CFBundleDisplayName');
        if ($appName === '') {
            $appName = self::str($plist, 'CFBundleName');
        }
        $appVersion = self::str($plist, 'CFBundleShortVersionString');
        $buildVersion = self::str($plist, 'CFBundleVersion');
        $minimumOs = self::str($plist, 'MinimumOSVersion');
        $executable = self::str($plist, 'CFBundleExecutable');
        unset($plist);

        $now = time();
        $update = [
            'raw_url' => $rawUrl,
            'status' => 'parsed',
            'bundle_id' => $bundleId,
            'app_name' => $appName,
            'app_version' => $appVersion,
            'build_version' => $buildVersion,
            'minimum_os' => $minimumOs,
            'sha256' => '',
            'last_error' => null,
            'parsed_at' => $now,
            'updated_at' => $now,
        ];
        if ($knownSize > 0) {
            $update['size_bytes'] = $knownSize;
        }
        if ($freshFingerprint !== '') {
            $update['etag'] = $freshFingerprint;
        }

        // Keep the write phase short. Old 2425 binary/index rows are derived from
        // the retired parser and must not survive a V2 parse as if they were fresh.
        Db::startTrans();
        try {
            Db::name('ipa_asset')->where('id', $assetId)->update($update);
            self::clearRetiredDerivedRows($assetId);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }

        $rangeStats = $range->stats();
        return [
            'asset_id' => $assetId,
            'bundle_id' => $bundleId,
            'app_name' => $appName,
            'app_version' => $appVersion,
            'build_version' => $buildVersion,
            'minimum_os' => $minimumOs,
            'executable' => $executable,
            'parser' => 'v2-fast-plist',
            'reused_by_md5' => false,
            'range_bytes' => (int)$rangeStats['network_bytes'],
            'range_requests' => (int)$rangeStats['network_requests'],
            'range_cached_blocks' => (int)$rangeStats['cached_blocks'],
            'range_budget_bytes' => (int)$rangeStats['budget_bytes'],
        ];
    }

    /**
     * Treat another parsed ipa_asset row as the persistent metadata library.
     * This keeps the same-MD5 reuse semantics of ipaxiazaizhan- without adding
     * a new table/migration to the 2427 online-update path.
     */
    protected static function reuseParsedMetadata(array $asset, $fingerprint)
    {
        $fingerprint = self::normalizeFingerprint($fingerprint);
        if ($fingerprint === '') {
            return null;
        }

        $query = Db::name('ipa_asset')
            ->where('id', '<>', (int)$asset['id'])
            ->where('status', 'parsed')
            ->where('etag', $fingerprint)
            ->where('parsed_at', '>', 0);
        if (!empty($asset['size_bytes'])) {
            $query->where('size_bytes', (int)$asset['size_bytes']);
        }
        $cached = $query->order('parsed_at desc,id desc')->find();
        if (!$cached) {
            return null;
        }

        $now = time();
        $update = [
            'status' => 'parsed',
            'bundle_id' => isset($cached['bundle_id']) ? (string)$cached['bundle_id'] : '',
            'app_name' => isset($cached['app_name']) ? (string)$cached['app_name'] : '',
            'app_version' => isset($cached['app_version']) ? (string)$cached['app_version'] : '',
            'build_version' => isset($cached['build_version']) ? (string)$cached['build_version'] : '',
            'minimum_os' => isset($cached['minimum_os']) ? (string)$cached['minimum_os'] : '',
            'sha256' => '',
            'last_error' => null,
            'parsed_at' => $now,
            'updated_at' => $now,
            'etag' => $fingerprint,
        ];

        Db::startTrans();
        try {
            Db::name('ipa_asset')->where('id', (int)$asset['id'])->update($update);
            self::clearRetiredDerivedRows((int)$asset['id']);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }

        return [
            'asset_id' => (int)$asset['id'],
            'bundle_id' => $update['bundle_id'],
            'app_name' => $update['app_name'],
            'app_version' => $update['app_version'],
            'build_version' => $update['build_version'],
            'minimum_os' => $update['minimum_os'],
            'executable' => '',
            'parser' => 'v2-md5-reuse',
            'reused_by_md5' => true,
            'reused_from_asset_id' => (int)$cached['id'],
            'range_bytes' => 0,
            'range_requests' => 0,
            'range_cached_blocks' => 0,
            'range_budget_bytes' => self::RANGE_MAX_BYTES,
        ];
    }

    protected static function clearRetiredDerivedRows($assetId)
    {
        Db::name('ipa_binary')->where('asset_id', (int)$assetId)->delete();
        Db::name('ipa_app_identity')->where('asset_id', (int)$assetId)->delete();
        Db::name('ipa_compare_result')->where('asset_id', (int)$assetId)->delete();
    }

    protected static function fileFingerprint(array $file)
    {
        $md5 = '';
        if (isset($file['hash_info']) && is_array($file['hash_info']) && !empty($file['hash_info']['md5'])) {
            $md5 = (string)$file['hash_info']['md5'];
        } elseif (!empty($file['hashinfo'])) {
            if (is_array($file['hashinfo'])) {
                $hashInfo = $file['hashinfo'];
            } else {
                $hashInfo = json_decode((string)$file['hashinfo'], true);
            }
            if (is_array($hashInfo) && !empty($hashInfo['md5'])) {
                $md5 = (string)$hashInfo['md5'];
            }
        } elseif (!empty($file['md5'])) {
            $md5 = (string)$file['md5'];
        }
        return self::normalizeFingerprint($md5);
    }

    protected static function normalizeFingerprint($value)
    {
        $value = trim((string)$value);
        if (stripos($value, 'md5:') === 0) {
            $value = substr($value, 4);
        }
        $value = strtoupper(trim($value));
        return preg_match('/^[0-9A-F]{32}$/', $value) ? 'md5:' . $value : '';
    }

    public static function markParseError($assetId, \Exception $e)
    {
        Db::name('ipa_asset')->where('id', (int)$assetId)->update([
            'status' => 'parse_failed',
            'last_error' => substr($e->getMessage(), 0, 4000),
            'updated_at' => time(),
        ]);
    }

    protected static function str(array $plist, $key)
    {
        if (!array_key_exists($key, $plist) || is_array($plist[$key]) || is_object($plist[$key])) {
            return '';
        }
        return trim((string)$plist[$key]);
    }
}