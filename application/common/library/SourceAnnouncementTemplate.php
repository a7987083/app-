<?php

namespace app\common\library;

use think\Db;

/**
 * Dynamic software-source announcement renderer.
 *
 * The configured message remains a static template so public source caches can
 * stay shared. Request-specific values are injected only after a cached JSON
 * body/static prefix is obtained and before source encryption/transport.
 */
class SourceAnnouncementTemplate
{
    const SENTINEL = '__ZONOE_DYNAMIC_ANNOUNCEMENT_V1_7E9A54A8__';

    protected static $context = [];

    public static function begin(array $context)
    {
        self::$context = $context;
    }

    public static function reset()
    {
        self::$context = [];
    }

    public static function context()
    {
        return self::$context;
    }

    public static function variables()
    {
        return [
            ['key' => '刷新时间', 'description' => '当前软件源请求时间'],
            ['key' => '软件个数', 'description' => '当前软件源 App 总数'],
            ['key' => '今日更新', 'description' => '今天更新的 App 数量'],
            ['key' => '七日更新', 'description' => '最近 7 天更新的 App 数量'],
            ['key' => '授权状态', 'description' => '按 全解锁 → 部分App → 仅验证 选择当前有效授权'],
            ['key' => '到期时间', 'description' => '当前有效授权到期时间（全解锁 → 部分App → 仅验证）'],
            ['key' => '剩余时间', 'description' => '当前有效授权剩余时间（全解锁 → 部分App → 仅验证）'],
            ['key' => '全源到期时间', 'description' => '全软件源授权到期时间'],
            ['key' => '全源剩余时间', 'description' => '全软件源授权剩余时间'],
            ['key' => '部分到期时间', 'description' => '指定 App 授权最晚到期时间'],
            ['key' => '部分剩余时间', 'description' => '指定 App 授权剩余时间'],
            ['key' => '验证到期时间', 'description' => '仅验证授权到期时间'],
            ['key' => '指定APP数量', 'description' => '当前有效指定 App 授权数量'],
            ['key' => '授权摘要', 'description' => '当前设备授权概要'],
            ['key' => '源名称', 'description' => '软件源名称'],
            ['key' => '服务器时间', 'description' => '服务器当前时间'],
        ];
    }

    public static function hasVariables($template)
    {
        $template = (string)$template;
        if ($template === '') {
            return false;
        }
        foreach (self::variables() as $variable) {
            if (strpos($template, '[' . $variable['key']) !== false) {
                return true;
            }
        }
        return false;
    }

    public static function requiredVariables($template)
    {
        $required = [];
        $template = (string)$template;
        foreach (self::variables() as $variable) {
            $key = $variable['key'];
            if (strpos($template, '[' . $key . ']') !== false || strpos($template, '[' . $key . '|') !== false) {
                $required[$key] = true;
            }
        }
        return $required;
    }

    public static function render($template, array $context = null)
    {
        $template = (string)$template;
        if ($template === '' || !self::hasVariables($template)) {
            return $template;
        }
        $context = $context === null ? self::$context : $context;

        return preg_replace_callback('/\[([^\[\]\|]+)(?:\|([^\[\]]*))?\]/u', function ($matches) use ($context) {
            $key = trim((string)$matches[1]);
            if (!array_key_exists($key, $context)) {
                return $matches[0];
            }
            $value = $context[$key];
            if ($value === null || $value === '') {
                return isset($matches[2]) ? (string)$matches[2] : '';
            }
            return (string)$value;
        }, $template);
    }

    public static function placeholderPayload(array $payload)
    {
        if (array_key_exists('message', $payload) && self::hasVariables($payload['message'])) {
            $payload['message'] = self::SENTINEL;
        }
        return $payload;
    }

    public static function injectEncodedMessage($encoded, $template, $jsonFlags = 320)
    {
        $encoded = (string)$encoded;
        if ($encoded === '' || !self::hasVariables($template)) {
            return $encoded;
        }
        $needle = json_encode(self::SENTINEL, $jsonFlags);
        $replacement = json_encode(self::render($template), $jsonFlags);
        if (!is_string($needle) || !is_string($replacement)) {
            return $encoded;
        }
        return str_replace($needle, $replacement, $encoded);
    }

    public static function buildContext($template, $sourceName, array $appRows, array $cardRows, array $sourceAccess, $now = null)
    {
        $now = $now === null ? time() : (int)$now;
        $required = self::requiredVariables($template);
        if (!$required) {
            return [];
        }

        $formattedNow = date('Y-m-d H:i:s', $now);
        $context = [
            '刷新时间' => $formattedNow,
            '服务器时间' => $formattedNow,
            '源名称' => (string)$sourceName,
        ];

        if (isset($required['软件个数'])) {
            $context['软件个数'] = (string)count($appRows);
        }

        if (isset($required['今日更新']) || isset($required['七日更新'])) {
            $todayStart = strtotime(date('Y-m-d 00:00:00', $now));
            $sevenDayStart = $now - 7 * 86400;
            $today = 0;
            $sevenDays = 0;
            foreach ($appRows as $row) {
                $updatedAt = (int)SourceAppRecord::value($row, 'updated_at', 0);
                if ($updatedAt >= $todayStart && $updatedAt <= $now) {
                    $today++;
                }
                if ($updatedAt >= $sevenDayStart && $updatedAt <= $now) {
                    $sevenDays++;
                }
            }
            if (isset($required['今日更新'])) {
                $context['今日更新'] = (string)$today;
            }
            if (isset($required['七日更新'])) {
                $context['七日更新'] = (string)$sevenDays;
            }
        }

        $needsAuthorization = isset($required['授权状态'])
            || isset($required['到期时间'])
            || isset($required['剩余时间'])
            || isset($required['全源到期时间'])
            || isset($required['全源剩余时间'])
            || isset($required['部分到期时间'])
            || isset($required['部分剩余时间'])
            || isset($required['验证到期时间'])
            || isset($required['指定APP数量'])
            || isset($required['授权摘要']);
        if ($needsAuthorization) {
            $authorization = self::authorizationContext($cardRows, $sourceAccess, $now);
            foreach ($authorization as $key => $value) {
                $context[$key] = $value;
            }
        }

        return $context;
    }

    public static function authorizationContext(array $cardRows, array $sourceAccess, $now = null)
    {
        $now = $now === null ? time() : (int)$now;
        $fullExpire = 0;
        $appExpire = 0;
        $verifyExpire = 0;

        foreach ($cardRows as $row) {
            if (!CardAccessPolicy::isActive($row, $now)) {
                continue;
            }
            $endtime = isset($row['endtime']) ? (int)$row['endtime'] : 0;
            $scope = CardAccessPolicy::scopeForRow($row);
            if ($scope === CardAccessPolicy::SCOPE_SOURCE && $endtime > $fullExpire) {
                $fullExpire = $endtime;
            } elseif ($scope === CardAccessPolicy::SCOPE_APPS && $endtime > $appExpire) {
                $appExpire = $endtime;
            } elseif ($scope === CardAccessPolicy::SCOPE_VERIFY && $endtime > $verifyExpire) {
                $verifyExpire = $endtime;
            }
        }

        $appIds = isset($sourceAccess['app_ids']) && is_array($sourceAccess['app_ids'])
            ? CardAccessPolicy::normalizeAppIds($sourceAccess['app_ids'])
            : [];
        $appCount = count($appIds);

        // One generic "current authorization" clock is selected by business
        // priority, never by whichever card happens to have the latest endtime.
        // Priority: full source -> partial App scope -> verification-only.
        $activeExpire = 0;
        if ($fullExpire > $now) {
            $status = '已授权';
            $activeExpire = $fullExpire;
        } elseif ($appCount > 0 && $appExpire > $now) {
            $status = '部分App授权';
            $activeExpire = $appExpire;
        } elseif ($verifyExpire > $now) {
            $status = '仅验证';
            $activeExpire = $verifyExpire;
        } else {
            $status = '未授权';
        }

        $activeTime = $activeExpire > $now ? date('Y-m-d H:i:s', $activeExpire) : '';
        $activeRemaining = $activeExpire > $now ? self::formatRemaining($activeExpire - $now) : '';
        $fullTime = $fullExpire > $now ? date('Y-m-d H:i:s', $fullExpire) : '';
        $appTime = ($appCount > 0 && $appExpire > $now) ? date('Y-m-d H:i:s', $appExpire) : '';
        $verifyTime = $verifyExpire > $now ? date('Y-m-d H:i:s', $verifyExpire) : '';

        $summary = [
            '全软件源：' . ($fullTime !== '' ? '有效至 ' . $fullTime : '未解锁'),
            '指定App：' . $appCount . '个' . ($appTime !== '' ? '，有效至 ' . $appTime : ''),
            '仅验证：' . ($verifyTime !== '' ? '有效至 ' . $verifyTime : '未开通'),
        ];

        return [
            '授权状态' => $status,
            '到期时间' => $activeTime,
            '剩余时间' => $activeRemaining,
            '全源到期时间' => $fullTime,
            '全源剩余时间' => $fullExpire > $now ? self::formatRemaining($fullExpire - $now) : '',
            '部分到期时间' => $appTime,
            '部分剩余时间' => ($appCount > 0 && $appExpire > $now) ? self::formatRemaining($appExpire - $now) : '',
            '验证到期时间' => $verifyTime,
            '指定APP数量' => (string)$appCount,
            '授权摘要' => implode("\n", $summary),
        ];
    }

    public static function sourceAccessForRows(array $cardRows, $now = null)
    {
        return CardAccessPolicy::sourceAccess($cardRows, self::cardAppMap($cardRows), $now);
    }

    public static function cardAppMap(array $rows)
    {
        $ids = [];
        foreach ($rows as $row) {
            if (CardAccessPolicy::scopeForRow($row) === CardAccessPolicy::SCOPE_APPS && !empty($row['id'])) {
                $ids[(int)$row['id']] = true;
            }
        }
        if (!$ids) {
            return [];
        }

        try {
            $mapped = Db::table('fa_kami_app')
                ->where('kami_id', 'in', array_keys($ids))
                ->field('kami_id,app_id')
                ->select();
        } catch (\Exception $e) {
            error_log('[SourceAnnouncementTemplate::cardAppMap] ' . $e->getMessage());
            return [];
        }

        $result = [];
        foreach ($mapped as $row) {
            $kamiId = isset($row['kami_id']) ? (int)$row['kami_id'] : 0;
            $appId = isset($row['app_id']) ? (int)$row['app_id'] : 0;
            if ($kamiId <= 0 || $appId <= 0) {
                continue;
            }
            if (!isset($result[$kamiId])) {
                $result[$kamiId] = [];
            }
            $result[$kamiId][] = $appId;
        }
        foreach ($result as $kamiId => $appIds) {
            $result[$kamiId] = CardAccessPolicy::normalizeAppIds($appIds);
        }
        return $result;
    }

    public static function formatRemaining($seconds)
    {
        $seconds = max(0, (int)$seconds);
        if ($seconds <= 0) {
            return '';
        }
        $days = (int)floor($seconds / 86400);
        $hours = (int)floor(($seconds % 86400) / 3600);
        $minutes = (int)floor(($seconds % 3600) / 60);
        if ($days > 0) {
            return $days . '天' . $hours . '小时';
        }
        if ($hours > 0) {
            return $hours . '小时' . $minutes . '分钟';
        }
        return max(1, $minutes) . '分钟';
    }
}
