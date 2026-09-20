<?php

namespace app\admin\command;

use app\common\library\IpaWorkerService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class IpaWorker extends Command
{
    protected function configure()
    {
        $this->setName('ipa:worker')
            ->addOption('once', null, Option::VALUE_NONE, 'Consume at most one queued worker job and exit')
            ->addOption('sleep', null, Option::VALUE_OPTIONAL, 'Idle sleep seconds (1-30)', 2)
            ->setDescription('Persistent Phase 20 IPA background worker');
    }

    protected function execute(Input $input, Output $output)
    {
        $once=(bool)$input->getOption('once');
        $sleep=max(1,min(30,(int)$input->getOption('sleep')));
        $output->info('Starting IPA worker'.($once?' (once)':'').' ...');
        $count=IpaWorkerService::runLoop($once,$sleep);
        if($once)$output->info('IPA worker consumed '.$count.' job(s).');
    }
}
