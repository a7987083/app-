<?php

namespace app\admin\command;

use app\common\library\update\UpdateOps;
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
    const RUNTIME_LOG_RETENTION_SECONDS = 2592000; // 30 days
    const RUNTIME_LOG_DELETE_LIMIT = 1000;

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
        $auditLogRetentionDays = max(1, min(3650, (int)Config::get('ipa_data_center.audit_log_retention_days')));
        $logBefore = $now - ($retentionDays * 86400);
        $apiLogBefore = $now - ($apiLogRetentionDays * 86400);
        $sessionBefore = $now - 86400;
        $challengeBefore = $now - self::CHALLENGE_RETENTION_SECONDS;
        $deviceKeyBefore = $now - self::DEVICE_KEY_RETENTION_SECONDS;
        $scanItemBefore = $now - ($scanItemRetentionDays * 86400);
        $scanJobBefore = $now - ($scanJobRetentionDays * 86400);
        $parseAttemptBefore = $now - ($parseAttemptRetentionDays * 86400);
        $auditLogBefore = $now - ($auditLogRetentionDays * 86400);

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
        $authorizationEventDeleted = self::deleteExpiredById('authorization_event', 'addtime', $auditLogBefore);
        $cardTransferDeleted = self::deleteExpiredById('card_transfer_log', 'addtime', $auditLogBefore);
        $adminLogDeleted = self::deleteExpiredById('admin_log', 'createtime', $auditLogBefore);
        $sourceChangeDeleted = self::deleteExpiredByPrimaryKey('source_change', 'revision', 'changed_at', $auditLogBefore);
        $runtimeLogDeleted = self::cleanupRuntimeLogs($now - self::RUNTIME_LOG_RETENTION_SECONDS);

        $updateCleanup = ['status' => 0, 'history' => 0, 'backups' => 0, 'bytes' => 0];
        try {
            if (defined('ROOT_PATH')) {
                $updateOps = new UpdateOps(ROOT_PATH);
                $result = $updateOps->cleanup(false);
                if (is_array($result) && isset($result['deleted']) && is_array($result['deleted'])) {
                    $updateCleanup = array_merge($updateCleanup, $result['deleted']);
                }
            }
        } catch (\Exception $e) {
            $output->warning('update retention cleanup failed: ' . $e->getMessage());
        }

        $output->info(sprintf(
            'maintenance complete challenge=%d session=%d verify_log=%d api_log=%d device_key=%d parse_attempt=%d scan_item=%d scan_job=%d authorization_event=%d card_transfer=%d admin_log=%d source_change=%d runtime_log=%d update_status=%d update_history=%d update_backup=%d retention_days=%d api_log_retention_days=%d audit_log_retention_days=%d',
            (int)$challengeDeleted,
            (int)$sessionDeleted,
            (int)$logDeleted,
            (int)$apiLogDeleted,
            (int)$deviceKeyDeleted,
            (int)$parseAttemptDeleted,
            (int)$scanItemDeleted,
            (int)$scanJobDeleted,
            (int)$authorizationEventDeleted,
            (int)$cardTransferDeleted,
            (int)$adminLogDeleted,
            (int)$sourceChangeDeleted,
            (int)$runtimeLogDeleted,
            isset($updateCleanup['status']) ? (int)$updateCleanup['status'] : 0,
            isset($updateCleanup['history']) ? (int)$updateCleanup['history'] : 0,
            isset($updateCleanup['backups']) ? (int)$updateCleanup['backups'] : 0,
            $retentionDays,
            $apiLogRetentionDays,
            $auditLogRetentionDays
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
    protected static function cleanupRuntimeLogs($before)
    {
        if (!defined('LOG_PATH') || !is_dir(LOG_PATH)) {
            return 0;
        }

        $deleted = 0;
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(LOG_PATH, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $item) {
                if ($deleted >= self::RUNTIME_LOG_DELETE_LIMIT) {
                    break;
                }
                if ($item->isLink()) {
                    continue;
                }
                if ($item->isFile()) {
                    $path = $item->getPathname();
                    $mtime = (int)$item->getMTime();
                    if (substr(strtolower($item->getFilename()), -4) === '.log'
                        && $mtime > 0
                        && $mtime < (int)$before
                        && @unlink($path)) {
                        $deleted++;
                    }
                    continue;
                }
                if ($item->isDir()) {
                    @rmdir($item->getPathname());
                }
            }
        } catch (\UnexpectedValueException $e) {
            return $deleted;
        }

        return $deleted;
    }

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
        return self::deleteExpiredByPrimaryKey($table, 'id', $timeColumn, $before);
    }

    protected static function deleteExpiredByPrimaryKey($table, $primaryKey, $timeColumn, $before)
    {
        $deleted = 0;

        try {
            for ($batch = 0; $batch < self::DELETE_MAX_BATCHES; $batch++) {
                $ids = Db::name($table)
                    ->where($timeColumn, '<', (int)$before)
                    ->order($primaryKey . ' asc')
                    ->limit(self::DELETE_BATCH_SIZE)
                    ->column($primaryKey);

                if (!$ids) {
                    break;
                }

                $count = Db::name($table)
                    ->where($primaryKey, 'in', array_map('intval', $ids))
                    ->delete();

                $deleted += (int)$count;
                if (count($ids) < self::DELETE_BATCH_SIZE) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            return $deleted;
        }

        return $deleted;
    }
}
