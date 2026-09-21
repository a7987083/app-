<?php

namespace app\common\library\Ipa;

use think\Db;

class IpaScanService
{
    public static function createJob($sourceId, $mode = 'incremental')
    {
        $mode = in_array($mode, ['incremental', 'full'], true) ? $mode : 'incremental';
        $now = time();

        Db::startTrans();
        try {
            $source = Db::name('ipa_source')
                ->where('id', (int)$sourceId)
                ->where('enabled', 1)
                ->lock(true)
                ->find();
            if (!$source) {
                throw new \InvalidArgumentException('IPA source not found or disabled');
            }

            $active = Db::name('ipa_scan_job')
                ->where('source_id', (int)$source['id'])
                ->where('status', 'in', ['pending', 'running'])
                ->count();
            if ((int)$active > 0) {
                throw new \RuntimeException('An IPA scan is already active for this source');
            }

            $jobId = Db::name('ipa_scan_job')->insertGetId([
                'source_id' => (int)$source['id'],
                'mode' => $mode,
                'status' => 'pending',
                'root_path' => isset($source['root_path']) ? $source['root_path'] : '/',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            self::enqueueItem($jobId, (int)$source['id'], 'directory', $source['root_path'], $now);
            Db::commit();
            return $jobId;
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    public static function claimOne($workerId)
    {
        $now = time();
        $staleBefore = $now - 300;

        Db::startTrans();
        try {
            Db::name('ipa_scan_item')
                ->where('status', 'processing')
                ->where('locked_at', '<', $staleBefore)
                ->where('retry_count', '<', 5)
                ->update([
                    'status' => 'pending',
                    'worker_id' => '',
                    'locked_at' => 0,
                    'available_at' => $now,
                    'updated_at' => $now,
                ]);

            $item = Db::name('ipa_scan_item')
                ->where('status', 'pending')
                ->where('available_at', '<=', $now)
                ->order('id asc')
                ->lock(true)
                ->find();

            if (!$item) {
                Db::commit();
                return null;
            }

            Db::name('ipa_scan_item')->where('id', $item['id'])->update([
                'status' => 'processing',
                'worker_id' => $workerId,
                'locked_at' => $now,
                'updated_at' => $now,
            ]);

            Db::name('ipa_scan_job')
                ->where('id', $item['job_id'])
                ->where('status', 'pending')
                ->update([
                    'status' => 'running',
                    'worker_id' => $workerId,
                    'started_at' => $now,
                    'updated_at' => $now,
                ]);

            Db::commit();
            $item['status'] = 'processing';
            $item['worker_id'] = $workerId;
            return $item;
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    public static function processItem(array $item, $tokenResolver = null)
    {
        $source = Db::name('ipa_source')->where('id', (int)$item['source_id'])->find();
        if (!$source || !(int)$source['enabled']) {
            throw new \RuntimeException('Source unavailable');
        }

        $token = '';
        if (is_callable($tokenResolver)) {
            $token = (string)call_user_func($tokenResolver, $source);
        } elseif (!empty($source['token_ciphertext'])) {
            throw new \RuntimeException('Encrypted OpenList token requires a token resolver');
        }

        $client = new OpenListClient($source['base_url'], $token, $source['request_timeout']);
        if ($item['item_type'] === 'directory') {
            self::processDirectory($client, $source, $item);
        } else {
            self::touchAssetFromFile($client, $source, $item['path']);
        }

        self::markDone($item);
    }

    protected static function processDirectory(OpenListClient $client, array $source, array $item)
    {
        $page = 1;
        $pageSize = max(20, min(1000, (int)$source['scan_page_size']));
        do {
            $data = $client->listDirectory($item['path'], $page, $pageSize, false);
            $rows = isset($data['content']) && is_array($data['content']) ? $data['content'] : [];
            foreach ($rows as $row) {
                if (empty($row['name'])) {
                    continue;
                }
                $path = rtrim($item['path'], '/') . '/' . ltrim($row['name'], '/');
                $isDir = !empty($row['is_dir']);
                if ($isDir) {
                    self::enqueueItem($item['job_id'], $item['source_id'], 'directory', $path);
                    continue;
                }
                if (strtolower(substr($row['name'], -4)) !== '.ipa') {
                    continue;
                }
                self::upsertAsset($source, $path, $row);
            }

            $total = isset($data['total']) ? (int)$data['total'] : count($rows);
            $page++;
        } while (!empty($rows) && (($page - 1) * $pageSize) < $total);
    }

    protected static function touchAssetFromFile(OpenListClient $client, array $source, $path)
    {
        $data = $client->getFile($path);
        self::upsertAsset($source, $path, $data);
    }

    protected static function upsertAsset(array $source, $path, array $row)
    {
        $now = time();
        $hash = hash('sha256', (string)$path);
        $existing = Db::name('ipa_asset')
            ->where('source_id', (int)$source['id'])
            ->where('path_hash', $hash)
            ->find();

        $mtime = 0;
        if (!empty($row['modified'])) {
            $parsed = strtotime($row['modified']);
            if ($parsed !== false) {
                $mtime = $parsed;
            }
        }
        $size = isset($row['size']) ? (int)$row['size'] : 0;

        $values = [
            'path' => (string)$path,
            'name' => basename((string)$path),
            'size_bytes' => $size,
            'modified_at' => $mtime,
            'last_seen_at' => $now,
            'updated_at' => $now,
        ];

        if ($existing) {
            $contentChanged = ((int)$existing['size_bytes'] !== $size)
                || ($mtime > 0 && (int)$existing['modified_at'] !== $mtime);
            if ($contentChanged || (string)$existing['status'] === 'missing') {
                $values['status'] = 'discovered';
                $values['parsed_at'] = 0;
                $values['last_error'] = null;
                $values['raw_url'] = null;
            }
            Db::name('ipa_asset')->where('id', $existing['id'])->update($values);
            return (int)$existing['id'];
        }

        $values['source_id'] = (int)$source['id'];
        $values['path_hash'] = $hash;
        $values['status'] = 'discovered';
        $values['created_at'] = $now;
        $assetId = Db::name('ipa_asset')->insertGetId($values);

        $jobId = self::currentJobId();
        if ($jobId > 0) {
            Db::name('ipa_scan_job')->where('id', $jobId)->setInc('discovered_count');
        }
        return $assetId;
    }

    protected static $jobContext = 0;

    public static function setJobContext($jobId)
    {
        self::$jobContext = (int)$jobId;
    }

    protected static function currentJobId()
    {
        return self::$jobContext;
    }

    public static function failItem(array $item, \Exception $e)
    {
        $now = time();
        $retry = (int)$item['retry_count'] + 1;
        $terminal = $retry >= 5;
        Db::name('ipa_scan_item')->where('id', $item['id'])->update([
            'status' => $terminal ? 'failed' : 'pending',
            'retry_count' => $retry,
            'available_at' => $terminal ? 0 : ($now + min(300, (int)pow(2, $retry) * 5)),
            'worker_id' => '',
            'locked_at' => 0,
            'last_error' => substr($e->getMessage(), 0, 2000),
            'updated_at' => $now,
        ]);
        if ($terminal) {
            Db::name('ipa_scan_job')->where('id', $item['job_id'])->setInc('failed_count');
        }
        self::reconcileJob((int)$item['job_id']);
    }

    protected static function markDone(array $item)
    {
        $now = time();
        Db::name('ipa_scan_item')->where('id', $item['id'])->update([
            'status' => 'done',
            'worker_id' => '',
            'locked_at' => 0,
            'updated_at' => $now,
        ]);
        Db::name('ipa_scan_job')->where('id', $item['job_id'])->setInc('processed_count');
        self::reconcileJob((int)$item['job_id']);
    }

    protected static function reconcileJob($jobId)
    {
        $active = Db::name('ipa_scan_item')
            ->where('job_id', $jobId)
            ->where('status', 'in', ['pending', 'processing'])
            ->count();
        if ((int)$active > 0) {
            return;
        }

        $job = Db::name('ipa_scan_job')->where('id', (int)$jobId)->find();
        if (!$job) {
            return;
        }
        $failed = Db::name('ipa_scan_item')->where('job_id', $jobId)->where('status', 'failed')->count();
        $now = time();

        if ((string)$job['mode'] === 'full') {
            $cutoff = !empty($job['started_at']) ? (int)$job['started_at'] : (int)$job['created_at'];
            Db::name('ipa_asset')
                ->where('source_id', (int)$job['source_id'])
                ->where('last_seen_at', '<', $cutoff)
                ->where('status', '<>', 'missing')
                ->update([
                    'status' => 'missing',
                    'updated_at' => $now,
                ]);
        }

        Db::name('ipa_scan_job')->where('id', $jobId)->update([
            'status' => ((int)$failed > 0) ? 'completed_with_errors' : 'completed',
            'finished_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected static function enqueueItem($jobId, $sourceId, $type, $path, $availableAt = null)
    {
        $path = '/' . ltrim(preg_replace('#/+#', '/', (string)$path), '/');
        $now = time();
        $data = [
            'job_id' => (int)$jobId,
            'source_id' => (int)$sourceId,
            'item_type' => $type,
            'path_hash' => hash('sha256', $path),
            'path' => $path,
            'status' => 'pending',
            'available_at' => $availableAt === null ? $now : (int)$availableAt,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        try {
            Db::name('ipa_scan_item')->insert($data);
        } catch (\think\exception\PDOException $e) {
            if (stripos($e->getMessage(), 'Duplicate entry') === false) {
                throw $e;
            }
        }
    }
}
