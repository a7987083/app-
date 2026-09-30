<?php

namespace app\admin\command;

use app\common\library\Ipa\IpaOpsSettings;
use app\common\library\Ipa\IpaParserV2Service;
use app\common\library\Ipa\SecretBox;
use app\common\library\Ipa\WorkerState;
use think\Db;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * Parser V2: CLI-only fast metadata worker.
 *
 * No PHP-FPM shutdown execution, no Mach-O enrichment, no hashing and no
 * software-source comparison. One bad IPA is isolated to its own asset row.
 */
class IpaParseWorker extends Command
{
    protected function configure()
    {
        $this->setName('ipa:parse-worker')
            ->addOption('once', null, Option::VALUE_NONE, 'Parse at most one IPA and exit')
            ->addOption('scheduled', null, Option::VALUE_NONE, 'Parse a bounded batch and exit')
            ->addOption('limit', null, Option::VALUE_OPTIONAL, 'Maximum IPA count for --scheduled (1-20)', 20)
            ->addOption('sleep', null, Option::VALUE_OPTIONAL, 'Idle sleep seconds', 2)
            ->setDescription('Run IPA Parser V2 fast metadata worker (CLI only)');
    }

    protected function execute(Input $input, Output $output)
    {
        $once = (bool)$input->getOption('once');
        $scheduled = (bool)$input->getOption('scheduled');
        $scheduledLimit = max(1, min(20, (int)$input->getOption('limit')));
        $sleep = max(1, min(30, (int)$input->getOption('sleep')));
        $workerId = gethostname() . ':' . getmypid();
        $processed = 0;

        try {
            $this->preflightSecrets();
        } catch (\Exception $e) {
            $output->error('IPA Parser V2 startup check failed: ' . $e->getMessage());
            try { WorkerState::heartbeat('parse', $workerId, 'stopped', 0); } catch (\Exception $ignored) {}
            return 2;
        }

        $output->info('IPA Parser V2 started: ' . $workerId);
        WorkerState::heartbeat('parse', $workerId, 'idle', 0);

        while (true) {
            $settings = IpaOpsSettings::all();
            if (empty($settings['parse_enabled'])) {
                break;
            }

            WorkerState::heartbeat('parse', $workerId, 'idle', 0);
            $asset = $this->claimAsset($settings);
            if (!$asset) {
                if ($once || $scheduled) {
                    break;
                }
                sleep($sleep);
                continue;
            }

            WorkerState::heartbeat('parse', $workerId, 'working', (int)$asset['id']);
            $source = Db::name('ipa_source')->where('id', (int)$asset['source_id'])->find();
            if (!$source || !(int)$source['enabled']) {
                $this->requeueAsset((int)$asset['id'], 'OpenList source is missing or disabled');
                $processed++;
                if ($once || ($scheduled && $processed >= $scheduledLimit)) {
                    break;
                }
                continue;
            }

            $startedAt = microtime(true);
            try {
                $token = empty($source['token_ciphertext']) ? '' : SecretBox::decrypt((string)$source['token_ciphertext']);
                $meta = IpaParserV2Service::parseAsset((int)$asset['id'], $source, $token);
                IpaOpsSettings::recordAttempt((int)$asset['id'], $workerId, 'success', '');
                $elapsedMs = (int)round((microtime(true) - $startedAt) * 1000);
                $output->info(sprintf(
                    'parsed-v2 asset=%d bundle=%s version=%s elapsed_ms=%d range_bytes=%d range_requests=%d',
                    $asset['id'],
                    $meta['bundle_id'],
                    $meta['app_version'],
                    $elapsedMs,
                    isset($meta['range_bytes']) ? (int)$meta['range_bytes'] : 0,
                    isset($meta['range_requests']) ? (int)$meta['range_requests'] : 0
                ));
            } catch (\Exception $e) {
                IpaParserV2Service::markParseError((int)$asset['id'], $e);
                IpaOpsSettings::recordAttempt((int)$asset['id'], $workerId, 'failed', $e->getMessage());
                $output->error(sprintf('parse-v2 failed asset=%d: %s', $asset['id'], $e->getMessage()));
            }

            $processed++;
            WorkerState::heartbeat('parse', $workerId, 'idle', 0);
            if ($once || ($scheduled && $processed >= $scheduledLimit)) {
                break;
            }
        }

        WorkerState::heartbeat('parse', $workerId, 'stopped', 0);
        return 0;
    }

    protected function preflightSecrets()
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

    protected function claimAsset(array $settings)
    {
        $now = time();
        $retrySeconds = max(60, (int)$settings['parse_retry_minutes'] * 60);
        Db::startTrans();
        try {
            // Crash recovery only; the active CLI worker heartbeats separately.
            Db::name('ipa_asset')
                ->where('status', 'parsing')
                ->where('updated_at', '<', $now - 600)
                ->update(['status' => 'discovered', 'updated_at' => $now]);

            // Reference-project cooldown semantics: a failed IPA is isolated from
            // the queue, then becomes eligible again only after parse_retry_minutes.
            // `updated_at` is written by markParseError(), so no schema migration is
            // required and old databases upgrade safely.
            Db::name('ipa_asset')
                ->where('status', 'parse_failed')
                ->where('updated_at', '<=', $now - $retrySeconds)
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

    protected function requeueAsset($assetId, $message)
    {
        Db::name('ipa_asset')->where('id', (int)$assetId)->update([
            'status' => 'discovered',
            'last_error' => substr((string)$message, 0, 2000),
            'updated_at' => time(),
        ]);
    }
}
