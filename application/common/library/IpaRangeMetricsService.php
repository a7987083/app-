<?php

namespace app\common\library;

use think\Db;

/** Phase 20.7.3 Range parser production metrics. */
class IpaRangeMetricsService
{
    const MAX_WINDOW_DAYS = 90;
    const MAX_SAMPLE_ROWS = 5000;

    public static function normalizeDays($days)
    {
        return max(1, min(self::MAX_WINDOW_DAYS, (int)$days));
    }

    public static function summary($days=7)
    {
        $days=self::normalizeDays($days);
        $cutoff=time()-$days*86400;
        $query=Db::name('ipa_scan_task_item')->where('stage','parsed')->where('updatetime','>=',$cutoff);
        $total=(int)(clone $query)->count();
        $rows=$query->field('id,metadata_id,result_json,updatetime')->order('id','desc')->limit(self::MAX_SAMPLE_ROWS)->select();

        $metrics=[
            'days'=>$days,
            'cutoff'=>$cutoff,
            'window_items'=>$total,
            'sampled_items'=>count((array)$rows),
            'truncated'=>$total>self::MAX_SAMPLE_ROWS,
            'parsed'=>0,
            'reused'=>0,
            'range_bytes'=>0,
            'range_requests'=>0,
            'avg_range_bytes_per_item'=>0,
            'avg_range_bytes_per_request'=>0,
            'max_range_bytes'=>0,
            'max_range_requests'=>0,
        ];

        foreach((array)$rows as $row){
            $result=json_decode(isset($row['result_json'])?$row['result_json']:'',true);
            $result=is_array($result)?$result:[];
            $reused=!empty($result['reused']);
            if($reused)$metrics['reused']++;else$metrics['parsed']++;
            $bytes=isset($result['range_bytes'])?(int)$result['range_bytes']:0;
            $requests=isset($result['range_requests'])?(int)$result['range_requests']:0;
            $metrics['range_bytes']+=$bytes;
            $metrics['range_requests']+=$requests;
            if($bytes>$metrics['max_range_bytes'])$metrics['max_range_bytes']=$bytes;
            if($requests>$metrics['max_range_requests'])$metrics['max_range_requests']=$requests;
        }

        $sampled=max(1,$metrics['sampled_items']);
        $metrics['avg_range_bytes_per_item']=(int)round($metrics['range_bytes']/$sampled);
        if($metrics['range_requests']>0)$metrics['avg_range_bytes_per_request']=(int)round($metrics['range_bytes']/$metrics['range_requests']);
        return $metrics;
    }
}
