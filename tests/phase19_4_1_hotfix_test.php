<?php

namespace app\common\library {
    class CardAccessPolicy
    {
        const SCOPE_SOURCE = 1;
        const SCOPE_VERIFY = 2;
        const SCOPE_APPS = 3;

        public static function normalizeAppIds(array $ids)
        {
            $clean = [];
            foreach ($ids as $id) {
                $id = (int)$id;
                if ($id > 0) {
                    $clean[$id] = true;
                }
            }
            $ids = array_keys($clean);
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

    function p1941_assert($condition, $message)
    {
        if (!$condition) {
            fwrite(STDERR, "FAIL phase19_4_1_hotfix_test: {$message}\n");
            exit(1);
        }
    }

    $now = strtotime('2026-09-18 12:00:00');

    $full = [
        ['jh' => 1, 'card_scope' => 1, 'endtime' => $now + 86400],
        ['jh' => 1, 'card_scope' => 3, 'endtime' => $now + 86400 * 5],
        ['jh' => 1, 'card_scope' => 2, 'endtime' => $now + 86400 * 9],
    ];
    $ctx = SourceAnnouncementTemplate::authorizationContext($full, ['unlock_all' => true, 'app_ids' => [7, 9]], $now);
    p1941_assert($ctx['授权状态'] === '已授权', 'full source must win priority');
    p1941_assert($ctx['到期时间'] === date('Y-m-d H:i:s', $now + 86400), 'full source expiry must win over later lower-priority cards');

    $partial = [
        ['jh' => 1, 'card_scope' => 3, 'endtime' => $now + 86400 * 2],
        ['jh' => 1, 'card_scope' => 3, 'endtime' => $now + 86400 * 5],
        ['jh' => 1, 'card_scope' => 2, 'endtime' => $now + 86400 * 9],
    ];
    $ctx = SourceAnnouncementTemplate::authorizationContext($partial, ['unlock_all' => false, 'app_ids' => [7, 9]], $now);
    p1941_assert($ctx['授权状态'] === '部分App授权', 'partial App scope must win over verify');
    p1941_assert($ctx['到期时间'] === date('Y-m-d H:i:s', $now + 86400 * 5), 'partial App expiry must use latest active App-scoped card');
    p1941_assert($ctx['剩余时间'] === '5天0小时', 'partial App remaining time must be rendered');
    p1941_assert($ctx['部分到期时间'] === $ctx['到期时间'], 'partial specific expiry mismatch');

    $verify = [
        ['jh' => 1, 'card_scope' => 2, 'endtime' => $now + 7200],
    ];
    $ctx = SourceAnnouncementTemplate::authorizationContext($verify, ['unlock_all' => false, 'app_ids' => []], $now);
    p1941_assert($ctx['授权状态'] === '仅验证', 'verify must be selected when no source/App authorization exists');
    p1941_assert($ctx['到期时间'] === date('Y-m-d H:i:s', $now + 7200), 'verify expiry mismatch');
    p1941_assert($ctx['剩余时间'] === '2小时0分钟', 'verify remaining mismatch');

    $root = dirname(__DIR__);
    $route = file_get_contents($root . '/application/route.php');
    $registry = file_get_contents($root . '/application/common/library/ApiEndpointRegistry.php');
    $sql = file_get_contents($root . '/release/sql/2026091806_announcement_priority.sql');

    p1941_assert(strpos($route, "Route::rule('license','index/Index/license')") !== false, 'legacy /license route must remain');
    p1941_assert(strpos($route, "Route::rule('authorization','index/Index/license')") !== false, 'safe /authorization route missing');
    p1941_assert(strpos($registry, "'path' => '/authorization'") !== false, 'API Center must advertise safe authorization URL');
    p1941_assert(strpos($sql, "'[全源到期时间]', '[到期时间]'") !== false, 'legacy expiry placeholder migration missing');
    p1941_assert(strpos($sql, "'[全源剩余时间]', '[剩余时间]'") !== false, 'legacy remaining placeholder migration missing');

    echo "OK phase19_4_1_hotfix_test priority=full>partial>verify partial_expiry=passed authorization_route=passed migration=passed\n";
}
