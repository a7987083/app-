<?php

namespace app\common\library {
    class CardAccessPolicy
    {
        const SCOPE_SOURCE = 1;
        const SCOPE_VERIFY = 2;
        const SCOPE_APPS = 3;

        public static function normalizeAppIds(array $ids)
        {
            $out = [];
            foreach ($ids as $id) {
                $id = (int)$id;
                if ($id > 0) $out[$id] = true;
            }
            $ids = array_keys($out);
            sort($ids, SORT_NUMERIC);
            return $ids;
        }

        public static function scopeForRow(array $row)
        {
            return isset($row['card_scope']) ? (int)$row['card_scope'] : self::SCOPE_SOURCE;
        }

        public static function isActive(array $row, $now = null)
        {
            return !empty($row['jh']) && (int)$row['endtime'] > (int)$now;
        }
    }

    class SourceAppRecord
    {
        public static function value(array $row, $name, $default = null)
        {
            return array_key_exists($name, $row) ? $row[$name] : $default;
        }
    }
}

namespace {
    require __DIR__ . '/../application/common/library/SourceAnnouncementTemplate.php';

    use app\common\library\SourceAnnouncementTemplate;

    function p1942_assert($condition, $message)
    {
        if (!$condition) {
            fwrite(STDERR, "FAIL phase19_4_2_unified_announcement_test: {$message}\n");
            exit(1);
        }
    }

    $keys = [];
    foreach (SourceAnnouncementTemplate::variables() as $row) {
        $keys[] = $row['key'];
    }

    p1942_assert(in_array('到期时间', $keys, true), 'generic expiry missing');
    p1942_assert(in_array('剩余时间', $keys, true), 'generic remaining missing');
    foreach (['全源到期时间','全源剩余时间','部分到期时间','部分剩余时间','验证到期时间'] as $legacy) {
        p1942_assert(!in_array($legacy, $keys, true), 'legacy scope-specific variable still exposed: ' . $legacy);
    }

    $now = strtotime('2026-09-18 12:00:00');

    $partialRows = [
        ['jh' => 1, 'card_scope' => 3, 'endtime' => $now + 86400 * 2],
        ['jh' => 1, 'card_scope' => 3, 'endtime' => $now + 86400 * 5],
        ['jh' => 1, 'card_scope' => 2, 'endtime' => $now + 86400 * 9],
    ];
    $partial = SourceAnnouncementTemplate::authorizationContext(
        $partialRows,
        ['unlock_all' => false, 'app_ids' => [7, 9]],
        $now
    );
    p1942_assert($partial['授权状态'] === '部分App授权', 'partial status');
    p1942_assert($partial['到期时间'] === date('Y-m-d H:i:s', $now + 86400 * 5), 'partial generic expiry');
    p1942_assert($partial['剩余时间'] === '5天0小时', 'partial generic remaining');

    foreach (['全源到期时间','部分到期时间','验证到期时间'] as $legacy) {
        p1942_assert($partial[$legacy] === $partial['到期时间'], 'legacy expiry alias must use generic clock');
    }
    foreach (['全源剩余时间','部分剩余时间','验证剩余时间'] as $legacy) {
        p1942_assert($partial[$legacy] === $partial['剩余时间'], 'legacy remaining alias must use generic clock');
    }

    $none = SourceAnnouncementTemplate::authorizationContext([], ['unlock_all' => false, 'app_ids' => []], $now);
    p1942_assert($none['授权状态'] === '已过期或未解锁本源', 'inactive status fallback');
    p1942_assert($none['到期时间'] === '已过期或未解锁本源', 'inactive expiry fallback');
    p1942_assert($none['剩余时间'] === '已过期或未解锁本源', 'inactive remaining fallback');
    p1942_assert(strpos($none['授权摘要'], '全软件源：') === false, 'summary must not expose multi-clock model');
    p1942_assert(strpos($none['授权摘要'], '到期时间：已过期或未解锁本源') !== false, 'summary fallback');

    $root = dirname(__DIR__);
    $view = file_get_contents($root . '/application/admin/view/general/config/index.html');
    $sql = file_get_contents($root . '/release/sql/2026091807_unified_announcement_expiry.sql');

    p1942_assert(strpos($view, '[全源到期时间|未解锁]') === false, 'old editor hint still present');
    p1942_assert(strpos($view, '[到期时间] / [剩余时间]') !== false, 'unified editor hint missing');
    p1942_assert(substr_count($sql, "UPDATE `fa_config`") >= 10, 'migration coverage incomplete');

    echo "OK phase19_4_2_unified_announcement_test one_clock=passed fallback=passed legacy_alias=passed migration=passed\n";
}
