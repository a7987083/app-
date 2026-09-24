<?php

namespace app\common\library\Ipa;

/**
 * Starts the scan worker from the same PHP installation/environment as PHP-FPM.
 * This avoids two production-only failures we observed:
 * - shell `php` may point to PHP 8 while the site runs PHP 7.0;
 * - CLI started manually may not inherit PHP_IPA_SERVER_SECRET from php-fpm.conf.
 */
class IpaWorkerLauncher
{
    public static function ensureScanWorker()
    {
        $snapshot = WorkerState::snapshot(15);
        if (isset($snapshot['scan']) && !empty($snapshot['scan']['alive'])) {
            return ['started' => false, 'status' => 'alive', 'worker_id' => (string)$snapshot['scan']['worker_id']];
        }

        $php = self::phpCli();
        $root = rtrim(dirname(rtrim(APP_PATH, '/\\')), '/\\');
        $think = $root . DIRECTORY_SEPARATOR . 'think';
        if (!is_file($think)) {
            throw new \RuntimeException('未找到 ThinkPHP CLI 入口：' . $think);
        }
        if (!function_exists('proc_open')) {
            throw new \RuntimeException('服务器已禁用 proc_open，无法自动启动 IPA 扫描 Worker');
        }

        $runtime = defined('RUNTIME_PATH') ? RUNTIME_PATH : ($root . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR);
        $logDir = rtrim($runtime, '/\\') . DIRECTORY_SEPARATOR . 'log';
        if (!is_dir($logDir) && !@mkdir($logDir, 0755, true) && !is_dir($logDir)) {
            throw new \RuntimeException('无法创建 IPA Worker 日志目录');
        }
        $log = $logDir . DIRECTORY_SEPARATOR . 'ipa_scan_worker.log';

        // Mark as starting before spawning so rapid repeated clicks do not fan out workers.
        $launcherId = 'launcher:' . gethostname() . ':' . getmypid();
        WorkerState::heartbeat('scan', $launcherId, 'starting', 0);

        $cmd = 'nohup ' . escapeshellarg($php)
            . ' ' . escapeshellarg($think)
            . ' ipa:worker --sleep=2 >> ' . escapeshellarg($log)
            . ' 2>&1 < /dev/null &';

        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', '/dev/null', 'a'],
            2 => ['file', '/dev/null', 'a'],
        ];
        // String form is required for PHP 7.0; array commands are only supported by newer PHP.
        $process = @proc_open($cmd, $descriptors, $pipes, $root, null);
        if (!is_resource($process)) {
            WorkerState::heartbeat('scan', $launcherId, 'stopped', 0);
            throw new \RuntimeException('无法创建 IPA 扫描 Worker 进程');
        }
        $exit = proc_close($process);
        if ($exit !== 0) {
            WorkerState::heartbeat('scan', $launcherId, 'stopped', 0);
            throw new \RuntimeException('IPA 扫描 Worker 启动命令失败，exit=' . (int)$exit);
        }

        return ['started' => true, 'status' => 'starting', 'worker_id' => $launcherId, 'php' => $php, 'log' => $log];
    }

    protected static function phpCli()
    {
        $override = trim((string)getenv('PHP_IPA_WORKER_BINARY'));
        if ($override !== '') {
            if (!is_file($override) || !is_executable($override)) {
                throw new \RuntimeException('PHP_IPA_WORKER_BINARY 不可执行：' . $override);
            }
            return $override;
        }

        // PHP_BINDIR belongs to the running FPM installation. On BaoTa PHP 7.0 this resolves
        // to /www/server/php/70/bin and therefore avoids /usr/bin/php -> PHP 8.x.
        $candidate = rtrim(PHP_BINDIR, '/\\') . DIRECTORY_SEPARATOR . 'php';
        if (is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }

        throw new \RuntimeException('未找到与站点 PHP-FPM 同版本的 PHP CLI；可设置 PHP_IPA_WORKER_BINARY');
    }
}
