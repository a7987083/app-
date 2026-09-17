<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\AuthorizationPolicy;
use app\common\library\AuthorizationSchema;
use app\common\library\BlacklistPolicy;
use app\common\library\SourceConfigRepository;
use app\common\library\SourceHttpClient;
use think\Db;

/**
 * Authorization operations dashboard.
 */
class Authorization extends Backend
{
    protected $noNeedRight = [];
    protected $layout = 'default';

    public function _initialize()
    {
        parent::_initialize();
        AuthorizationSchema::ensureAdmin();
    }

    public function index()
    {
        // The overview is a live operational snapshot. Keep HTTP caches out of
        // the path, while the FastAdmin tab is explicitly invalidated by the
        // authorization module after log mutations.
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        $now = time();
        $today = strtotime(date('Y-m-d 00:00:00', $now));
        $week = $now + 7 * 86400;

        $activeDevices = Db::query("SELECT COUNT(DISTINCT `udid`) AS c FROM `fa_kami` WHERE `jh`=1 AND `udid`<>'' AND `endtime`>?", [$now]);
        $expiring = Db::query("SELECT COUNT(DISTINCT `udid`) AS c FROM `fa_kami` WHERE `jh`=1 AND `udid`<>'' AND `endtime`>? AND `endtime`<=?", [$now, $week]);
        $todayActivations = Db::table('fa_authorization_event')->where('event', 'in', ['activate', 'stack'])->where('addtime', '>=', $today)->count();
        $todayTransfers = Db::table('fa_card_transfer_log')->where('status', 1)->where('addtime', '>=', $today)->count();

        $blackRows = Db::table('fa_black')->order('id desc')->select();
        $activeBlack = 0;
        foreach ($blackRows as $row) {
            if (BlacklistPolicy::isActive($row, $now)) {
                $activeBlack++;
            }
        }

        $values = SourceConfigRepository::mapRows(SourceConfigRepository::rows());
        $summary = [
            'active_devices' => isset($activeDevices[0]['c']) ? (int)$activeDevices[0]['c'] : 0,
            'expiring_7d' => isset($expiring[0]['c']) ? (int)$expiring[0]['c'] : 0,
            'today_activations' => (int)$todayActivations,
            'today_transfers' => (int)$todayTransfers,
            'active_blacklist' => $activeBlack,
            'transfer_max' => AuthorizationPolicy::maxTransfers($values),
            'transfer_daily' => AuthorizationPolicy::dailyTransfers($values),
            'cooldown' => AuthorizationPolicy::cooldownSeconds($values),
            'ip_limit' => AuthorizationPolicy::ipHourlyAttempts($values),
        ];

        $this->view->assign('summary', $summary);
        $this->view->assign('recentTransfers', Db::table('fa_card_transfer_log')->order('id desc')->limit(8)->select());
        $this->view->assign('recentEvents', Db::table('fa_authorization_event')->order('id desc')->limit(8)->select());
        $this->view->assign('diagPreview', $this->diagnosticSnapshot(false));
        return $this->view->fetch();
    }

    public function transfers()
    {
        if ($this->request->isPost()) {
            if ((int)$this->request->post('clear', 0) !== 1) {
                $this->error('无效的清空请求');
            }
            try {
                $deleted = Db::execute('DELETE FROM `fa_card_transfer_log`');
                $this->success(
                    '换绑记录已清空，共删除 ' . (int)$deleted . ' 条',
                    null,
                    ['deleted' => (int)$deleted, 'resource' => 'transfers']
                );
            } catch (\Exception $e) {
                error_log('[Authorization::transfers] clear failed: ' . $e->getMessage());
                $this->error('清空换绑记录失败: ' . $e->getMessage());
            }
        }

        $q = trim((string)$this->request->get('q', ''));
        $query = Db::table('fa_card_transfer_log')->order('id desc');
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($sub) use ($like) {
                $sub->where('kami', 'like', $like)
                    ->whereOr('old_udid', 'like', $like)
                    ->whereOr('new_udid', 'like', $like)
                    ->whereOr('ip', 'like', $like);
            });
        }
        $rows = $query->limit(300)->select();
        $this->view->assign('rows', $rows);
        $this->view->assign('q', $q);
        return $this->view->fetch();
    }

    public function events()
    {
        if ($this->request->isPost()) {
            if ((int)$this->request->post('clear', 0) !== 1) {
                $this->error('无效的清空请求');
            }
            try {
                $deleted = Db::execute('DELETE FROM `fa_authorization_event`');
                $this->success(
                    '授权事件已清空，共删除 ' . (int)$deleted . ' 条',
                    null,
                    ['deleted' => (int)$deleted, 'resource' => 'events']
                );
            } catch (\Exception $e) {
                error_log('[Authorization::events] clear failed: ' . $e->getMessage());
                $this->error('清空授权事件失败: ' . $e->getMessage());
            }
        }

        $q = trim((string)$this->request->get('q', ''));
        $query = Db::table('fa_authorization_event')->order('id desc');
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($sub) use ($like) {
                $sub->where('event', 'like', $like)
                    ->whereOr('kami', 'like', $like)
                    ->whereOr('udid', 'like', $like)
                    ->whereOr('related_udid', 'like', $like)
                    ->whereOr('ip', 'like', $like);
            });
        }
        $rows = $query->limit(300)->select();
        $this->view->assign('rows', $rows);
        $this->view->assign('q', $q);
        return $this->view->fetch();
    }

    public function diagnostic()
    {
        $this->view->assign('diag', $this->diagnosticSnapshot($this->request->get('probe') === '1'));
        return $this->view->fetch();
    }

    protected function diagnosticSnapshot($probeExternal = false)
    {
        $dbOk = false;
        try {
            $probe = Db::query('SELECT 1 AS ok');
            $dbOk = isset($probe[0]['ok']);
        } catch (\Exception $e) {
            $dbOk = false;
        }

        $runtimePath = defined('RUNTIME_PATH') ? RUNTIME_PATH : ROOT_PATH . 'runtime' . DS;
        $logPath = $runtimePath . 'log';
        $uploadPath = ROOT_PATH . 'public' . DS . 'uploads';
        $requiredExtensions = ['curl', 'pdo_mysql', 'openssl', 'json', 'mbstring'];
        $missingExtensions = [];
        foreach ($requiredExtensions as $extension) {
            if (!extension_loaded($extension)) {
                $missingExtensions[] = $extension;
            }
        }

        $backupDir = getenv('ZONOE_DB_BACKUP_DIR');
        if ($backupDir === false || trim((string)$backupDir) === '') {
            // BaoTa commonly enables open_basedir; keep the default probe inside
            // this site's runtime tree instead of touching /www/backup/database.
            $backupDir = ROOT_PATH . 'runtime' . DS . 'update_backup';
        }

        $diag = [
            'php_version' => PHP_VERSION,
            'db_ok' => $dbOk,
            'https' => $this->request->isSsl(),
            'runtime_writable' => is_dir($runtimePath) && is_writable($runtimePath),
            'log_writable' => is_dir($logPath) && is_writable($logPath),
            'uploads_writable' => is_dir($uploadPath) && is_writable($uploadPath),
            'disk_free_gb' => @disk_free_space(ROOT_PATH) !== false ? round(@disk_free_space(ROOT_PATH) / 1073741824, 2) : null,
            'tls_verify' => SourceHttpClient::envBool('SOURCE_HTTP_VERIFY_TLS', true),
            'missing_extensions' => $missingExtensions,
            'backup_dir' => $backupDir,
            'latest_backup' => $this->latestBackupFile($backupDir),
            'external' => null,
        ];

        if ($probeExternal) {
            $payload = ['content' => base64_encode(json_encode(['probe' => 'zonoe-phase11']))];
            $diag['external'] = [
                'appstore' => SourceHttpClient::postForm('https://api.nuosike.com/api.php', $payload, ['connect_timeout' => 3, 'timeout' => 8]),
                'appstore_v2' => SourceHttpClient::postForm('https://api.nuosike.com/encrypt.php', $payload, ['connect_timeout' => 3, 'timeout' => 8]),
            ];
            foreach ($diag['external'] as &$item) {
                unset($item['body']);
                unset($item['error']);
            }
            unset($item);
        }

        return $diag;
    }

    protected function latestBackupFile($directory)
    {
        if (!@is_dir($directory) || !@is_readable($directory)) {
            return null;
        }
        $latest = null;
        $latestTime = 0;
        $items = @scandir($directory);
        if (!is_array($items)) {
            return null;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $item;
            $candidate = null;
            if (@is_file($path)) {
                $candidate = $path;
            } elseif (@is_dir($path) && @is_file($path . DIRECTORY_SEPARATOR . 'database.sql')) {
                $candidate = $path . DIRECTORY_SEPARATOR . 'database.sql';
            }
            if ($candidate === null) {
                continue;
            }
            $mtime = @filemtime($candidate);
            if ($mtime && $mtime > $latestTime) {
                $latestTime = $mtime;
                $relative = ltrim(str_replace('\\', '/', substr($candidate, strlen(rtrim($directory, '/\\')))), '/');
                $latest = ['file' => $relative, 'mtime' => $mtime, 'recent' => $mtime >= time() - 7 * 86400];
            }
        }
        return $latest;
    }
}
