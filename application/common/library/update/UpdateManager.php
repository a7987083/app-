<?php

namespace app\common\library\update;

use app\common\library\UpdateIntegrity;

class UpdateManager
{
    protected $root;
    protected $http;
    protected $runtime;

    public function __construct($root)
    {
        $this->root = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
        $this->http = new UpdateHttpClient();
        $this->runtime = new UpdateRuntimeStore($this->root);
    }

    public function check($sourceName)
    {
        $source = $this->source($sourceName);
        $localManifest = $this->localManifest();
        if ($localManifest === false) {
            return ['code' => 406, 'msg' => '本地版本记录文件获取失败', 'data' => ''];
        }
        $local = (string)$localManifest['version'];
        $latest = $source->latest();
        if ($latest === false) {
            return ['code' => 406, 'msg' => $source->name() === 'github' ? 'GitHub 更新源访问失败' : '服务器最新版号接口获取失败', 'data' => ''];
        }
        if ($latest === null) {
            return ['code' => 204, 'msg' => $source->name() === 'github' ? 'GitHub 暂无可用稳定更新' : '未获取到版号信息', 'data' => ['has_update' => false, 'source' => $source->name(), 'local_version' => $local]];
        }

        $incomplete = false;
        if (!empty($localManifest['file_sign'])) {
            $incomplete = UpdateIntegrity::signFromRoot($this->root) !== $localManifest['file_sign'];
        }

        $latestVersion = (string)$latest['version'];
        $hasNewer = intval($latestVersion) > intval($local);
        $canReinstall = intval($latestVersion) >= intval($local);
        $hasUpdate = $hasNewer || ($incomplete && $canReinstall);
        $data = [
            'source' => $source->name(),
            'has_update' => $hasUpdate,
            'incomplete' => $incomplete,
            'can_reinstall' => $canReinstall,
            'last_version' => $latestVersion,
            'local_version' => $local,
            'changelog' => isset($latest['desc']) ? (string)$latest['desc'] : '',
            'vn' => isset($latest['vn']) ? (string)$latest['vn'] : '',
            'sha256' => isset($latest['sha256']) ? (string)$latest['sha256'] : '',
        ];
        if ($hasUpdate) {
            return [
                'code' => 200,
                'msg' => $incomplete && !$hasNewer && $canReinstall
                    ? '检测到当前版本文件与本地版本记录不一致，可重新安装当前版本。'
                    : (($source->name() === 'github' ? 'GitHub 有新版本 ' : '服务器有新版本 ') . $latestVersion),
                'data' => $data,
            ];
        }
        return ['code' => 204, 'msg' => '已经是最新版本', 'data' => $data];
    }

    public function install($sourceName, $force = false, $jobId = '')
    {
        if (!UpdateRuntimeStore::validId($jobId)) {
            $jobId = $this->runtime->newId('update');
        }
        $fromVersion = $this->localVersion();
        if ($fromVersion === false) {
            $fromVersion = '';
        }
        $this->runtime->begin($jobId, $sourceName, $fromVersion);

        $lock = $this->acquireLock();
        if ($lock === false) {
            $this->runtime->fail($jobId, '当前已有升级任务进行中，请稍后再试', false);
            return ['code' => 409, 'msg' => '当前已有升级任务进行中，请稍后再试', 'data' => ['job_id' => $jobId]];
        }

        $installed = [];
        $targetVersion = '';
        try {
            $source = $this->source($sourceName);
            $local = $this->localVersion();
            if ($local === false) {
                throw new \RuntimeException('本地更新日志获取失败');
            }
            $this->runtime->progress($jobId, 'source', 6, '正在读取更新源');
            $packages = $source->packagesAfter($local, $force);
            if ($packages === false) {
                throw new \RuntimeException($source->name() === 'github' ? 'GitHub 更新包列表获取失败' : '服务器更新日志获取失败');
            }
            if (!$packages) {
                $this->runtime->success($jobId, ['from_version' => $local, 'to_version' => $local, 'message' => '本地已经是最新版']);
                return ['code' => 204, 'msg' => '本地已经是最新版', 'data' => ['source' => $source->name(), 'job_id' => $jobId]];
            }
            $last = end($packages);
            $targetVersion = isset($last['version']) ? (string)$last['version'] : '';
            reset($packages);
            $this->runtime->progress($jobId, 'preparing', 8, '已获取更新信息，准备安装', ['to_version' => $targetVersion]);

            $runtime = $this->runtime;
            $progress = function ($stage, $percent, $message, $extra = []) use ($runtime, $jobId) {
                $runtime->progress($jobId, $stage, $percent, $message, is_array($extra) ? $extra : []);
            };
            $installer = new UpdateInstaller($this->root, $this->http, $progress);
            foreach ($packages as $package) {
                $installed[] = $installer->install($package, $source->requiresSha256());
            }

            $backups = [];
            foreach ($installed as $row) {
                if (!empty($row['backup'])) {
                    $backups[] = basename(rtrim(str_replace('\\', '/', $row['backup']), '/'));
                }
            }
            $manifest = $this->localManifest();
            $integrity = is_array($manifest) && !empty($manifest['file_sign']) && UpdateIntegrity::signFromRoot($this->root) === $manifest['file_sign'];
            $historyId = $this->runtime->recordHistory([
                'type' => 'update',
                'status' => 'success',
                'source' => $source->name(),
                'from_version' => (string)$local,
                'to_version' => $targetVersion,
                'backups' => $backups,
                'installed' => $installed,
                'integrity_verified' => $integrity,
            ]);
            $this->runtime->success($jobId, [
                'from_version' => (string)$local,
                'to_version' => $targetVersion,
                'history_id' => $historyId,
                'backups' => $backups,
                'installed' => $installed,
                'integrity_verified' => $integrity,
            ]);
            return [
                'code' => 200,
                'msg' => ($source->name() === 'github' ? 'GitHub 在线升级已完成' : '在线升级已完成'),
                'data' => [
                    'source' => $source->name(),
                    'job_id' => $jobId,
                    'history_id' => $historyId,
                    'from_version' => (string)$local,
                    'to_version' => $targetVersion,
                    'integrity_verified' => $integrity,
                    'installed' => $installed,
                ],
            ];
        } catch (\Exception $e) {
            error_log('[UpdateManager] install failed source=' . $sourceName . ' error=' . $e->getMessage());
            $status = $this->runtime->status($jobId);
            $rolledBack = is_array($status) && !empty($status['rollback']);
            $historyId = $this->runtime->recordHistory([
                'type' => 'update',
                'status' => 'failed',
                'source' => $sourceName,
                'from_version' => (string)$fromVersion,
                'to_version' => $targetVersion,
                'installed' => $installed,
                'rollback' => $rolledBack,
                'error' => $e->getMessage(),
            ]);
            $this->runtime->fail($jobId, $e->getMessage(), $rolledBack, ['history_id' => $historyId]);
            return ['code' => 406, 'msg' => $e->getMessage(), 'data' => ['source' => $sourceName, 'job_id' => $jobId, 'history_id' => $historyId, 'rollback' => $rolledBack]];
        } finally {
            $this->releaseLock($lock);
        }
    }

    public function status($jobId)
    {
        $row = $this->runtime->status($jobId);
        if (!is_array($row)) {
            return ['code' => 404, 'msg' => '未找到更新任务', 'data' => ''];
        }
        return ['code' => 200, 'msg' => 'ok', 'data' => $row];
    }

    public function history($limit = 20)
    {
        return ['code' => 200, 'msg' => 'ok', 'data' => ['list' => $this->runtime->history($limit)]];
    }

    public function rollback($historyId, $jobId = '')
    {
        $entry = $this->runtime->historyById($historyId);
        if (!is_array($entry) || !isset($entry['type']) || $entry['type'] !== 'update' || !isset($entry['status']) || $entry['status'] !== 'success') {
            return ['code' => 404, 'msg' => '未找到可回滚的更新记录', 'data' => ''];
        }
        $backups = isset($entry['backups']) && is_array($entry['backups']) ? $entry['backups'] : [];
        if (!$backups) {
            return ['code' => 406, 'msg' => '该更新记录没有可用备份', 'data' => ''];
        }
        if (!UpdateRuntimeStore::validId($jobId)) {
            $jobId = $this->runtime->newId('rollback');
        }
        $current = $this->localVersion();
        $target = isset($entry['from_version']) ? (string)$entry['from_version'] : '';
        $this->runtime->begin($jobId, 'rollback', (string)$current, $target);

        $lock = $this->acquireLock();
        if ($lock === false) {
            $this->runtime->fail($jobId, '当前已有升级任务进行中，请稍后再试', false);
            return ['code' => 409, 'msg' => '当前已有升级任务进行中，请稍后再试', 'data' => ['job_id' => $jobId]];
        }
        try {
            $reversed = array_reverse($backups);
            $count = count($reversed);
            foreach ($reversed as $index => $backupId) {
                if (!preg_match('/^[A-Za-z0-9_-]+$/', (string)$backupId)) {
                    throw new \RuntimeException('备份标识无效');
                }
                $dir = $this->root . 'runtime' . DIRECTORY_SEPARATOR . 'update_backup' . DIRECTORY_SEPARATOR . $backupId . DIRECTORY_SEPARATOR;
                if (!is_dir($dir)) {
                    throw new \RuntimeException('备份目录不存在: ' . $backupId);
                }
                $progress = 20 + intval((($index + 1) / max(1, $count)) * 60);
                $this->runtime->progress($jobId, 'rollback', $progress, '正在恢复备份 ' . ($index + 1) . '/' . $count);
                (new UpdateBackup($this->root, $dir))->rollback();
            }
            $after = $this->localVersion();
            if ($target !== '' && (string)$after !== $target) {
                throw new \RuntimeException('回滚后的版本号与目标版本不一致');
            }
            $manifest = $this->localManifest();
            $integrity = is_array($manifest) && (!empty($manifest['file_sign']) ? UpdateIntegrity::signFromRoot($this->root) === $manifest['file_sign'] : true);
            if (!$integrity) {
                throw new \RuntimeException('回滚后文件完整性校验失败');
            }
            $rollbackHistoryId = $this->runtime->recordHistory([
                'type' => 'rollback',
                'status' => 'success',
                'source_history_id' => $historyId,
                'from_version' => (string)$current,
                'to_version' => (string)$after,
                'integrity_verified' => true,
            ]);
            $this->runtime->success($jobId, ['history_id' => $rollbackHistoryId, 'from_version' => (string)$current, 'to_version' => (string)$after, 'integrity_verified' => true]);
            return ['code' => 200, 'msg' => '回滚完成', 'data' => ['job_id' => $jobId, 'history_id' => $rollbackHistoryId, 'from_version' => (string)$current, 'to_version' => (string)$after]];
        } catch (\Exception $e) {
            $this->runtime->fail($jobId, $e->getMessage(), false);
            return ['code' => 406, 'msg' => $e->getMessage(), 'data' => ['job_id' => $jobId]];
        } finally {
            $this->releaseLock($lock);
        }
    }

    protected function source($name)
    {
        if ($name === 'github') {
            return new GitHubUpdateSource($this->http);
        }
        return new NuosikeUpdateSource($this->http);
    }

    protected function localVersion()
    {
        $manifest = $this->localManifest();
        return $manifest === false ? false : (string)$manifest['version'];
    }

    protected function localManifest()
    {
        $file = $this->root . 'ver.json';
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return false;
        }
        $json = json_decode($raw, true);
        if (!is_array($json) || !isset($json['version'])) {
            return false;
        }
        return [
            'version' => trim((string)$json['version']),
            'file_sign' => isset($json['file_sign']) ? trim((string)$json['file_sign']) : '',
        ];
    }

    protected function acquireLock()
    {
        $dir = $this->root . 'runtime' . DIRECTORY_SEPARATOR . 'update' . DIRECTORY_SEPARATOR;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return false;
        }
        $fp = @fopen($dir . 'update.lock', 'c+');
        if (!$fp) {
            return false;
        }
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return false;
        }
        ftruncate($fp, 0);
        fwrite($fp, getmypid() . ' ' . date('c'));
        fflush($fp);
        return $fp;
    }

    protected function releaseLock($lock)
    {
        if (is_resource($lock)) {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }
}
