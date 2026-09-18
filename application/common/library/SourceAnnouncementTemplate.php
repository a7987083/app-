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

    /**
     * Public editor variables after the Phase 19.4.x closeout.
     * Authorization intentionally exposes status + remaining time only.
     */
    public static function variables()
    {
        return [
            ['key' => '刷新时间', 'description' => '当前软件源请求时间'],
            ['key' => '软件个数', 'description' => '当前软件源 App 总数'],
            ['key' => '今日更新', 'description' => '今天更新的 App 数量'],
            ['key' => '七日更新', 'description' => '最近 7 天更新的 App 数量'],
            ['key' => '授权状态', 'description' => '当前有效授权状态'],
            ['key' => '剩余时间', 'description' => '当前有效授权剩余时间'],
            ['key' => '服务器运行时间', 'description' => '本软件源服务累计运行时间；持久化保存'],
        ];
    }

    /**
     * Tokens removed from the public announcement contract. They remain
     * recognized only so historical templates never leak raw placeholders.
     */
    public static function deprecatedRemovedKeys()
    {
        return [
            '授权摘要',
            '到期时间',
            '源名称',
            '指定APP数量',
            '全源到期时间',
            '全源剩余时间',
            '部分到期时间',
            '部分剩余时间',
            '验证到期时间',
            '验证剩余时间',
        ];
    }

    protected static function recognizedVariableKeys()
    {
        $keys = [];
        foreach (self::variables() as $variable) {
            $keys[$variable['key']] = true;
        }
        // 服务器时间 is migrated to 服务器运行时间 and remains a runtime alias.
        $keys['服务器时间'] = true;
        foreach (self::deprecatedRemovedKeys() as $key) {
            $keys[$key] = true;
        }
        return array_keys($keys);
    }

    public static function hasVariables($template)
    {
        $template = (string)$template;
        if ($template === '') {
            return false;
        }
        foreach (self::recognizedVariableKeys() as $key) {
            if (strpos($template, '[' . $key) !== false) {
                return true;
            }
        }
        return false;
    }

    public static function requiredVariables($template)
    {
        $required = [];
        $template = (string)$template;
        foreach (self::recognizedVariableKeys() as $key) {
            if (strpos($template, '[' . $key . ']') !== false || strpos($template, '[' . $key . '|') !== false) {
                $required[$key] = true;
            }
        }
        return $required;
    }

    /**
     * Normalize a stored template when it is edited/saved.
     * - old 服务器时间 becomes 服务器运行时间 (default value syntax preserved)
     * - removed variables are deleted, including [key|default] forms
     */
    public static function normalizeTemplate($template)
    {
        $template = (string)$template;
        $template = preg_replace_callback('/\[服务器时间(?:\|([^\[\]]*))?\]/u', function ($matches) {
            return isset($matches[1]) ? '[服务器运行时间|' . $matches[1] . ']' : '[服务器运行时间]';
        }, $template);

        $quoted = [];
        foreach (self::deprecatedRemovedKeys() as $key) {
            $quoted[] = preg_quote($key, '/');
        }
        if ($quoted) {
            $template = preg_replace('/\[(?:' . implode('|', $quoted) . ')(?:\|[^\[\]]*)?\]/u', '', $template);
        }
        return (string)$template;
    }

    public static function render($template, array $context = null)
    {
        $template = (string)$template;
        if ($template === '' || !self::hasVariables($template)) {
            return $template;
        }
        $context = $context === null ? self::$context : $context;
        $removed = array_flip(self::deprecatedRemovedKeys());

        return preg_replace_callback('/\[([^\[\]\|]+)(?:\|([^\[\]]*))?\]/u', function ($matches) use ($context, $removed) {
            $key = trim((string)$matches[1]);
            // Removed tokens are always deleted. Their historical |default is
            // intentionally ignored so they cannot continue to surface.
            if (isset($removed[$key])) {
                return '';
            }
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
        $runtime = SourceServerRuntime::display($now);
        $context = [
            '刷新时间' => $formattedNow,
            '服务器运行时间' => $runtime,
            // Legacy alias: old templates now show elapsed runtime rather than wall time.
            '服务器时间' => $runtime,
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

        $needsAuthorization = isset($required['授权状态']) || isset($required['剩余时间']);
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

        // Permission scopes stay separate internally; the announcement chooses
        // one effective clock in business priority order.
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
            $status = '已过期或未解锁本源';
        }

        $fallback = '已过期或未解锁本源';
        $activeRemaining = $activeExpire > $now ? self::formatRemaining($activeExpire - $now) : $fallback;

        return [
            '授权状态' => $status,
            '剩余时间' => $activeRemaining,
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
