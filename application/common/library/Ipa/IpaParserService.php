<?php

namespace app\common\library\Ipa;

use think\Db;

class IpaParserService
{
    public static function parseAsset($assetId, array $source, $token = '')
    {
        $asset = Db::name('ipa_asset')->where('id', (int)$assetId)->find();
        if (!$asset) {
            throw new \RuntimeException('IPA asset not found');
        }

        $client = new OpenListClient($source['base_url'], $token, $source['request_timeout']);
        $file = $client->getFile($asset['path']);
        $rawUrl = isset($file['raw_url']) ? trim((string)$file['raw_url']) : '';
        if ($rawUrl === '') {
            throw new \RuntimeException('OpenList did not return raw_url');
        }

        $knownSize = !empty($asset['size_bytes']) ? (int)$asset['size_bytes'] : (isset($file['size']) ? (int)$file['size'] : 0);
        $range = new HttpRangeClient($rawUrl, [], (int)$source['request_timeout'], $knownSize);
        $zip = new RemoteZipReader($range);
        $entries = $zip->entries();

        $plistEntry = null;
        foreach ($entries as $name => $entry) {
            if (preg_match('#^Payload/[^/]+\.app/Info\.plist$#', $name)) {
                $plistEntry = $entry;
                break;
            }
        }
        if (!$plistEntry) {
            throw new \RuntimeException('Payload app Info.plist not found');
        }

        $plistBytes = $zip->extract($plistEntry, 8388608);
        $plist = (new PlistDecoder())->decode($plistBytes);
        unset($plistBytes);
        if (!is_array($plist)) {
            throw new \RuntimeException('Info.plist root is not a dictionary');
        }

        $bundleId = self::str($plist, 'CFBundleIdentifier');
        $appName = self::str($plist, 'CFBundleDisplayName');
        if ($appName === '') $appName = self::str($plist, 'CFBundleName');
        $appVersion = self::str($plist, 'CFBundleShortVersionString');
        $buildVersion = self::str($plist, 'CFBundleVersion');
        $minimumOs = self::str($plist, 'MinimumOSVersion');
        $executable = self::str($plist, 'CFBundleExecutable');
        unset($plist);

        $now = time();
        Db::startTrans();
        try {
            Db::name('ipa_asset')->where('id', (int)$asset['id'])->update([
                'raw_url' => $rawUrl,
                'status' => 'parsed',
                'bundle_id' => $bundleId,
                'app_name' => $appName,
                'app_version' => $appVersion,
                'build_version' => $buildVersion,
                'minimum_os' => $minimumOs,
                'last_error' => null,
                'parsed_at' => $now,
                'updated_at' => $now,
            ]);

            Db::name('ipa_binary')->where('asset_id', (int)$asset['id'])->delete();
            self::indexBinaries((int)$asset['id'], $zip, $entries, $executable, $now);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }

        return [
            'asset_id' => (int)$asset['id'],
            'bundle_id' => $bundleId,
            'app_name' => $appName,
            'app_version' => $appVersion,
            'build_version' => $buildVersion,
            'minimum_os' => $minimumOs,
            'executable' => $executable,
        ];
    }

    protected static function indexBinaries($assetId, RemoteZipReader $zip, array $entries, $executable, $now)
    {
        $inspector = new MachOInspector();
        $fullHashLimit = self::binaryHashLimit();
        $headerLimit = self::binaryHeaderLimit();

        foreach ($entries as $name => $entry) {
            $type = self::binaryType($name, $executable);
            if ($type === null || substr($name, -1) === '/') {
                continue;
            }

            $sha256 = '';
            $architectures = '';
            $installName = '';
            $uncompressedSize = (int)$entry['uncompressed_size'];
            $compressedSize = isset($entry['compressed_size']) ? (int)$entry['compressed_size'] : $uncompressedSize;

            // Architecture/load-command inspection only needs the beginning of a Mach-O member.
            // Use bounded streaming inflate so large game binaries never become large PHP strings.
            if ($uncompressedSize > 0) {
                try {
                    $prefix = $zip->extractPrefix($entry, $headerLimit);
                    if ($prefix !== '') {
                        $meta = $inspector->inspect($prefix);
                        if (!empty($meta['architectures']) && is_array($meta['architectures'])) {
                            $architectures = implode(',', array_values(array_unique($meta['architectures'])));
                        }
                        if (!empty($meta['install_name'])) {
                            $installName = (string)$meta['install_name'];
                        }
                    }
                    unset($prefix, $meta);
                } catch (\Exception $e) {
                    // Header enrichment is best effort. IPA metadata remains authoritative and must finish.
                }
            }

            // SHA256 requires the complete uncompressed member. Keep that synchronous only for small files;
            // large members remain indexed and can be hashed later by a dedicated streaming enrichment worker.
            if ($uncompressedSize > 0
                && $uncompressedSize <= $fullHashLimit
                && $compressedSize <= $fullHashLimit) {
                try {
                    $bytes = $zip->extract($entry, $fullHashLimit);
                    $sha256 = hash('sha256', $bytes);
                    unset($bytes);
                } catch (\Exception $e) {
                    // Hash enrichment is best effort.
                }
            }

            Db::name('ipa_binary')->insert([
                'asset_id' => $assetId,
                'relative_path' => $name,
                'binary_type' => $type,
                'name' => basename($name),
                'sha256' => $sha256,
                'size_bytes' => $uncompressedSize,
                'architectures' => $architectures,
                'install_name' => $installName,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    protected static function binaryHeaderLimit()
    {
        // 2 MiB comfortably covers normal Mach-O headers/load commands while keeping Range traffic bounded.
        return 2 * 1024 * 1024;
    }

    protected static function binaryHashLimit()
    {
        // Full-member hashing remains intentionally conservative under production PHP 7 memory_limit=128M.
        return 8 * 1024 * 1024;
    }

    protected static function binaryType($path, $executable)
    {
        if ($executable !== '' && preg_match('#^Payload/[^/]+\.app/' . preg_quote($executable, '#') . '$#', $path)) {
            return 'main';
        }
        if (preg_match('#^Payload/[^/]+\.app/Frameworks/.+\.dylib$#i', $path)) {
            return 'dylib';
        }
        if (preg_match('#^Payload/[^/]+\.app/Frameworks/([^/]+)\.framework/\1$#i', $path)) {
            return 'framework';
        }
        return null;
    }

    protected static function str(array $plist, $key)
    {
        if (!array_key_exists($key, $plist) || is_array($plist[$key]) || is_object($plist[$key])) {
            return '';
        }
        return trim((string)$plist[$key]);
    }

    public static function markParseError($assetId, \Exception $e)
    {
        Db::name('ipa_asset')->where('id', (int)$assetId)->update([
            'status' => 'parse_failed',
            'last_error' => substr($e->getMessage(), 0, 4000),
            'updated_at' => time(),
        ]);
    }
}
