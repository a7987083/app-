<?php

namespace app\common\library\Ipa;

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
        $process = @proc_open($cmd, $descriptors, $pipes, $root, null);
        if (!is_resource($process)) {
            WorkerState::heartbeat('scan', $launcherId, 'stopped', 0);
            throw new \RuntimeException('无法创建 IPA 扫描 Worker 进程');
        }
        $exit = proc_close($process);
        if ($exit !== 0) {
            WorkerState::heartbeat('scan', $launcherId, 'stopped', 0);
            throw new \RuntimeException('IPA 扫描 Worker 启动命令失败，exit=' . (int)$exit . '；请查看 ' . $log);
        }

        return ['started' => true, 'status' => 'starting', 'worker_id' => $launcherId, 'php' => $php, 'log' => $log];
    }

    protected static function phpCli()
    {
        $override = trim((string)getenv('PHP_IPA_WORKER_BINARY'));
        if ($override !== '') {
            return $override;
        }

        // Do not call is_file()/is_executable() here. BaoTa open_basedir usually allows
        // only the site root and /tmp, while PHP_BINDIR is /www/server/php/<ver>/bin.
        // The path is supplied by the currently running PHP-FPM binary itself, so use it
        // directly and let the shell report an execution failure if it is actually invalid.
        $bindir = trim((string)PHP_BINDIR);
        if ($bindir === '') {
            throw new \RuntimeException('无法确定站点 PHP-FPM 的 PHP_BINDIR');
        }
        return rtrim($bindir, '/\\') . DIRECTORY_SEPARATOR . 'php';
    }
}
