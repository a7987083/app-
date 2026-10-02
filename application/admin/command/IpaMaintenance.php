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
        $apiLogRetentionDays = max(1, min(3650, (int)Config::get('ipa_data_center.api_request_log_retention_days')));
        $scanItemRetentionDays = max(1, min(3650, (int)Config::get('ipa_data_center.scan_item_retention_days')));
        $scanJobRetentionDays = max(1, min(3650, (int)Config::get('ipa_data_center.scan_job_retention_days')));
        $parseAttemptRetentionDays = max(1, min(3650, (int)Config::get('ipa_data_center.parse_attempt_retention_days')));
        $logBefore = $now - ($retentionDays * 86400);
        $apiLogBefore = $now - ($apiLogRetentionDays * 86400);
        $sessionBefore = $now - 86400;
        $challengeBefore = $now - self::CHALLENGE_RETENTION_SECONDS;
        $deviceKeyBefore = $now - self::DEVICE_KEY_RETENTION_SECONDS;
        $scanItemBefore = $now - ($scanItemRetentionDays * 86400);
        $scanJobBefore = $now - ($scanJobRetentionDays * 86400);
        $parseAttemptBefore = $now - ($parseAttemptRetentionDays * 86400);

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
        $apiLogDeleted = self::deleteExpiredById(
            'api_request_log',
            'addtime',
            $apiLogBefore
        );
        $parseAttemptDeleted = self::deleteExpiredById(
            'ipa_parse_attempt',
            'created_at',
            $parseAttemptBefore
        );
        $scanItemDeleted = self::deleteTerminalScanItems($scanItemBefore);
        $scanJobDeleted = self::deleteTerminalScanJobs($scanJobBefore);

        $output->info(sprintf(
            'maintenance complete challenge=%d session=%d verify_log=%d api_log=%d device_key=%d parse_attempt=%d scan_item=%d scan_job=%d retention_days=%d api_log_retention_days=%d',
            (int)$challengeDeleted,
            (int)$sessionDeleted,
            (int)$logDeleted,
            (int)$apiLogDeleted,
            (int)$deviceKeyDeleted,
            (int)$parseAttemptDeleted,
            (int)$scanItemDeleted,
            (int)$scanJobDeleted,
            $retentionDays,
            $apiLogRetentionDays
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
    protected static function deleteTerminalScanItems($before)
    {
        $deleted = 0;
        $terminal = ['completed', 'completed_with_errors', 'cancelled'];

        for ($batch = 0; $batch < self::DELETE_MAX_BATCHES; $batch++) {
            $jobIds = Db::name('ipa_scan_job')
                ->where('status', 'in', $terminal)
                ->where('updated_at', '<', (int)$before)
                ->order('id asc')
                ->limit(500)
                ->column('id');

            if (!$jobIds) {
                break;
            }

            $ids = Db::name('ipa_scan_item')
                ->where('job_id', 'in', array_map('intval', $jobIds))
                ->order('id asc')
                ->limit(self::DELETE_BATCH_SIZE)
                ->column('id');

            if (!$ids) {
                break;
            }

            $count = Db::name('ipa_scan_item')
                ->where('id', 'in', array_map('intval', $ids))
                ->delete();

            $deleted += (int)$count;
            if (count($ids) < self::DELETE_BATCH_SIZE) {
                break;
            }
        }

        return $deleted;
    }

    protected static function deleteTerminalScanJobs($before)
    {
        $deleted = 0;
        $terminal = ['completed', 'completed_with_errors', 'cancelled'];

        $jobIds = Db::name('ipa_scan_job')
            ->where('status', 'in', $terminal)
            ->where('updated_at', '<', (int)$before)
            ->order('id asc')
            ->limit(1000)
            ->column('id');

        foreach ($jobIds ?: [] as $jobId) {
            $jobId = (int)$jobId;
            if ($jobId <= 0) {
                continue;
            }
            $remaining = (int)Db::name('ipa_scan_item')->where('job_id', $jobId)->count();
            if ($remaining > 0) {
                continue;
            }
            $deleted += (int)Db::name('ipa_scan_job')
                ->where('id', $jobId)
                ->where('status', 'in', $terminal)
                ->delete();
        }

        return $deleted;
    }

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
