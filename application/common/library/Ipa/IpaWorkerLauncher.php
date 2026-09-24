<?php

namespace app\common\library\Ipa;

use think\Db;

/**
 * Schedule IPA scan queue consumption inside the current PHP-FPM process.
 *
 * This class intentionally does not spawn a separate php/think process. The
 * HTTP request creates the scan job as usual; after the response is flushed,
 * the current FPM worker continues consuming the existing database queue.
 */
class IpaWorkerLauncher
{
    protected static $scheduled = false;

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

        if (self::$scheduled) {
            return ['started' => false, 'status' => 'scheduled', 'worker_id' => ''];
        }

        $workerId = 'fpm:' . gethostname() . ':' . getmypid() . ':' . substr(md5(uniqid('', true)), 0, 8);
        self::$scheduled = true;
        WorkerState::heartbeat('scan', $workerId, 'scheduled', 0);

        register_shutdown_function(function () use ($workerId) {
            // Under PHP-FPM this flushes the HTTP response first, matching the
            // existing Node/OpenList project's "return now, continue in API process"
            // behavior without launching a child process.
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            @ignore_user_abort(true);
            @set_time_limit(0);
            self::drainQueue($workerId);
        });

        return ['started' => true, 'status' => 'scheduled', 'worker_id' => $workerId];
    }

    /**
     * Consume queued scan items in-process. $maxItems is mainly useful for
     * regression tests; 0 means drain until the queue is empty or owned by
     * another worker.
     */
    public static function drainQueue($workerId = '', $maxItems = 0)
    {
        $workerId = trim((string)$workerId);
        if ($workerId === '') {
            $workerId = 'fpm:' . gethostname() . ':' . getmypid();
        }
        $maxItems = max(0, (int)$maxItems);
        $processed = 0;

        try {
            WorkerState::heartbeat('scan', $workerId, 'idle', 0);
            while (true) {
                $item = IpaScanService::claimOne($workerId);
                if (!$item) {
                    // Failed items use a short retry backoff. Stay alive while
                    // pending work still exists so a transient OpenList error
                    // does not leave the job stranded until another click.
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
            // Do not throw from a shutdown handler. Queue/item state already
            // records per-item failures; an unexpected outer failure simply
            // marks this in-process consumer stopped so a later request can resume.
        }

        try {
            WorkerState::heartbeat('scan', $workerId, 'stopped', 0);
        } catch (\Exception $ignored) {
        }
        return $processed;
    }
}
