<?php

namespace app\admin\command;

use think\Config;
use think\Db;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class IpaMaintenance extends Command
{
    protected function configure()
    {
        $this->setName('ipa:maintenance')
            ->setDescription('Cleanup expired IPA/Dylib runtime data');
    }

    protected function execute(Input $input, Output $output)
    {
        $now = time();
        $retentionDays = max(1, min(3650, (int)Config::get('ipa_data_center.verify_log_retention_days')));
        $logBefore = $now - ($retentionDays * 86400);
        $sessionBefore = $now - 86400;

        $nonceDeleted = Db::name('dylib_nonce')->where('expires_at', '<', $now)->delete();
        $sessionDeleted = Db::name('dylib_device_session')
            ->where('expires_at', '<', $sessionBefore)
            ->delete();
        $logDeleted = Db::name('dylib_verify_log')->where('created_at', '<', $logBefore)->delete();

        $output->info(sprintf(
            'maintenance complete nonce=%d session=%d verify_log=%d retention_days=%d',
            (int)$nonceDeleted,
            (int)$sessionDeleted,
            (int)$logDeleted,
            $retentionDays
        ));
        return 0;
    }
}
