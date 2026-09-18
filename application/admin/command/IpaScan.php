<?php

namespace app\admin\command;

use app\common\library\IpaScanService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class IpaScan extends Command
{
    protected function configure()
    {
        $this->setName('ipa:scan')
            ->addOption('task', null, Option::VALUE_OPTIONAL, 'Run one task ID', null)
            ->addOption('pending', null, Option::VALUE_NONE, 'Run one queued/interrupted task')
            ->addOption('schedule', null, Option::VALUE_NONE, 'Create due scheduled tasks and run one')
            ->setDescription('Phase 20 OpenList IPA discovery worker');
    }

    protected function execute(Input $input, Output $output)
    {
        IpaScanService::markStaleInterrupted();
        $taskId=(int)$input->getOption('task');
        if ($input->getOption('schedule')) {
            $created=IpaScanService::createDueScheduledTasks();
            if ($created) $output->info('Created scheduled tasks: '.implode(',',$created));
        }
        if (!$taskId && ($input->getOption('pending') || $input->getOption('schedule'))) {
            $task=IpaScanService::nextPendingTask();
            $taskId=$task?(int)$task['id']:0;
        }
        if (!$taskId) { $output->info('No IPA scan task to run.'); return; }
        $output->info('Running IPA scan task #'.$taskId);
        $result=IpaScanService::runTask($taskId);
        $output->info('Task #'.$taskId.' => '.$result['state'].' / '.$result['stage']);
    }
}
