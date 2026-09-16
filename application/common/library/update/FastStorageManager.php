<?php

namespace app\common\library\update;

/**
 * Phase 15.3 fast whole-site storage engine.
 *
 * Goals:
 * - system-level GNU find scanning instead of PHP recursive scandir/stat loops;
 * - cached snapshot so the panel opens immediately;
 * - async scan/cleanup jobs with progress status files;
 * - source-directory quick fingerprints so unchanged source trees are reused;
 * - safe cleanup only from previously indexed regenerable/temp candidates.
 */
class FastStorageManager
{
    const SAFE_MIN_AGE_SECONDS = 3600;
    const REVIEW_LIMIT = 500;
    const LARGEST_LIMIT = 50;
    const STATUS_STALE_SECONDS = 1800;

    protected $root;
    protected $runtime;
    protected $indexFile;
    protected $safeFile;
    protected $scanStatusFile;
    protected $cleanupStatusFile;

    public function __construct($root)
    {
        $this->root = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
        $this->runtime = $this->root . 'runtime' . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR;
        $this->indexFile = $this->runtime . 'index.json';
        $this->safeFile = $this->runtime . 'safe.jsonl';
        $this->scanStatusFile = $this->runtime . 'scan-status.json';
        $this->cleanupStatusFile = $this->runtime . 'cleanup-status.json';
        $this->ensureRuntime();
    }

    public function snapshot()
    {
        $data = $this->readJson($this->indexFile);
        if (!is_array($data)) {
            return [
                'index_ready' => false,
                'engine' => $this->engineCapabilities(),
                'buckets' => $this->emptyBuckets(),
                'largest' => [],
                'review_candidates' => [],
                'safe_candidates' => [],
                'errors' => [],
                'meta' => ['last_scan_at' => '', 'duration_seconds' => 0, 'skipped_source_dirs' => []],
            ];
        }
        $data['index_ready'] = true;
        $data['engine'] = $this->engineCapabilities();
        return $data;
    }

    public function statuses()
    {
        return [
            'scan' => $this->normalizeStatus($this->readJson($this->scanStatusFile), 'scan'),
            'cleanup' => $this->normalizeStatus($this->readJson($this->cleanupStatusFile), 'cleanup'),
        ];
    }

    public function previewSafe()
    {
        $rows = $this->readJsonLines($this->safeFile, self::REVIEW_LIMIT);
        $count = 0;
        $bytes = 0;
        if (is_file($this->safeFile)) {
            $fp = @fopen($this->safeFile, 'rb');
            if ($fp) {
                while (($line = fgets($fp)) !== false) {
                    $row = json_decode(trim($line), true);
                    if (!is_array($row)) {
                        continue;
                    }
                    $count++;
                    $bytes += isset($row['bytes']) ? (int)$row['bytes'] : 0;
                }
                fclose($fp);
            }
        }
        return ['count' => $count, 'bytes' => $bytes, 'candidates' => $rows];
    }

    public function startScan()
    {
        $status = $this->normalizeStatus($this->readJson($this->scanStatusFile), 'scan');
        if ($status['running']) {
            return ['started' => false, 'status' => $status, 'message' => '扫描任务正在运行'];
        }
        $cap = $this->engineCapabilities();
        if (!$cap['system_scan_available']) {
            return ['started' => false, 'status' => $status, 'message' => '服务器未开放 exec/proc_open 或未找到 find，无法启动系统高速扫描'];
        }
        $job = $this->newStatus('scan', 'queued', '准备启动系统扫描');
        $this->writeJson($this->scanStatusFile, $job);
        $ok = $this->spawnWorker('scan');
        if (!$ok) {
            $job['status'] = 'failed';
            $job['message'] = '后台扫描进程启动失败';
            $job['finished_at'] = date('c');
            $this->writeJson($this->scanStatusFile, $job);
        }
        return ['started' => $ok, 'status' => $job, 'message' => $ok ? '扫描任务已启动' : '扫描任务启动失败'];
    }

    public function startCleanup()
    {
        $status = $this->normalizeStatus($this->readJson($this->cleanupStatusFile), 'cleanup');
        if ($status['running']) {
            return ['started' => false, 'status' => $status, 'message' => '安全清理任务正在运行'];
        }
        if (!is_file($this->safeFile)) {
            return ['started' => false, 'status' => $status, 'message' => '没有可清理索引，请先完成一次扫描'];
        }
        $cap = $this->engineCapabilities();
        if (!$cap['background_worker_available']) {
            return ['started' => false, 'status' => $status, 'message' => '服务器未开放后台 PHP CLI 执行能力'];
        }
        $job = $this->newStatus('cleanup', 'queued', '准备执行自动安全清理');
        $this->writeJson($this->cleanupStatusFile, $job);
        $ok = $this->spawnWorker('cleanup');
        if (!$ok) {
            $job['status'] = 'failed';
            $job['message'] = '后台清理进程启动失败';
            $job['finished_at'] = date('c');
            $this->writeJson($this->cleanupStatusFile, $job);
        }
        return ['started' => $ok, 'status' => $job, 'message' => $ok ? '自动安全清理已启动' : '自动安全清理启动失败'];
    }

    public function deleteSelected(array $paths, $dryRun = false)
    {
        $result = ['dry_run' => (bool)$dryRun, 'deleted' => ['count' => 0, 'bytes' => 0], 'skipped' => []];
        $seen = [];
        foreach ($paths as $path) {
            $path = $this->normalizeRelative($path);
            if ($path === '' || isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;
            $rule = $this->classify($path);
            if (!$rule['reviewable']) {
                $result['skipped'][] = ['path' => $path, 'reason' => '该文件不是人工可删除类别'];
                continue;
            }
            $full = $this->absolute($path);
            if (!$this->isInsideRoot($full) || !is_file($full) || is_link($full)) {
                $result['skipped'][] = ['path' => $path, 'reason' => '文件不存在或路径不安全'];
                continue;
            }
            $bytes = (int)@filesize($full);
            if (!$dryRun && !@unlink($full)) {
                $result['skipped'][] = ['path' => $path, 'reason' => '删除失败'];
                continue;
            }
            if (!$dryRun) {
                $result['deleted']['count']++;
                $result['deleted']['bytes'] += $bytes;
            }
        }
        return $result;
    }

    public function runWorker($action)
    {
        if ($action === 'scan') {
            return $this->runScanWorker();
        }
        if ($action === 'cleanup') {
            return $this->runCleanupWorker();
        }
        throw new \RuntimeException('unknown worker action');
    }

    protected function runScanWorker()
    {
        $started = microtime(true);
        $status = $this->newStatus('scan', 'running', '正在统计文件数量');
        $status['stage'] = 'counting';
        $this->writeJson($this->scanStatusFile, $status);

        $previous = $this->readJson($this->indexFile);
        $previous = is_array($previous) ? $previous : [];
        $totalCount = $this->systemFileCount();
        $status['total'] = $totalCount;
        $status['message'] = '正在比对源码目录指纹';
        $status['stage'] = 'fingerprint';
        $this->writeJson($this->scanStatusFile, $status);

        $sourceDirs = $this->sourceDirectories();
        $previousSource = isset($previous['source_dirs']) && is_array($previous['source_dirs']) ? $previous['source_dirs'] : [];
        $sourceState = [];
        $skipDirs = [];
        $skippedCount = 0;
        $buckets = $this->emptyBuckets();
        $largest = [];
        $review = [];
        $safePreview = [];
        $errors = [];

        foreach ($sourceDirs as $dir) {
            $full = $this->absolute($dir);
            if (!is_dir($full)) {
                continue;
            }
            $fingerprint = $this->quickFingerprint($dir);
            $old = isset($previousSource[$dir]) && is_array($previousSource[$dir]) ? $previousSource[$dir] : null;
            if ($old && isset($old['fingerprint']) && $old['fingerprint'] === $fingerprint) {
                $skipDirs[] = $dir;
                $count = isset($old['count']) ? (int)$old['count'] : 0;
                $bytes = isset($old['bytes']) ? (int)$old['bytes'] : 0;
                $skippedCount += $count;
                $buckets['total']['count'] += $count;
                $buckets['total']['bytes'] += $bytes;
                $buckets['protected']['count'] += $count;
                $buckets['protected']['bytes'] += $bytes;
                if (!empty($old['largest']) && is_array($old['largest'])) {
                    foreach ($old['largest'] as $row) {
                        $largest[] = $row;
                    }
                }
                $sourceState[$dir] = $old;
                continue;
            }
            $sourceState[$dir] = ['fingerprint' => $fingerprint, 'count' => 0, 'bytes' => 0, 'largest' => []];
        }

        $safeTmp = $this->safeFile . '.tmp.' . getmypid();
        $safeFp = @fopen($safeTmp, 'wb');
        $cmd = $this->buildFindCommand($skipDirs);
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            $this->failStatus($this->scanStatusFile, $status, '系统 find 扫描进程启动失败');
            return false;
        }

        $processed = 0;
        $lastStatusAt = 0;
        $status['stage'] = 'scanning';
        $status['message'] = '系统正在扫描网站目录';
        $status['processed'] = $skippedCount;
        $status['skipped'] = $skippedCount;
        $this->writeJson($this->scanStatusFile, $status);
        $now = time();

        while (($line = fgets($pipes[1])) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                continue;
            }
            $parts = explode("\t", $line);
            if (count($parts) < 4) {
                continue;
            }
            $path = $this->normalizeRelative($parts[0]);
            if ($path === '') {
                continue;
            }
            $row = [
                'path' => $path,
                'bytes' => (int)$parts[1],
                'mtime' => (int)floor((float)$parts[2]),
                'inode' => (string)$parts[3],
            ];
            $rule = $this->classify($path);
            $row['category'] = $rule['category'];
            $row['reason'] = $rule['reason'];
            $row['auto_safe'] = $rule['auto_safe'];
            $row['reviewable'] = $rule['reviewable'];

            $buckets['total']['count']++;
            $buckets['total']['bytes'] += $row['bytes'];
            $buckets[$rule['category']]['count']++;
            $buckets[$rule['category']]['bytes'] += $row['bytes'];

            foreach ($sourceDirs as $dir) {
                if ($this->startsWith(strtolower($path), strtolower($dir) . '/')) {
                    if (isset($sourceState[$dir])) {
                        $sourceState[$dir]['count']++;
                        $sourceState[$dir]['bytes'] += $row['bytes'];
                        $sourceState[$dir]['largest'][] = $row;
                        $this->trimLargest($sourceState[$dir]['largest'], 10);
                    }
                    break;
                }
            }

            $largest[] = $row;
            if (count($largest) >= 500) {
                $this->trimLargest($largest, self::LARGEST_LIMIT);
            }
            if ($rule['reviewable'] && count($review) < self::REVIEW_LIMIT) {
                $review[] = $row;
            }
            if ($rule['auto_safe'] && ($row['mtime'] <= 0 || ($now - $row['mtime']) >= self::SAFE_MIN_AGE_SECONDS)) {
                if ($safeFp) {
                    fwrite($safeFp, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
                }
                if (count($safePreview) < self::REVIEW_LIMIT) {
                    $safePreview[] = $row;
                }
            }

            $processed++;
            if (($processed % 200) === 0 || (microtime(true) - $lastStatusAt) >= 0.75) {
                $done = $skippedCount + $processed;
                $status['processed'] = $done;
                $status['progress'] = $totalCount > 0 ? min(99, (int)floor(($done * 100) / $totalCount)) : null;
                $status['message'] = '已处理 ' . $done . ($totalCount > 0 ? (' / ' . $totalCount) : '') . ' 个文件';
                $this->writeJson($this->scanStatusFile, $status);
                $lastStatusAt = microtime(true);
            }
        }

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);
        if ($safeFp) {
            fclose($safeFp);
        }
        if ($exit !== 0) {
            @unlink($safeTmp);
            $this->failStatus($this->scanStatusFile, $status, '系统扫描失败：' . trim((string)$stderr));
            return false;
        }
        if ($safeFp !== false) {
            @rename($safeTmp, $this->safeFile);
        }

        $this->trimLargest($largest, self::LARGEST_LIMIT);
        foreach ($sourceState as $dir => $state) {
            if (!isset($state['fingerprint']) || $state['fingerprint'] === '') {
                $sourceState[$dir]['fingerprint'] = $this->quickFingerprint($dir);
            }
            if (!empty($sourceState[$dir]['largest'])) {
                $this->trimLargest($sourceState[$dir]['largest'], 10);
            }
        }

        $version = $this->currentVersion();
        $snapshot = [
            'root' => str_replace('\\', '/', rtrim($this->root, '/\\')),
            'buckets' => $buckets,
            'largest' => $largest,
            'review_candidates' => $review,
            'safe_candidates' => $safePreview,
            'errors' => $errors,
            'source_dirs' => $sourceState,
            'policy' => [
                'auto_delete' => ['regenerable', 'temporary'],
                'review_only' => ['backup', 'unknown'],
                'protected' => ['protected', 'persistent', 'log'],
                'safe_min_age_seconds' => self::SAFE_MIN_AGE_SECONDS,
            ],
            'meta' => [
                'version' => $version,
                'engine' => 'gnu-find',
                'last_scan_at' => date('c'),
                'duration_seconds' => round(microtime(true) - $started, 3),
                'skipped_source_dirs' => $skipDirs,
                'skipped_source_files' => $skippedCount,
            ],
        ];
        $this->writeJson($this->indexFile, $snapshot);

        $status['status'] = 'success';
        $status['stage'] = 'done';
        $status['progress'] = 100;
        $status['processed'] = $buckets['total']['count'];
        $status['total'] = $buckets['total']['count'];
        $status['message'] = '扫描完成，共 ' . $buckets['total']['count'] . ' 个文件';
        $status['finished_at'] = date('c');
        $status['duration_seconds'] = round(microtime(true) - $started, 3);
        $this->writeJson($this->scanStatusFile, $status);
        return true;
    }

    protected function runCleanupWorker()
    {
        $started = microtime(true);
        $preview = $this->previewSafe();
        $total = (int)$preview['count'];
        $status = $this->newStatus('cleanup', 'running', '正在执行自动安全清理');
        $status['stage'] = 'deleting';
        $status['total'] = $total;
        $status['processed'] = 0;
        $status['progress'] = $total > 0 ? 0 : 100;
        $this->writeJson($this->cleanupStatusFile, $status);

        $deletedCount = 0;
        $deletedBytes = 0;
        $skipped = 0;
        $processed = 0;
        $now = time();
        $fp = @fopen($this->safeFile, 'rb');
        if ($fp) {
            while (($line = fgets($fp)) !== false) {
                $row = json_decode(trim($line), true);
                if (!is_array($row) || empty($row['path'])) {
                    continue;
                }
                $processed++;
                $path = $this->normalizeRelative($row['path']);
                $rule = $this->classify($path);
                $full = $this->absolute($path);
                $mtime = is_file($full) ? (int)@filemtime($full) : 0;
                if ($path === '' || !$rule['auto_safe'] || !$this->isInsideRoot($full) || !is_file($full) || is_link($full) || ($mtime > 0 && ($now - $mtime) < self::SAFE_MIN_AGE_SECONDS)) {
                    $skipped++;
                } else {
                    $bytes = (int)@filesize($full);
                    if (@unlink($full)) {
                        $deletedCount++;
                        $deletedBytes += $bytes;
                    } else {
                        $skipped++;
                    }
                }
                if (($processed % 20) === 0 || $processed === $total) {
                    $status['processed'] = $processed;
                    $status['progress'] = $total > 0 ? min(100, (int)floor(($processed * 100) / $total)) : 100;
                    $status['message'] = '已处理 ' . $processed . ' / ' . $total . ' 个候选文件';
                    $status['deleted_count'] = $deletedCount;
                    $status['deleted_bytes'] = $deletedBytes;
                    $this->writeJson($this->cleanupStatusFile, $status);
                }
            }
            fclose($fp);
        }

        $status['status'] = 'success';
        $status['stage'] = 'done';
        $status['progress'] = 100;
        $status['processed'] = $processed;
        $status['message'] = '清理完成，删除 ' . $deletedCount . ' 个文件';
        $status['deleted_count'] = $deletedCount;
        $status['deleted_bytes'] = $deletedBytes;
        $status['skipped_count'] = $skipped;
        $status['finished_at'] = date('c');
        $status['duration_seconds'] = round(microtime(true) - $started, 3);
        $this->writeJson($this->cleanupStatusFile, $status);
        @unlink($this->safeFile);
        // Index is now stale. Start a fresh asynchronous system scan.
        $this->startScan();
        return true;
    }

    protected function buildFindCommand(array $skipDirs)
    {
        $root = rtrim($this->root, '/\\');
        $rootArg = escapeshellarg($root);
        $prunes = [];
        foreach ($skipDirs as $dir) {
            $prunes[] = '-path ' . escapeshellarg($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir));
        }
        $pruneExpr = $prunes ? ('\\( ' . implode(' -o ', $prunes) . ' \\) -prune -o ') : '';
        // %P relative path, %s bytes, %T@ mtime, %i inode.
        return 'find ' . $rootArg . ' -xdev ' . $pruneExpr . "-type f -printf '%P\\t%s\\t%T@\\t%i\\n'";
    }

    protected function systemFileCount()
    {
        $root = escapeshellarg(rtrim($this->root, '/\\'));
        $out = [];
        $code = 0;
        @exec("find {$root} -xdev -type f -printf '.' 2>/dev/null | wc -c", $out, $code);
        if ($code !== 0 || !$out) {
            return 0;
        }
        return max(0, (int)trim(end($out)));
    }

    protected function quickFingerprint($relativeDir)
    {
        $full = $this->absolute($relativeDir);
        if (!is_dir($full)) {
            return '';
        }
        $arg = escapeshellarg(rtrim($full, '/\\'));
        $cmd = "find {$arg} -xdev -type f -printf '%P\\t%s\\n' 2>/dev/null | LC_ALL=C sort | sha256sum";
        $out = [];
        $code = 0;
        @exec($cmd, $out, $code);
        if ($code !== 0 || !$out) {
            return '';
        }
        $parts = preg_split('/\\s+/', trim(end($out)));
        return isset($parts[0]) ? strtolower($parts[0]) : '';
    }

    protected function sourceDirectories()
    {
        return ['application', 'vendor', 'thinkphp', 'extend', 'addons', 'public/assets', 'public/static'];
    }

    protected function classify($relative)
    {
        $path = strtolower(str_replace('\\', '/', ltrim($relative, '/')));
        $base = basename($path);
        $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));

        if ($this->startsWith($path, 'public/uploads/')) return $this->rule('persistent', '用户上传/业务持久文件', false, false);
        if ($this->startsWith($path, 'runtime/update_backup/')) return $this->rule('backup', '在线更新/回滚备份', false, true);
        if ($this->startsWith($path, 'runtime/log/') || $ext === 'log') return $this->rule('log', '运行日志，纳入统计但默认保护', false, false);
        if ($this->startsWith($path, 'runtime/cache/') || $this->startsWith($path, 'runtime/temp/') || $this->startsWith($path, 'runtime/tmp/')) return $this->rule('regenerable', '可重建运行缓存/临时目录', true, false);
        if (in_array($ext, ['tmp', 'temp', 'part', 'download', 'swp'], true) || substr($base, -1) === '~' || $base === '.ds_store') return $this->rule('temporary', '临时/未完成下载文件', true, false);
        if (in_array($ext, ['zip', 'tar', 'gz', 'tgz', 'bz2', '7z', 'rar', 'sql', 'bak', 'backup'], true)) return $this->rule('backup', '备份/归档文件，需人工确认', false, true);

        foreach (['application/', 'vendor/', 'thinkphp/', 'extend/', 'addons/', 'public/assets/', 'public/static/', 'public/update/', 'release/', 'tools/', 'tests/', '.github/'] as $prefix) {
            if ($this->startsWith($path, $prefix)) return $this->rule('protected', '站点程序/发布资产', false, false);
        }
        if (in_array($path, ['public/index.php','public/api.php','public/encrypt.php','index.php','think','composer.json','composer.lock','ver.json','auto_install.json','nginx.rewrite','.htaccess','.user.ini','license','readme.md','version','phase13_release.txt'], true)) {
            return $this->rule('protected', '站点入口/配置/版本文件', false, false);
        }
        if ($this->startsWith($path, 'runtime/update/') || $this->startsWith($path, 'runtime/storage/')) return $this->rule('protected', '更新/储存管理运行状态文件', false, false);
        if ($this->startsWith($path, 'runtime/')) return $this->rule('unknown', '未识别 runtime 文件，需人工确认', false, true);
        return $this->rule('unknown', '不属于已知站点资产规则，需人工确认', false, true);
    }

    protected function rule($category, $reason, $autoSafe, $reviewable)
    {
        return ['category' => $category, 'reason' => $reason, 'auto_safe' => (bool)$autoSafe, 'reviewable' => (bool)$reviewable];
    }

    protected function engineCapabilities()
    {
        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        $exec = function_exists('exec') && !in_array('exec', $disabled, true);
        $proc = function_exists('proc_open') && !in_array('proc_open', $disabled, true);
        $find = false;
        if ($exec) {
            $out = [];
            $code = 0;
            @exec('command -v find 2>/dev/null', $out, $code);
            $find = $code === 0 && !empty($out);
        }
        $php = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php';
        $phpCli = is_file($php) && is_executable($php);
        return [
            'exec' => $exec,
            'proc_open' => $proc,
            'find' => $find,
            'php_cli' => $phpCli,
            'system_scan_available' => $exec && $proc && $find,
            'background_worker_available' => $exec && $phpCli,
            'mode' => ($exec && $proc && $find && $phpCli) ? 'system-fast' : 'unavailable',
        ];
    }

    protected function spawnWorker($action)
    {
        $cap = $this->engineCapabilities();
        if (!$cap['background_worker_available']) {
            return false;
        }
        $php = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php';
        $script = $this->root . 'tools' . DIRECTORY_SEPARATOR . 'storage_worker.php';
        if (!is_file($script)) {
            return false;
        }
        $cmd = 'nohup ' . escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($action) . ' ' . escapeshellarg(rtrim($this->root, '/\\')) . ' >/dev/null 2>&1 &';
        $out = [];
        $code = 0;
        @exec($cmd, $out, $code);
        return $code === 0;
    }

    protected function newStatus($type, $status, $message)
    {
        return [
            'type' => $type,
            'job_id' => $type . '-' . date('YmdHis') . '-' . substr(md5(uniqid('', true)), 0, 8),
            'status' => $status,
            'stage' => $status,
            'running' => in_array($status, ['queued', 'running'], true),
            'progress' => 0,
            'processed' => 0,
            'total' => 0,
            'message' => $message,
            'started_at' => date('c'),
            'finished_at' => '',
            'updated_at' => date('c'),
        ];
    }

    protected function normalizeStatus($row, $type)
    {
        if (!is_array($row)) {
            return ['type' => $type, 'status' => 'idle', 'stage' => 'idle', 'running' => false, 'progress' => 0, 'processed' => 0, 'total' => 0, 'message' => '空闲', 'started_at' => '', 'finished_at' => '', 'updated_at' => ''];
        }
        $running = isset($row['status']) && in_array($row['status'], ['queued', 'running'], true);
        if ($running && !empty($row['updated_at'])) {
            $stamp = strtotime($row['updated_at']);
            if ($stamp && (time() - $stamp) > self::STATUS_STALE_SECONDS) {
                $row['status'] = 'stale';
                $row['message'] = '任务状态超过 30 分钟未更新，可能已中断';
                $running = false;
            }
        }
        $row['running'] = $running;
        return $row;
    }

    protected function failStatus($file, array $status, $message)
    {
        $status['status'] = 'failed';
        $status['stage'] = 'failed';
        $status['running'] = false;
        $status['message'] = $message;
        $status['finished_at'] = date('c');
        $this->writeJson($file, $status);
    }

    protected function trimLargest(array &$rows, $limit)
    {
        usort($rows, function ($a, $b) {
            $ab = isset($a['bytes']) ? (int)$a['bytes'] : 0;
            $bb = isset($b['bytes']) ? (int)$b['bytes'] : 0;
            if ($ab === $bb) return strcmp(isset($a['path']) ? $a['path'] : '', isset($b['path']) ? $b['path'] : '');
            return $ab < $bb ? 1 : -1;
        });
        if (count($rows) > $limit) $rows = array_slice($rows, 0, $limit);
    }

    protected function emptyBuckets()
    {
        $out = [];
        foreach (['total','protected','persistent','regenerable','log','backup','temporary','unknown'] as $key) $out[$key] = ['count' => 0, 'bytes' => 0];
        return $out;
    }

    protected function currentVersion()
    {
        $file = $this->root . 'ver.json';
        $row = $this->readJson($file);
        return is_array($row) && isset($row['version']) ? trim((string)$row['version']) : '';
    }

    protected function ensureRuntime()
    {
        if (!is_dir($this->runtime)) @mkdir($this->runtime, 0775, true);
    }

    protected function writeJson($file, array $row)
    {
        $row['updated_at'] = date('c');
        $tmp = $file . '.tmp.' . getmypid();
        $ok = @file_put_contents($tmp, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        if ($ok === false) return false;
        return @rename($tmp, $file);
    }

    protected function readJson($file)
    {
        $raw = @file_get_contents($file);
        if ($raw === false) return null;
        $row = json_decode($raw, true);
        return is_array($row) ? $row : null;
    }

    protected function readJsonLines($file, $limit)
    {
        $rows = [];
        $fp = @fopen($file, 'rb');
        if (!$fp) return $rows;
        while (($line = fgets($fp)) !== false && count($rows) < $limit) {
            $row = json_decode(trim($line), true);
            if (is_array($row)) $rows[] = $row;
        }
        fclose($fp);
        return $rows;
    }

    protected function normalizeRelative($path)
    {
        $path = str_replace('\\', '/', trim((string)$path));
        $path = ltrim($path, '/');
        if ($path === '' || strpos($path, "\0") !== false) return '';
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.' || $part === '..') return '';
        }
        return implode('/', explode('/', $path));
    }

    protected function absolute($relative)
    {
        return $this->root . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relative, '/'));
    }

    protected function isInsideRoot($path)
    {
        $root = str_replace('\\', '/', $this->root);
        $candidate = str_replace('\\', '/', $path);
        return strpos($candidate, $root) === 0;
    }

    protected function startsWith($haystack, $needle)
    {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
