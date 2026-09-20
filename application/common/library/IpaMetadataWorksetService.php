<?php

namespace app\common\library;

use think\Db;

/**
 * Compatibility facade retained for pre-1918 callers.
 * The active OpenList work-set is now the latest scan JSON snapshot.
 */
class IpaMetadataWorksetService
{
    public static function invalidateIfNoEnabledSources(){return 0;}
    public static function pruneUnreferenced($limit=10000){return ['deleted'=>0,'cached'=>0];}
    public static function orphanStats(){return ['total'=>0,'pending'=>0,'success'=>0,'failed'=>0,'parsing'=>0];}
    public static function summary()
    {
        $snapshot=IpaScanService::latestSnapshot();
        return ['active'=>isset($snapshot['total'])?(int)$snapshot['total']:count(isset($snapshot['current_paths'])?(array)$snapshot['current_paths']:[]),'reclaimable'=>0,'snapshot_version'=>isset($snapshot['snapshot_version'])?(int)$snapshot['snapshot_version']:0];
    }
}
