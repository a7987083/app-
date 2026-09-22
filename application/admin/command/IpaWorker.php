<?php

namespace app\admin\command;

use app\common\library\Ipa\IpaScanService;
use app\common\library\Ipa\SecretBox;
use app\common\library\Ipa\WorkerState;
use think\Db;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class IpaWorker extends Command
{
    protected function configure()
    {
        $this->setName('ipa:worker')
            ->addOption('once', null, Option::VALUE_NONE, 'Process at most one queue item and exit')
            ->addOption('sleep', null, Option::VALUE_OPTIONAL, 'Idle sleep seconds', 2)
            ->setDescription('Run IPA Data Center queue worker');
    }

    protected function execute(Input $input, Output $output)
    {
        $once = (bool)$input->getOption('once');
        $sleep = max(1, min(30, (int)$input->getOption('sleep')));
        $workerId = gethostname() . ':' . getmypid();

        try {
            $this->preflightSecrets();
        } catch (\Exception $e) {
            $output->error('IPA 扫描 Worker 启动检查失败：' . $e->getMessage());
            try { WorkerState::heartbeat('scan', $workerId, 'stopped', 0); } catch (\Exception $ignored) {}
            return 2;
        }

        $output->info('IPA worker started: ' . $workerId);
        WorkerState::heartbeat('scan', $workerId, 'idle', 0);

        do {
            WorkerState::heartbeat('scan', $workerId, 'idle', 0);
            $item = IpaScanService::claimOne($workerId);
            if (!$item) {
                if ($once) {
                    WorkerState::heartbeat('scan', $workerId, 'stopped', 0);
                    return 0;
                }
                sleep($sleep);
                continue;
            }

            WorkerState::heartbeat('scan', $workerId, 'working', (int)$item['id']);
            IpaScanService::setJobContext((int)$item['job_id']);
            try {
                IpaScanService::processItem($item, function (array $source) {
                    return empty($source['token_ciphertext']) ? '' : SecretBox::decrypt($source['token_ciphertext']);
                });
                $output->info(sprintf('done item=%d type=%s path=%s', $item['id'], $item['item_type'], $item['path']));
            } catch (\Exception $e) {
                IpaScanService::failItem($item, $e);
                $output->error(sprintf('failed item=%d: %s', $item['id'], $e->getMessage()));
            }
            WorkerState::heartbeat('scan', $workerId, 'idle', 0);

            if ($once) {
                break;
            }
        } while (true);

        WorkerState::heartbeat('scan', $workerId, 'stopped', 0);
        return 0;
    }

    protected function preflightSecrets()
    {
        $sources = Db::name('ipa_source')
            ->field('id,token_ciphertext')
            ->where('enabled', 1)
            ->where('token_ciphertext', '<>', '')
            ->select();
        if (!$sources) return;
        SecretBox::assertConfigured();
        foreach ($sources as $source) {
            SecretBox::decrypt((string)$source['token_ciphertext']);
        }
    }
}
