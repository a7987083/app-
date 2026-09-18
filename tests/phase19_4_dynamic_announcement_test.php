<?php

namespace app\common\library {
    class SourceAppRecord
    {
        public static function value(array $row, $name, $default = null)
        {
            return array_key_exists($name, $row) ? $row[$name] : $default;
        }
    }

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
                if ($id > 0) $clean[$id] = true;
            }
            $ids = array_keys($clean);
            sort($ids, SORT_NUMERIC);
            return $ids;
        }

        public static function scopeForRow(array $row)
        {
            return isset($row['card_scope']) ? (int)$row['card_scope'] : 1;
        }

        public static function isActive(array $row, $now = null)
        {
            return !empty($row['jh']) && (int)$row['endtime'] > (int)$now;
        }

        public static function sourceAccess(array $rows, array $map = [], $now = null)
        {
            $unlockAll = false;
            $ids = [];
            foreach ($rows as $row) {
                if (!self::isActive($row, $now)) continue;
                $scope = self::scopeForRow($row);
                if ($scope === self::SCOPE_SOURCE) $unlockAll = true;
                if ($scope === self::SCOPE_APPS && isset($map[(int)$row['id']])) {
                    foreach ($map[(int)$row['id']] as $id) $ids[(int)$id] = true;
                }
            }
            return ['unlock_all' => $unlockAll, 'app_ids' => array_keys($ids)];
        }
    }
}

namespace {
    require __DIR__ . '/../application/common/library/SourceAnnouncementTemplate.php';

    use app\common\library\SourceAnnouncementTemplate;

    function p194_assert($condition, $message)
    {
        if (!$condition) {
            fwrite(STDERR, "FAIL phase19_4_dynamic_announcement_test: {$message}\n");
            exit(1);
        }
    }

    $now = strtotime('2026-09-18 10:00:00');
    $apps = [
        ['updated_at' => $now - 60],
        ['updated_at' => $now - 86400 * 2],
        ['updated_at' => $now - 86400 * 10],
    ];
    $cards = [
        ['id' => 1, 'jh' => 1, 'card_scope' => 1, 'endtime' => $now + 86400 * 3 + 3600 * 2],
        ['id' => 2, 'jh' => 1, 'card_scope' => 2, 'endtime' => $now + 86400],
        ['id' => 3, 'jh' => 1, 'card_scope' => 3, 'endtime' => $now + 86400 * 5],
        ['id' => 4, 'jh' => 1, 'card_scope' => 1, 'endtime' => $now - 1],
    ];
    $access = ['unlock_all' => true, 'app_ids' => [9, 7, 9]];
    $template = "[源名称]\n刷新：[刷新时间]\nApp：[软件个数] 今日：[今日更新] 七日：[七日更新]\n状态：[授权状态]\n全源：[全源到期时间|未解锁] / [全源剩余时间|0]\n验证：[验证到期时间|未开通]\n指定：[指定APP数量]\n[授权摘要]";

    $context = SourceAnnouncementTemplate::buildContext($template, 'ZONOE', $apps, $cards, $access, $now);
    SourceAnnouncementTemplate::begin($context);
    $rendered = SourceAnnouncementTemplate::render($template);

    p194_assert(strpos($rendered, 'ZONOE') !== false, 'source name rendered');
    p194_assert(strpos($rendered, 'App：3') !== false, 'app count rendered');
    p194_assert(strpos($rendered, '今日：1') !== false, 'today count rendered');
    p194_assert(strpos($rendered, '七日：2') !== false, 'seven day count rendered');
    p194_assert(strpos($rendered, '状态：已授权') !== false, 'authorization status rendered');
    p194_assert(strpos($rendered, '3天2小时') !== false, 'remaining duration rendered');
    p194_assert(strpos($rendered, '指定：2') !== false, 'deduplicated app authorization count rendered');

    $payload = ['name' => 'ZONOE', 'message' => $template, 'apps' => []];
    $placeholder = SourceAnnouncementTemplate::placeholderPayload($payload);
    p194_assert($placeholder['message'] === SourceAnnouncementTemplate::SENTINEL, 'dynamic message replaced by sentinel');
    $json = json_encode($placeholder, 320);
    $injected = SourceAnnouncementTemplate::injectEncodedMessage($json, $template, 320);
    p194_assert(strpos($injected, SourceAnnouncementTemplate::SENTINEL) === false, 'sentinel removed before response');
    p194_assert(strpos($injected, '已授权') !== false, 'rendered value injected into encoded JSON');

    $guest = SourceAnnouncementTemplate::authorizationContext([], ['unlock_all' => false, 'app_ids' => []], $now);
    $guestRendered = SourceAnnouncementTemplate::render(
        '[授权状态] [全源到期时间|未解锁] [验证到期时间|未开通]',
        $guest
    );
    p194_assert($guestRendered === '未授权 未解锁 未开通', 'default value syntax works for guest');

    $static = '普通公告，不含变量';
    p194_assert(SourceAnnouncementTemplate::render($static) === $static, 'static announcement remains byte-identical');
    p194_assert(SourceAnnouncementTemplate::placeholderPayload(['message' => $static])['message'] === $static, 'static payload untouched');

    $root = dirname(__DIR__);
    $app = file_get_contents($root . '/application/index/controller/App.php');
    $response = file_get_contents($root . '/application/common/library/SourceResponse.php');
    $cache = file_get_contents($root . '/application/common/library/SourceLegacyCache.php');
    $config = file_get_contents($root . '/application/admin/controller/general/Config.php');
    $manifest = file_get_contents($root . '/release/online-update-files.txt');

    p194_assert(strpos($app, 'SourceAnnouncementTemplate::buildContext') !== false, 'App integration missing');
    p194_assert(strpos($response, 'SourceAnnouncementTemplate::placeholderPayload') !== false, 'plain response placeholder integration missing');
    p194_assert(strpos($response, 'SourceAnnouncementTemplate::injectEncodedMessage') !== false, 'plain response injection missing');
    p194_assert(strpos($cache, 'SourceAnnouncementTemplate::placeholderPayload') !== false, 'encrypted cache placeholder integration missing');
    p194_assert(strpos($cache, 'SourceAnnouncementTemplate::injectEncodedMessage') !== false, 'encrypted cache injection missing');
    p194_assert(strpos($config, 'announcement_preview') !== false, 'admin announcement preview endpoint missing');
    p194_assert(strpos($manifest, "application/common/library/SourceAnnouncementTemplate.php\n") !== false, 'announcement runtime missing from update manifest');
    p194_assert(strpos($manifest, "application/index/view/index/license.html\n") !== false, 'license view missing from update manifest');
    p194_assert(strpos($manifest, "application/index/view/index/unbind.html\n") !== false, 'unbind view missing from update manifest');
    p194_assert(strpos($manifest, "application/common/library/AuthorizationLicense.php\n") !== false, 'AuthorizationLicense missing from update manifest');

    echo "OK phase19_4_dynamic_announcement_test cache_safe=passed auth_isolated=passed defaults=passed admin=passed manifest=passed\n";
}
