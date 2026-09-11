<?php

namespace app\common\library\update;

use app\common\library\UpdateIntegrity;

class UpdateManager
{
    protected $root;
    protected $http;

    public function __construct($root)
    {
        $this->root = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
        $this->http = new UpdateHttpClient();
    }

    public function check($sourceName)
    {
        $source = $this->source($sourceName);
        $local = $this->localVersion();
        if ($local === false) {
            return ['code' => 406, 'msg' => '本地版本记录文件获取失败', 'data' => ''];
        }
        $latest = $source->latest();
        if ($latest === false) {
            return ['code' => 406, 'msg' => $source->name() === 'github' ? 'GitHub 更新源访问失败' : '服务器最新版号接口获取失败', 'data' => ''];
        }
        if ($latest === null) {
            return ['code' => 204, 'msg' => $source->name() === 'github' ? 'GitHub 暂无可用稳定更新' : '未获取到版号信息', 'data' => ['has_update' => false, 'source' => $source->name()]];
        }
        $incomplete = false;
        if (!empty($latest['file_sign'])) {
            $incomplete = UpdateIntegrity::signFromRoot($this->root) !== $latest['file_sign'];
        }
        $hasUpdate = intval($latest['version']) > intval($local) || $incomplete;
        $data = [
            'source' => $source->name(),
            'has_update' => $hasUpdate,
            'incomplete' => $incomplete,
            'can_reinstall' => intval($latest['version']) >= intval($local),
            'last_version' => (string)$latest['version'],
            'local_version' => (string)$local,
            'changelog' => isset($latest['desc']) ? (string)$latest['desc'] : '',
            'vn' => isset($latest['vn']) ? (string)$latest['vn'] : '',
            'sha256' => isset($latest['sha256']) ? (string)$latest['sha256'] : '',
        ];
        if ($hasUpdate) {
            return [
                'code' => 200,
                'msg' => $incomplete && intval($latest['version']) <= intval($local)
                    ? '检测到文件与版本号不一致，可能被网站防篡改还原。请关闭防篡改后重新安装。'
                    : (($source->name() === 'github' ? 'GitHub 有新版本 ' : '服务器有新版本 ') . $latest['version']),
                'data' => $data,
            ];
        }
        return ['code' => 204, 'msg' => '已经是最新版本', 'data' => $data];
    }

    public function install($sourceName, $force = false)
    {
        $lock = $this->acquireLock();
        if ($lock === false) {
            return ['code' => 409, 'msg' => '当前已有升级任务进行中，请稍后再试', 'data' => ''];
        }
        try {
            $source = $this->source($sourceName);
            $local = $this->localVersion();
            if ($local === false) {
                throw new \RuntimeException('本地更新日志获取失败');
            }
            $packages = $source->packagesAfter($local, $force);
            if ($packages === false) {
                throw new \RuntimeException($source->name() === 'github' ? 'GitHub 更新包列表获取失败' : '服务器更新日志获取失败');
            }
            if (!$packages) {
                return ['code' => 204, 'msg' => '本地已经是最新版', 'data' => ['source' => $source->name()]];
            }
            $installer = new UpdateInstaller($this->root, $this->http);
            $installed = [];
            foreach ($packages as $package) {
                $installed[] = $installer->install($package, $source->requiresSha256());
            }
            return [
                'code' => 200,
                'msg' => ($source->name() === 'github' ? 'GitHub 在线升级已完成' : '在线升级已完成'),
                'data' => ['source' => $source->name(), 'installed' => $installed],
            ];
        } catch (\Exception $e) {
            error_log('[UpdateManager] install failed source=' . $sourceName . ' error=' . $e->getMessage());
            return ['code' => 406, 'msg' => $e->getMessage(), 'data' => ['source' => $sourceName]];
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
        $file = $this->root . 'ver.json';
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return false;
        }
        $json = json_decode($raw, true);
        if (!is_array($json) || !isset($json['version'])) {
            return false;
        }
        return trim((string)$json['version']);
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
