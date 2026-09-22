<?php

namespace app\common\library\Ipa;

use think\Db;

class WorkerState
{
    public static function heartbeat($type, $workerId, $status = 'idle', $currentItemId = 0)
    {
        $type = trim((string)$type);
        $workerId = trim((string)$workerId);
        if ($type === '' || $workerId === '') {
            return;
        }
        $now = time();
        $existing = Db::name('ipa_worker_state')->where('worker_type', $type)->find();
        $data = [
            'worker_id' => substr($workerId, 0, 128),
            'status' => substr((string)$status, 0, 24),
            'current_item_id' => (int)$currentItemId,
            'heartbeat_at' => $now,
            'updated_at' => $now,
        ];
        if ($existing) {
            Db::name('ipa_worker_state')->where('worker_type', $type)->update($data);
        } else {
            $data['worker_type'] = $type;
            $data['created_at'] = $now;
            Db::name('ipa_worker_state')->insert($data);
        }
    }

    public static function snapshot($aliveSeconds = 15)
    {
        $aliveSeconds = max(5, (int)$aliveSeconds);
        $now = time();
        $rows = Db::name('ipa_worker_state')->select();
        $out = [];
        foreach ($rows as $row) {
            $status = isset($row['status']) ? (string)$row['status'] : '';
            $grace = $status === 'working' ? max($aliveSeconds, 600) : $aliveSeconds;
            $row['alive'] = $status !== 'stopped'
                && !empty($row['heartbeat_at'])
                && ((int)$row['heartbeat_at'] >= $now - $grace);
            $row['alive_grace_seconds'] = $grace;
            $out[(string)$row['worker_type']] = $row;
        }
        return $out;
    }
}
