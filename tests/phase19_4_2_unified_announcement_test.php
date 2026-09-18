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

    foreach (['刷新时间','软件个数','今日更新','七日更新','授权状态','剩余时间','服务器运行时间'] as $required) {
        p1942_assert(in_array($required, $keys, true), 'public variable missing: ' . $required);
    }
    foreach ([
        '到期时间','授权摘要','源名称','指定APP数量','服务器时间',
        '全源到期时间','全源剩余时间','部分到期时间','部分剩余时间','验证到期时间','验证剩余时间'
    ] as $removed) {
        p1942_assert(!in_array($removed, $keys, true), 'retired variable still exposed: ' . $removed);
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
    p1942_assert($partial['剩余时间'] === '5天0小时', 'partial remaining');
    foreach (['到期时间','授权摘要','指定APP数量','全源到期时间','部分到期时间','验证到期时间'] as $removed) {
        p1942_assert(!array_key_exists($removed, $partial), 'retired authorization key returned: ' . $removed);
    }

    $none = SourceAnnouncementTemplate::authorizationContext([], ['unlock_all' => false, 'app_ids' => []], $now);
    p1942_assert($none['授权状态'] === '已过期或未解锁本源', 'inactive status fallback');
    p1942_assert($none['剩余时间'] === '已过期或未解锁本源', 'inactive remaining fallback');

    $legacyTemplate = "状态：[授权状态]\n到期：[到期时间]\n摘要：[授权摘要]\n源：[源名称]\n数量：[指定APP数量]\n时间：[服务器时间]";
    $normalized = SourceAnnouncementTemplate::normalizeTemplate($legacyTemplate);
    p1942_assert(strpos($normalized, '[到期时间]') === false, 'expiry token not removed');
    p1942_assert(strpos($normalized, '[授权摘要]') === false, 'summary token not removed');
    p1942_assert(strpos($normalized, '[源名称]') === false, 'source token not removed');
    p1942_assert(strpos($normalized, '[指定APP数量]') === false, 'app count token not removed');
    p1942_assert(strpos($normalized, '[服务器运行时间]') !== false, 'server time token not migrated');

    $root = dirname(__DIR__);
    $sql = file_get_contents($root . '/release/sql/2026091808_phase19_4_x_closeout.sql');
    p1942_assert(strpos($sql, "`path`='/authorization'") !== false, 'authorization API migration missing');
    p1942_assert(strpos($sql, '[服务器运行时间]') !== false, 'runtime token migration missing');

    echo "OK phase19_4_2_unified_announcement_test closeout=passed remaining=passed retired_tokens=passed migration=passed\n";
}
