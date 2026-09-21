<?php

namespace app\admin\command;

use app\common\library\Ipa\IpaScanService;
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
        $output->info('IPA worker started: ' . $workerId);

        do {
            $item = IpaScanService::claimOne($workerId);
            if (!$item) {
                if ($once) {
                    return 0;
                }
                sleep($sleep);
                continue;
            }

            IpaScanService::setJobContext((int)$item['job_id']);
            try {
                IpaScanService::processItem($item);
                $output->info(sprintf('done item=%d type=%s path=%s', $item['id'], $item['item_type'], $item['path']));
            } catch (\Exception $e) {
                IpaScanService::failItem($item, $e);
                $output->error(sprintf('failed item=%d: %s', $item['id'], $e->getMessage()));
            }

            if ($once) {
                break;
            }
        } while (true);

        return 0;
    }
}
