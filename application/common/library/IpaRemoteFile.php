<?php

namespace app\common\library;

class IpaRemoteFile
{
    public static function normalizePath($path)
    {
        $path = str_replace('\\', '/', trim((string)$path));
        $path = preg_replace('#/+#', '/', $path);
        if ($path === '' || $path === '.') return '/';
        if ($path[0] !== '/') $path = '/' . $path;
        if (strlen($path) > 1) $path = rtrim($path, '/');
        return $path;
    }

    public static function joinPath($dir, $name)
    {
        $dir = self::normalizePath($dir);
        $name = ltrim(str_replace('\\', '/', (string)$name), '/');
        return self::normalizePath(($dir === '/' ? '' : $dir) . '/' . $name);
    }

    public static function pathHash($path)
    {
        return hash('sha256', self::normalizePath($path));
    }

    public static function isIpaName($name)
    {
        return strtolower(pathinfo((string)$name, PATHINFO_EXTENSION)) === 'ipa';
    }

    public static function publicUrl($template, $path)
    {
        $path = self::normalizePath($path);
        $template = trim((string)$template);
        if ($template === '') return '';
        if (strpos($template, '{path}') !== false) return str_replace('{path}', ltrim($path, '/'), $template);
        return rtrim($template, '/') . '/' . ltrim($path, '/');
    }

    public static function fromOpenListEntry($sourceKey, $dir, array $entry, $publicUrlTemplate = '')
    {
        $name = isset($entry['name']) ? (string)$entry['name'] : '';
        $path = self::joinPath($dir, $name);
        $modified = isset($entry['modified']) ? $entry['modified'] : (isset($entry['mtime']) ? $entry['mtime'] : 0);
        if (!is_numeric($modified)) $modified = $modified ? strtotime($modified) : 0;
        $etag = '';
        foreach (['etag', 'ETag', 'sign'] as $key) {
            if (isset($entry[$key]) && is_scalar($entry[$key])) {
                $etag = trim((string)$entry[$key]);
                if ($etag !== '') break;
            }
        }
        return [
            'source_key' => (string)$sourceKey,
            'remote_path' => $path,
            'remote_path_hash' => self::pathHash($path),
            'file_name' => $name,
            'public_url' => self::publicUrl($publicUrlTemplate, $path),
            'file_size' => isset($entry['size']) ? max(0, (int)$entry['size']) : 0,
            'remote_mtime' => max(0, (int)$modified),
            'etag' => $etag,
            'md5' => self::extractMd5($entry),
        ];
    }

    public static function extractMd5(array $entry)
    {
        foreach (['md5', 'MD5'] as $key) {
            if (!empty($entry[$key]) && preg_match('/^[a-f0-9]{32}$/i', (string)$entry[$key])) return strtolower((string)$entry[$key]);
        }
        if (isset($entry['hash_info']) && is_array($entry['hash_info'])) {
            foreach (['md5', 'MD5'] as $key) {
                if (!empty($entry['hash_info'][$key]) && preg_match('/^[a-f0-9]{32}$/i', (string)$entry['hash_info'][$key])) return strtolower((string)$entry['hash_info'][$key]);
            }
        }
        if (isset($entry['hash_info']) && is_string($entry['hash_info']) && preg_match('/(?:md5[:=]\s*)?([a-f0-9]{32})/i', $entry['hash_info'], $m)) return strtolower($m[1]);
        return '';
    }

    public static function fingerprint(array $row)
    {
        $md5 = isset($row['md5']) ? strtolower(trim((string)$row['md5'])) : '';
        if ($md5 !== '') return 'md5:' . $md5;
        $etag = isset($row['etag']) ? trim((string)$row['etag']) : '';
        if ($etag !== '') return 'etag:' . $etag;
        return 'stat:' . (isset($row['file_size']) ? (int)$row['file_size'] : 0) . ':' . (isset($row['remote_mtime']) ? (int)$row['remote_mtime'] : 0);
    }
}
