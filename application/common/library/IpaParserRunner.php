<?php

namespace app\common\library;

use RuntimeException;

class IpaParserRunner
{
    public static function parse($rawUrl, $fileSize, $maxFetch = 25165824)
    {
        $rawUrl = trim((string)$rawUrl);
        $fileSize = (int)$fileSize;
        if (!preg_match('#^https?://#i', $rawUrl) || $fileSize <= 0) {
            throw new RuntimeException('Parser raw_url/size 参数无效');
        }
        if (!function_exists('proc_open')) {
            throw new RuntimeException('PHP proc_open is required for IPA parser worker');
        }

        $script = self::scriptPath();
        if (!is_file($script)) {
            throw new RuntimeException('IPA parser script not found');
        }

        $command = 'python3 ' . escapeshellarg($script);
        $spec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $spec, $pipes, defined('ROOT_PATH') ? ROOT_PATH : null);
        if (!is_resource($process)) {
            throw new RuntimeException('Failed to start IPA parser worker');
        }

        $request = json_encode([
            'url' => $rawUrl,
            'size' => $fileSize,
            'max_fetch' => max(4 * 1024 * 1024, min(64 * 1024 * 1024, (int)$maxFetch)),
        ], JSON_UNESCAPED_SLASHES);
        fwrite($pipes[0], $request);
        fclose($pipes[0]);

        stream_set_timeout($pipes[1], 60);
        stream_set_timeout($pipes[2], 60);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        $line = trim((string)$stdout);
        $decoded = json_decode($line, true);
        if ($exit !== 0 || !is_array($decoded) || empty($decoded['ok'])) {
            $message = is_array($decoded) && !empty($decoded['error']) ? $decoded['error'] : trim((string)$stderr);
            if ($message === '') {
                $message = 'IPA parser failed with exit ' . $exit;
            }
            $e = new RuntimeException(mb_substr($message, 0, 1000, 'UTF-8'));
            throw $e;
        }

        $metadata = isset($decoded['metadata']) && is_array($decoded['metadata']) ? $decoded['metadata'] : [];
        return $metadata;
    }

    public static function scriptPath()
    {
        $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR;
        return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'ipa-range-info.py';
    }
}
