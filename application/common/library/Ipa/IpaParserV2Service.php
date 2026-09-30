<?php

namespace app\common\library\Ipa;

use think\Db;

/**
 * Fast IPA metadata parser introduced in 2026092426.
 *
 * Contract: parsing an IPA means reading Payload/*.app/Info.plist only. Binary
 * discovery, Mach-O inspection, hashing and software-source comparison are not
 * part of this transaction and must never block metadata parsing.
 */
class IpaParserV2Service
{
    const PLIST_MAX_BYTES = 4194304; // 4 MiB

    public static function parseAsset($assetId, array $source, $token = '')
    {
        $assetId = (int)$assetId;
        $asset = Db::name('ipa_asset')->where('id', $assetId)->find();
        if (!$asset) {
            throw new \RuntimeException('IPA asset not found');
        }

        $client = new OpenListClient($source['base_url'], $token, $source['request_timeout']);
        $file = $client->getFile($asset['path']);
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

        $range = new HttpRangeClient($rawUrl, [], (int)$source['request_timeout'], $knownSize);
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

        // Keep the write phase short. Old 2425 binary/index rows are derived from
        // the retired parser and must not survive a V2 parse as if they were fresh.
        Db::startTrans();
        try {
            Db::name('ipa_asset')->where('id', $assetId)->update($update);
            Db::name('ipa_binary')->where('asset_id', $assetId)->delete();
            Db::name('ipa_app_identity')->where('asset_id', $assetId)->delete();
            Db::name('ipa_compare_result')->where('asset_id', $assetId)->delete();
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }

        return [
            'asset_id' => $assetId,
            'bundle_id' => $bundleId,
            'app_name' => $appName,
            'app_version' => $appVersion,
            'build_version' => $buildVersion,
            'minimum_os' => $minimumOs,
            'executable' => $executable,
            'parser' => 'v2-fast-plist',
        ];
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
