<?php

namespace app\index\controller;

use app\common\library\AppStorePayload;
use app\common\library\CardAccessPolicy;
use app\common\library\SourceChangeLog;
use app\common\library\SourceConfigRepository;
use app\common\library\SourceSyncV3;
use think\Db;

/**
 * Additive AppStore V3 synchronization endpoints.
 *
 * This controller deliberately extends the stable legacy App controller so it
 * reuses blacklist, card-scope and encryption-response behavior without
 * modifying /appstore itself.
 */
class SourceV3 extends App
{
    public function meta()
    {
        $ctx = $this->v3Context();
        if (isset($ctx['error'])) {
            $this->emitPayload($ctx['error'], $ctx['opencry'], $ctx['app_type'], 320, true);
        }

        $meta = SourceSyncV3::meta();
        $payload = [
            'protocol' => 'appstore_v3',
            'version' => 3,
            'code' => 1,
            'supported' => 1,
            'revision' => $meta['revision'],
            'min_delta_since' => $meta['min_delta_since'],
            'app_count' => $meta['app_count'],
            'delta_available' => $meta['delta_available'],
            'fallback' => 'appstore',
            'pagination' => [
                'default_limit' => $meta['default_limit'],
                'max_limit' => $meta['max_limit'],
                'default_delta_limit' => $meta['default_delta_limit'],
                'max_delta_limit' => $meta['max_delta_limit'],
            ],
            'sync_contract' => [
                'snapshot_param' => 'snapshot_revision',
                'snapshot_restart_field' => 'restart_required',
                'delta_reset_field' => 'reset_required',
            ],
        ];
        $this->emitPayload($payload, $ctx['opencry'], $ctx['app_type'], 320, true);
    }

    public function apps()
    {
        $ctx = $this->v3Context();
        if (isset($ctx['error'])) {
            $this->emitPayload($ctx['error'], $ctx['opencry'], $ctx['app_type'], 320, true);
        }

        $afterId = isset($_GET['after_id']) ? (int)$_GET['after_id'] : 0;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : SourceSyncV3::DEFAULT_LIMIT;
        $snapshotRevision = isset($_GET['snapshot_revision']) && $_GET['snapshot_revision'] !== ''
            ? (int)$_GET['snapshot_revision']
            : null;
        $page = SourceSyncV3::page($afterId, $limit, $ctx['mode'], $ctx['source_access'], $snapshotRevision);

        $payload = [
            'protocol' => 'appstore_v3',
            'version' => 3,
            'code' => !empty($page['snapshot_valid']) ? 1 : 0,
            'supported' => 1,
            'revision' => $page['revision'],
            'snapshot_revision' => $page['snapshot_revision'],
            'current_revision' => $page['current_revision'],
            'snapshot_valid' => $page['snapshot_valid'],
            'restart_required' => $page['restart_required'],
            'apps' => $page['apps'],
            'paging' => $page['paging'],
        ];
        if (!empty($page['restart_required'])) {
            $payload['msg'] = '软件源数据在全量同步期间发生变化，请使用新的revision重新开始全量同步';
        }
        $this->emitPayload($payload, $ctx['opencry'], $ctx['app_type'], 320, true);
    }

    public function delta()
    {
        $ctx = $this->v3Context();
        if (isset($ctx['error'])) {
            $this->emitPayload($ctx['error'], $ctx['opencry'], $ctx['app_type'], 320, true);
        }

        if (!SourceChangeLog::available()) {
            $payload = [
                'protocol' => 'appstore_v3',
                'version' => 3,
                'code' => 0,
                'supported' => 1,
                'msg' => 'V3增量同步表不可用，请先完成服务端数据库更新',
                'fallback' => 'appstore',
            ];
            $this->emitPayload($payload, $ctx['opencry'], $ctx['app_type'], 320, true);
        }

        $since = isset($_GET['since']) ? (int)$_GET['since'] : 0;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : SourceSyncV3::DEFAULT_DELTA_LIMIT;
        $delta = SourceSyncV3::delta($since, $limit, $ctx['mode'], $ctx['source_access']);
        $payload = [
            'protocol' => 'appstore_v3',
            'version' => 3,
            'code' => 1,
            'supported' => 1,
            'since' => $delta['since'],
            'next_since' => $delta['next_since'],
            'min_since' => $delta['min_since'],
            'current_revision' => $delta['current_revision'],
            'has_more' => $delta['has_more'],
            'reset_required' => $delta['reset_required'],
            'reset_reason' => $delta['reset_reason'],
            'upserts' => $delta['upserts'],
            'deleted' => $delta['deleted'],
        ];
        if (!empty($delta['reset_required'])) {
            $payload['msg'] = $delta['reset_reason'] === 'history_gap'
                ? '本地revision已超出服务端可增量恢复窗口，请重新全量同步'
                : '本地revision高于服务端revision，请重新全量同步';
        }
        $this->emitPayload($payload, $ctx['opencry'], $ctx['app_type'], 320, true);
    }

    protected function v3Context()
    {
        $this->requestStartedAt = microtime(true);
        $appType = AppStorePayload::appType(isset($_SERVER['HTTP_APPSTORE']) ? $_SERVER['HTTP_APPSTORE'] : null);
        $configRows = SourceConfigRepository::rows();
        $configValues = SourceConfigRepository::mapRows($configRows);
        $opencry = array_key_exists('opencry', $configValues) ? $configValues['opencry'] : null;
        $v3Enabled = !array_key_exists('source_v3', $configValues) || (string)$configValues['source_v3'] === '1';

        if (!$v3Enabled) {
            return [
                'opencry' => $opencry,
                'app_type' => $appType,
                'error' => [
                    'protocol' => 'appstore_v3',
                    'version' => 3,
                    'code' => 0,
                    'supported' => 0,
                    'msg' => 'V3软件源已关闭，请回退到appstore',
                    'fallback' => 'appstore',
                ],
            ];
        }

        $udid = isset($_GET['udid']) ? trim((string)$_GET['udid']) : '';
        $now = time();

        $black = $this->activeBlacklist($udid);
        if ($black) {
            $this->markBlacklistUsed($black);
            return [
                'opencry' => $opencry,
                'app_type' => $appType,
                'error' => [
                    'protocol' => 'appstore_v3',
                    'version' => 3,
                    'code' => 0,
                    'supported' => 1,
                    'msg' => '设备已被拉黑',
                ],
            ];
        }

        $kamiRows = $udid === ''
            ? []
            : Db::table('fa_kami')->where('udid', $udid)->order('id desc')->select();
        $appMap = $this->cardAppMap($kamiRows);
        $sourceAccess = CardAccessPolicy::sourceAccess($kamiRows, $appMap, $now);
        $mode = CardAccessPolicy::hasSourceCard($kamiRows) ? 'licensed' : 'guest';

        return [
            'opencry' => $opencry,
            'app_type' => $appType,
            'udid' => $udid,
            'mode' => $mode,
            'source_access' => $sourceAccess,
        ];
    }
}
