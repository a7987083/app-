<?php

namespace app\common\library\Ipa;

use think\Db;

/**
 * IPA queue launcher.
 *
 * Scan work keeps the lightweight FPM shutdown compatibility path. IPA parsing
 * itself always runs in the CLI command; web requests only spawn that CLI worker.
 */
class IpaWorkerLauncher
{
    protected static $scanScheduled = false;
    protected static $parseScheduled = false;

    public static function ensureScanWorker()
    {
        $snapshot = WorkerState::snapshot(15);
        if (isset($snapshot['scan']) && !empty($snapshot['scan']['alive'])) {
            return [
                'started' => false,
                'status' => 'alive',
                'worker_id' => (string)$snapshot['scan']['worker_id'],
            ];
        }

        if (self::$scanScheduled) {
            return ['started' => false, 'status' => 'scheduled', 'worker_id' => ''];
        }

        $workerId = self::workerId('scan');
        self::$scanScheduled = true;
        WorkerState::heartbeat('scan', $workerId, 'scheduled', 0);

        register_shutdown_function(function () use ($workerId) {
            self::finishHttpRequest();
            self::drainScanQueue($workerId);
        });

        return ['started' => true, 'status' => 'scheduled', 'worker_id' => $workerId];
    }

    /**
     * Ensure Parser V2 is running without executing parser work in PHP-FPM.
     *
     * The web process only launches `php think ipa:parse-worker --scheduled` in
     * the background. The CLI worker still owns serial claiming, stale recovery,
     * OpenList access and parsing. systemd remains optional as a long-running
     * supervisor, not a prerequisite for normal admin-triggered parsing.
     */
    public static function ensureParseWorker()
    {
        $settings = IpaOpsSettings::all();
        if (empty($settings['parse_enabled'])) {
            return ['started' => false, 'status' => 'disabled', 'worker_id' => ''];
        }

        $snapshot = WorkerState::snapshot($settings['worker_alive_seconds']);
        if (isset($snapshot['parse']) && !empty($snapshot['parse']['alive'])) {
            return [
                'started' => false,
                'status' => 'alive',
                'worker_id' => (string)$snapshot['parse']['worker_id'],
            ];
        }

        if (self::$parseScheduled) {
            return ['started' => false, 'status' => 'scheduled', 'worker_id' => ''];
        }

        $sourceIds = Db::name('ipa_source')->where('enabled', 1)->column('id');
        if (!$sourceIds) {
            return ['started' => false, 'status' => 'no_source', 'worker_id' => ''];
        }
        $pending = (int)Db::name('ipa_asset')
            ->where('status', 'discovered')
            ->where('source_id', 'in', $sourceIds)
            ->count();
        if ($pending <= 0) {
            return ['started' => false, 'status' => 'idle', 'worker_id' => ''];
        }

        if (!self::functionAvailable('exec')) {
            return ['started' => false, 'status' => 'external_required', 'worker_id' => '', 'reason' => 'exec_disabled'];
        }

        $root = dirname(__DIR__, 4);
        $think = $root . DIRECTORY_SEPARATOR . 'think';
        if (!is_file($think)) {
            return ['started' => false, 'status' => 'external_required', 'worker_id' => '', 'reason' => 'think_not_found'];
        }

        $php = self::resolveCliPhpBinary();
        if ($php === '') {
            return ['started' => false, 'status' => 'external_required', 'worker_id' => '', 'reason' => 'cli_php_not_found'];
        }

        $workerId = self::workerId('parse-launch');
        self::$parseScheduled = true;
        WorkerState::heartbeat('parse', $workerId, 'scheduled', 0);

        $command = escapeshellarg($php)
            . ' ' . escapeshellarg($think)
            . ' ipa:parse-worker --scheduled'
            . ' > /dev/null 2>&1 & echo $!';
        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);
        $pid = isset($output[0]) ? (int)trim((string)$output[0]) : 0;
        if ($exitCode !== 0 || $pid <= 0) {
            self::$parseScheduled = false;
            WorkerState::heartbeat('parse', $workerId, 'stopped', 0);
            return ['started' => false, 'status' => 'external_required', 'worker_id' => '', 'reason' => 'spawn_failed'];
        }

        return [
            'started' => true,
            'status' => 'scheduled',
            'worker_id' => $workerId,
            'pid' => $pid,
        ];
    }

    public static function drainQueue($workerId = '', $maxItems = 0)
    {
        return self::drainScanQueue($workerId, $maxItems);
    }

    public static function drainScanQueue($workerId = '', $maxItems = 0)
    {
        $workerId = trim((string)$workerId);
        if ($workerId === '') {
            $workerId = self::workerId('scan');
        }
        $maxItems = max(0, (int)$maxItems);
        $processed = 0;

        try {
            WorkerState::heartbeat('scan', $workerId, 'idle', 0);
            while (true) {
                $item = IpaScanService::claimOne($workerId);
                if (!$item) {
                    $nextAt = (int)Db::name('ipa_scan_item')
                        ->where('status', 'pending')
                        ->min('available_at');
                    if ($nextAt <= 0) {
                        break;
                    }
                    $sleep = max(1, min(5, $nextAt - time()));
                    WorkerState::heartbeat('scan', $workerId, 'idle', 0);
                    sleep($sleep);
                    continue;
                }

                WorkerState::heartbeat('scan', $workerId, 'working', (int)$item['id']);
                IpaScanService::setJobContext((int)$item['job_id']);
                try {
                    IpaScanService::processItem($item, function (array $source) {
                        return empty($source['token_ciphertext'])
                            ? ''
                            : SecretBox::decrypt($source['token_ciphertext']);
                    });
                } catch (\Exception $e) {
                    IpaScanService::failItem($item, $e);
                }

                $processed++;
                WorkerState::heartbeat('scan', $workerId, 'idle', 0);
                if ($maxItems > 0 && $processed >= $maxItems) {
                    break;
                }
            }
        } catch (\Exception $e) {
        }

        try {
            WorkerState::heartbeat('scan', $workerId, 'stopped', 0);
        } catch (\Exception $ignored) {
        }

        // Scan/discovery and parsing remain separate queues. Once the scan queue
        // has produced/updated assets, only kick the CLI parser; never parse in
        // this FPM/shutdown worker.
        if ($processed > 0) {
            try {
                self::ensureParseWorker();
            } catch (\Exception $ignored) {
            }
        }
        return $processed;
    }

    protected static function resolveCliPhpBinary()
    {
        $candidates = [];

        if (defined('PHP_BINARY') && PHP_BINARY !== '') {
            $binary = (string)PHP_BINARY;
            if (PHP_SAPI === 'cli' && is_file($binary) && is_executable($binary)) {
                return $binary;
            }

            // Under PHP-FPM, PHP_BINARY normally points at .../sbin/php-fpm.
            // Prefer the matching CLI binary from the same PHP installation.
            $installRoot = dirname(dirname($binary));
            $candidates[] = $installRoot . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'php';
        }

        if (defined('PHP_BINDIR') && PHP_BINDIR !== '') {
            $candidates[] = rtrim((string)PHP_BINDIR, '/\\') . DIRECTORY_SEPARATOR . 'php';
        }

        // Baota/BT Panel versioned PHP layout, e.g. /www/server/php/70/bin/php.
        if (defined('PHP_MAJOR_VERSION') && defined('PHP_MINOR_VERSION')) {
            $candidates[] = '/www/server/php/' . PHP_MAJOR_VERSION . PHP_MINOR_VERSION . '/bin/php';
        }

        $candidates[] = '/usr/bin/php';
        $candidates[] = '/usr/local/bin/php';

        foreach (array_unique($candidates) as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    protected static function functionAvailable($name)
    {
        if (!function_exists($name)) {
            return false;
        }
        $disabled = array_filter(array_map('trim', explode(',', (string)ini_get('disable_functions'))));
        return !in_array($name, $disabled, true);
    }

    protected static function workerId($type)
    {
        return 'fpm-' . (string)$type . ':' . gethostname() . ':' . getmypid() . ':' . substr(md5(uniqid('', true)), 0, 8);
    }

    protected static function finishHttpRequest()
    {
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
        @ignore_user_abort(true);
        @set_time_limit(0);
    }
}
