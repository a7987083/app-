<?php

namespace app\common\library\Ipa;

use think\Db;

/**
 * Run IPA scan and parse queues inside the current PHP-FPM process.
 *
 * HTTP requests only schedule shutdown consumers. Under PHP-FPM the response
 * is flushed first, then the same FPM worker continues consuming database
 * queues. No child PHP process is started.
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

            $settings = IpaOpsSettings::all();
            if (!empty($settings['parse_enabled'])) {
                self::drainParseQueue(self::workerId('parse'));
            }
        });

        return ['started' => true, 'status' => 'scheduled', 'worker_id' => $workerId];
    }

    public static function ensureParseWorker()
    {
        $settings = IpaOpsSettings::all();
        if (empty($settings['parse_enabled'])) {
            return ['started' => false, 'status' => 'paused', 'worker_id' => ''];
        }

        $snapshot = WorkerState::snapshot(isset($settings['worker_alive_seconds']) ? (int)$settings['worker_alive_seconds'] : 180);
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

        $workerId = self::workerId('parse');
        self::$parseScheduled = true;
        WorkerState::heartbeat('parse', $workerId, 'scheduled', 0);

        register_shutdown_function(function () use ($workerId) {
            self::finishHttpRequest();
            self::drainParseQueue($workerId);
        });

        return ['started' => true, 'status' => 'scheduled', 'worker_id' => $workerId];
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

    public static function drainParseQueue($workerId = '', $maxItems = 0)
    {
        $workerId = trim((string)$workerId);
        if ($workerId === '') {
            $workerId = self::workerId('parse');
        }
        $maxItems = max(0, (int)$maxItems);
        $processed = 0;

        try {
            self::preflightParseSecrets();
            WorkerState::heartbeat('parse', $workerId, 'idle', 0);

            while (true) {
                $settings = IpaOpsSettings::all();
                if (empty($settings['parse_enabled'])) {
                    break;
                }

                $asset = self::claimParseAsset($workerId);
                if (!$asset) {
                    break;
                }

                WorkerState::heartbeat('parse', $workerId, 'working', (int)$asset['id']);
                $source = Db::name('ipa_source')->where('id', (int)$asset['source_id'])->find();
                if (!$source || !(int)$source['enabled']) {
                    self::requeueParseAsset((int)$asset['id'], 'OpenList 数据源不存在或已停用，已暂停解析');
                    WorkerState::heartbeat('parse', $workerId, 'idle', 0);
                    continue;
                }

                try {
                    $token = empty($source['token_ciphertext']) ? '' : SecretBox::decrypt((string)$source['token_ciphertext']);
                    IpaParserService::parseAsset((int)$asset['id'], $source, $token);
                    IpaOpsSettings::recordAttempt((int)$asset['id'], $workerId, 'success', '');
                    try {
                        IpaCompareService::refreshAsset((int)$asset['id']);
                    } catch (\Exception $compareError) {
                    }
                } catch (\Exception $e) {
                    IpaParserService::markParseError((int)$asset['id'], $e);
                    IpaOpsSettings::recordAttempt((int)$asset['id'], $workerId, 'failed', $e->getMessage());
                }

                $processed++;
                WorkerState::heartbeat('parse', $workerId, 'idle', 0);
                if ($maxItems > 0 && $processed >= $maxItems) {
                    break;
                }
            }
        } catch (\Exception $e) {
        }

        try {
            WorkerState::heartbeat('parse', $workerId, 'stopped', 0);
        } catch (\Exception $ignored) {
        }
        return $processed;
    }

    protected static function claimParseAsset($workerId)
    {
        $now = time();
        Db::startTrans();
        try {
            Db::name('ipa_asset')
                ->where('status', 'parsing')
                ->where('updated_at', '<', $now - 600)
                ->update(['status' => 'discovered', 'updated_at' => $now]);

            $sourceIds = Db::name('ipa_source')->where('enabled', 1)->column('id');
            if (!$sourceIds) {
                Db::commit();
                return null;
            }

            $asset = Db::name('ipa_asset')
                ->where('status', 'discovered')
                ->where('source_id', 'in', $sourceIds)
                ->order('id asc')
                ->lock(true)
                ->find();
            if (!$asset) {
                Db::commit();
                return null;
            }

            Db::name('ipa_asset')->where('id', (int)$asset['id'])->update([
                'status' => 'parsing',
                'last_error' => null,
                'updated_at' => $now,
            ]);
            Db::commit();
            return $asset;
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    protected static function preflightParseSecrets()
    {
        $sources = Db::name('ipa_source')
            ->field('id,token_ciphertext')
            ->where('enabled', 1)
            ->where('token_ciphertext', '<>', '')
            ->select();
        if (!$sources) {
            return;
        }
        SecretBox::assertConfigured();
        foreach ($sources as $source) {
            SecretBox::decrypt((string)$source['token_ciphertext']);
        }
    }

    protected static function requeueParseAsset($assetId, $message)
    {
        Db::name('ipa_asset')->where('id', (int)$assetId)->update([
            'status' => 'discovered',
            'last_error' => substr((string)$message, 0, 2000),
            'updated_at' => time(),
        ]);
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
