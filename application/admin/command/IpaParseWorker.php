<?php

namespace app\admin\command;

use app\common\library\Ipa\WorkerState;
use think\console\Command;
use think\console\Input;
use think\console\Output;

/**
 * Compatibility command for 2426 parser migration.
 *
 * The 2425 parser has been retired. Keeping the command registered prevents old
 * cron/supervisor entries from crashing during the rolling upgrade, while also
 * guaranteeing that legacy parsing code cannot claim IPA assets.
 */
class IpaParseWorker extends Command
{
    protected function configure()
    {
        $this->setName('ipa:parse-worker')
            ->setDescription('IPA Parser V2 CLI entry (legacy parser retired)');
    }

    protected function execute(Input $input, Output $output)
    {
        $workerId = gethostname() . ':' . getmypid();
        try {
            WorkerState::heartbeat('parse', $workerId, 'stopped', 0);
        } catch (\Exception $ignored) {
        }
        $output->warning('Legacy IPA parser retired in 2026092426; Parser V2 has not been activated by this migration commit.');
        return 0;
    }
}
