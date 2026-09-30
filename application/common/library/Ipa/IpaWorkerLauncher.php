<?php

namespace app\common\library\Ipa;

use think\Db;

/**
 * IPA queue launcher.
 *
 * 2026092426: legacy IPA parsing inside PHP-FPM has been retired. HTTP requests
 * may still drain the lightweight scan queue for compatibility, but parsing is
 * never executed from a shutdown handler. Parser V2 is owned by the CLI worker.
 */
class IpaWorkerLauncher
{
    protected static $scanScheduled = false;

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
     * Kept only as a compatibility surface for callers from older deployments.
     * 2426 must never start or drain the retired parser from PHP-FPM.
     */
    public static function ensureParseWorker()
    {
        return [
            'started' => false,
            'status' => 'cli_only',
            'worker_id' => '',
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
        return $processed;
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
