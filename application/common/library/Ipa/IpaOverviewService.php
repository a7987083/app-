<?php

namespace app\common\library\Ipa;

use think\Db;

/**
 * Read-only model for the IPA data-center overview page.
 *
 * Keep this class free of controller/view concerns so overview aggregation can
 * be regression-tested independently and reused without duplicating SQL.
 */
class IpaOverviewService
{
    const SOURCE_FIELDS = 'id,name,base_url,root_path,enabled,scan_page_size,request_timeout,updated_at';

    /**
     * Build the exact data contract consumed by IpaCenter::index().
     *
     * Compared with the legacy controller implementation, status counts are
     * aggregated in one query per table rather than issuing one COUNT query per
     * status. The returned summary keys and source ordering remain unchanged.
     *
     * @param int|null $now Injectable clock for deterministic regression tests.
     * @return array{summary:array,sources:array}
     */
    public static function snapshot($now = null)
    {
        $now = $now === null ? time() : (int)$now;

        $sources = Db::name('ipa_source')
            ->field(self::SOURCE_FIELDS)
            ->order('id desc')
            ->select();

        $assetStatusCounts = self::statusCounts('ipa_asset');
        $scanStatusCounts = self::statusCounts('ipa_scan_item');

        $summary = self::buildSummary(
            count($sources),
            $assetStatusCounts,
            $scanStatusCounts,
            (int)Db::name('dylib')->count(),
            (int)Db::name('dylib_verify_log')->where('created_at', '>=', $now - 86400)->count()
        );

        return [
            'summary' => $summary,
            'sources' => $sources,
        ];
    }

    /**
     * Pure summary builder. Public by design so the overview contract can be
     * verified without bootstrapping the web controller.
     */
    public static function buildSummary($sourceCount, array $assetStatusCounts, array $scanStatusCounts, $dylibCount, $verify24hCount)
    {
        return [
            'sources' => (int)$sourceCount,
            'assets' => (int)array_sum($assetStatusCounts),
            'pending' => self::countFor($scanStatusCounts, 'pending'),
            'failed' => self::countFor($scanStatusCounts, 'failed'),
            'parse_pending' => self::countFor($assetStatusCounts, 'discovered'),
            'parse_failed' => self::countFor($assetStatusCounts, 'parse_failed'),
            'dylibs' => (int)$dylibCount,
            'verify24h' => (int)$verify24hCount,
        ];
    }

    protected static function statusCounts($table)
    {
        $rows = Db::name($table)
            ->field('status,COUNT(*) AS aggregate_count')
            ->group('status')
            ->select();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string)$row['status']] = (int)$row['aggregate_count'];
        }
        return $counts;
    }

    protected static function countFor(array $counts, $status)
    {
        return isset($counts[$status]) ? (int)$counts[$status] : 0;
    }
}
