<?php

namespace app\admin\command;

use app\common\library\Ipa\IpaParserService;
use app\common\library\Ipa\SecretBox;
use app\common\library\Ipa\WorkerState;
use think\Db;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class IpaParseWorker extends Command
{
    protected function configure()
    {
        $this->setName('ipa:parse-worker')
            ->addOption('once', null, Option::VALUE_NONE, 'Parse at most one IPA and exit')
            ->addOption('sleep', null, Option::VALUE_OPTIONAL, 'Idle sleep seconds', 2)
            ->setDescription('Parse discovered IPA assets with HTTP Range');
    }

    protected function execute(Input $input, Output $output)
    {
        $once = (bool)$input->getOption('once');
        $sleep = max(1, min(30, (int)$input->getOption('sleep')));
        $workerId = gethostname() . ':' . getmypid();

        try {
            $this->preflightSecrets();
        } catch (\Exception $e) {
            $output->error('IPA 解析 Worker 启动检查失败：' . $e->getMessage());
            try {
                WorkerState::heartbeat('parse', $workerId, 'stopped', 0);
            } catch (\Exception $ignored) {
            }
            return 1;
        }

        $output->info('IPA parse worker started: ' . $workerId);
        WorkerState::heartbeat('parse', $workerId, 'idle', 0);

        do {
            WorkerState::heartbeat('parse', $workerId, 'idle', 0);
            $asset = $this->claimAsset($workerId);
            if (!$asset) {
                if ($once) {
                    WorkerState::heartbeat('parse', $workerId, 'stopped', 0);
                    return 0;
                }
                sleep($sleep);
                continue;
            }

            WorkerState::heartbeat('parse', $workerId, 'working', (int)$asset['id']);
            $source = Db::name('ipa_source')->where('id', (int)$asset['source_id'])->find();
            if (!$source || !(int)$source['enabled']) {
                $this->requeueAsset((int)$asset['id'], '数据源已停用或不存在，已暂停解析');
                WorkerState::heartbeat('parse', $workerId, 'idle', 0);
                if ($once) break;
                continue;
            }

            try {
                $token = $this->decryptToken($source);
                $meta = IpaParserService::parseAsset((int)$asset['id'], $source, $token);
                $output->info(sprintf('parsed asset=%d bundle=%s version=%s', $asset['id'], $meta['bundle_id'], $meta['app_version']));
            } catch (\Exception $e) {
                $this->failAsset($asset['id'], $e);
                $output->error(sprintf('parse failed asset=%d: %s', $asset['id'], $e->getMessage()));
            }
            WorkerState::heartbeat('parse', $workerId, 'idle', 0);

            if ($once) break;
        } while (true);
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

    protected function claimAsset($workerId)
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

    protected function decryptToken(array $source)
    {
        if (empty($source['token_ciphertext'])) return '';
        return SecretBox::decrypt((string)$source['token_ciphertext']);
    }

    protected function requeueAsset($assetId, $message)
    {
        Db::name('ipa_asset')->where('id', (int)$assetId)->update([
            'status' => 'discovered',
            'last_error' => substr((string)$message, 0, 2000),
            'updated_at' => time(),
        ]);
    }

    protected function failAsset($assetId, \Exception $e)
    {
        IpaParserService::markParseError((int)$assetId, $e);
    }
}
