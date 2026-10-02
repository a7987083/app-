<?php

namespace app\admin\command;

use think\Config;
use think\Db;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class IpaMaintenance extends Command
{
    const CHALLENGE_RETENTION_SECONDS = 86400; // Keep consumed/expired challenges for at most 1 day.
    const DELETE_BATCH_SIZE = 5000;
    const DELETE_MAX_BATCHES = 20;
    const DEVICE_KEY_RETENTION_SECONDS = 31536000; // 365 days

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
        $challengeBefore = $now - self::CHALLENGE_RETENTION_SECONDS;
        $deviceKeyBefore = $now - self::DEVICE_KEY_RETENTION_SECONDS;

        // 2430 removed fa_dylib_nonce. Challenge rows are the v3 replay-protection
        // state and must be cleaned explicitly; never query the retired nonce table.
        $challengeDeleted = self::deleteExpiredById(
            'dylib_auth_challenge',
            'expires_at',
            $challengeBefore
        );
        $sessionDeleted = self::deleteExpiredById(
            'dylib_device_session',
            'expires_at',
            $sessionBefore
        );
        $logDeleted = self::deleteExpiredById(
            'dylib_verify_log',
            'created_at',
            $logBefore
        );
        $deviceKeyDeleted = self::deleteStaleDeviceKeys($deviceKeyBefore);

        $output->info(sprintf(
            'maintenance complete challenge=%d session=%d verify_log=%d device_key=%d retention_days=%d',
            (int)$challengeDeleted,
            (int)$sessionDeleted,
            (int)$logDeleted,
            (int)$deviceKeyDeleted,
            $retentionDays
        ));
        return 0;
    }

    /**
     * Delete old rows in bounded primary-key batches.
     *
     * A maintenance run may arrive after a long outage with millions of stale
     * rows. Batching avoids one huge DELETE transaction, limits undo/redo growth,
     * and caps the amount of cleanup work performed by one daily invocation.
     */
    protected static function deleteStaleDeviceKeys($before)
    {
        $deleted = 0;

        for ($batch = 0; $batch < self::DELETE_MAX_BATCHES; $batch++) {
            $ids = Db::name('dylib_device_key')
                ->where('last_used_at', '>', 0)
                ->where('last_used_at', '<', (int)$before)
                ->order('id asc')
                ->limit(self::DELETE_BATCH_SIZE)
                ->column('id');

            if (!$ids) {
                break;
            }

            $count = Db::name('dylib_device_key')
                ->where('id', 'in', array_map('intval', $ids))
                ->delete();

            $deleted += (int)$count;
            if (count($ids) < self::DELETE_BATCH_SIZE) {
                break;
            }
        }

        return $deleted;
    }

    protected static function deleteExpiredById($table, $timeColumn, $before)
    {
        $deleted = 0;

        for ($batch = 0; $batch < self::DELETE_MAX_BATCHES; $batch++) {
            $ids = Db::name($table)
                ->where($timeColumn, '<', (int)$before)
                ->order('id asc')
                ->limit(self::DELETE_BATCH_SIZE)
                ->column('id');

            if (!$ids) {
                break;
            }

            $count = Db::name($table)
                ->where('id', 'in', array_map('intval', $ids))
                ->delete();

            $deleted += (int)$count;
            if (count($ids) < self::DELETE_BATCH_SIZE) {
                break;
            }
        }

        return $deleted;
    }
}
