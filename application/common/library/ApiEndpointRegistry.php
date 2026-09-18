<?php

namespace app\common\library;

use think\Cache;
use think\Db;

/**
 * Registry and runtime guard for project-owned public API endpoints.
 *
 * FastAdmin native /api/* endpoints are intentionally outside this registry.
 * Missing tables fail open so an interrupted migration can never take the
 * software-source service offline.
 */
class ApiEndpointRegistry
{
    const STATE_CACHE_KEY = 'zonoe_project_api_states_v1';
    const STATE_CACHE_TTL = 30;

    protected static $requestStartedAt = 0.0;
    protected static $requestEndpoint = '';
    protected static $requestLoggingRegistered = false;
    protected static $bypassHandler = '';
    protected static $systemSyncAttempted = false;

    public static function systemDefinitions()
    {
        return [
            'appstore' => [
                'endpoint_key' => 'appstore',
                'name' => '软件源接口',
                'path' => '/appstore',
                'method' => 'ANY',
                'source' => 'system',
                'auth' => 'UDID/卡密',
                'handler_key' => 'appstore',
                'enabled' => 1,
                'description' => '添加、刷新软件源与卡密激活',
                'test_method' => 'GET',
                'test_fields' => [
                    ['name' => 'udid', 'label' => 'UDID', 'required' => true, 'placeholder' => '设备 UDID'],
                    ['name' => 'code', 'label' => '卡密', 'required' => false, 'placeholder' => '激活时填写；仅刷新可留空'],
                ],
            ],
            'dylib_config' => [
                'endpoint_key' => 'dylib_config',
                'name' => '远程Dylib配置',
                'path' => '/index/index/dylib',
                'method' => 'GET',
                'source' => 'system',
                'auth' => 'UDID',
                'handler_key' => 'dylib_config',
                'enabled' => 1,
                'description' => '远程Dylib配置与授权状态',
                'test_method' => 'GET',
                'test_fields' => [
                    ['name' => 'udid', 'label' => 'UDID', 'required' => true, 'placeholder' => '设备 UDID'],
                ],
            ],
            'dylib_auth' => [
                'endpoint_key' => 'dylib_auth',
                'name' => 'Dylib授权',
                'path' => '/index/index/apiface',
                'method' => 'GET',
                'source' => 'system',
                'auth' => 'UDID+HMAC',
                'handler_key' => 'dylib_auth',
                'enabled' => 1,
                'description' => '动态库授权验证',
                // Dylib/HMAC protocol is intentionally not redesigned in this closeout.
                'test_method' => 'GET',
                'test_fields' => [
                    ['name' => 'udid', 'label' => 'UDID', 'required' => true, 'placeholder' => '设备 UDID'],
                ],
            ],
            'unbind' => [
                'endpoint_key' => 'unbind',
                'name' => '换绑接口',
                'path' => '/unbind',
                'method' => 'GET,POST',
                'source' => 'system',
                'auth' => '卡密+UDID',
                'handler_key' => 'unbind',
                'enabled' => 1,
                'description' => '设备换绑页面与提交',
                'test_method' => 'POST',
                'test_fields' => [
                    ['name' => 'code', 'label' => '卡密', 'required' => true, 'placeholder' => '卡密'],
                    ['name' => 'old_udid', 'label' => '原 UDID', 'required' => true, 'placeholder' => '原设备 UDID'],
                    ['name' => 'new_udid', 'label' => '新 UDID', 'required' => true, 'placeholder' => '新设备 UDID'],
                ],
            ],
            'unbind_query' => [
                'endpoint_key' => 'unbind_query',
                'name' => '换绑查询',
                'path' => '/unbind/query',
                'method' => 'GET',
                'source' => 'system',
                'auth' => 'UDID',
                'handler_key' => 'unbind_query',
                'enabled' => 1,
                'description' => '查询换绑状态',
                'test_method' => 'GET',
                'test_fields' => [
                    ['name' => 'udid', 'label' => 'UDID', 'required' => true, 'placeholder' => '设备 UDID'],
                ],
            ],
            'license' => [
                'endpoint_key' => 'license',
                'name' => '授权查询',
                'path' => '/authorization',
                'method' => 'GET,POST',
                'source' => 'system',
                'auth' => '卡密+UDID',
                'handler_key' => 'license',
                'enabled' => 1,
                'description' => '查询授权信息；/license 保留兼容，若 Nginx 拦截请使用 /authorization',
                'test_method' => 'POST',
                'test_fields' => [
                    ['name' => 'code', 'label' => '卡密', 'required' => true, 'placeholder' => '卡密'],
                    ['name' => 'udid', 'label' => 'UDID', 'required' => true, 'placeholder' => '设备 UDID'],
                ],
            ],
        ];
    }

    protected static function persistentDefinition(array $row)
    {
        $keys = ['endpoint_key', 'name', 'path', 'method', 'source', 'auth', 'handler_key', 'description'];
        $data = [];
        foreach ($keys as $key) {
            $data[$key] = isset($row[$key]) ? $row[$key] : '';
        }
        return $data;
    }

    /**
     * System definition is the single source of truth for system API metadata.
     * The operator-owned enabled flag is deliberately preserved.
     */
    public static function syncSystemDefinitions()
    {
        if (self::$systemSyncAttempted) {
            return true;
        }
        self::$systemSyncAttempted = true;
        $now = time();
        try {
            foreach (self::systemDefinitions() as $key => $definition) {
                $row = Db::table('fa_api_endpoint')->where('endpoint_key', $key)->find();
                $data = self::persistentDefinition($definition);
                $data['updatetime'] = $now;
                if ($row) {
                    // Never convert a user-created custom row into a system row.
                    if (isset($row['source']) && (string)$row['source'] !== '' && (string)$row['source'] !== 'system') {
                        continue;
                    }
                    Db::table('fa_api_endpoint')->where('id', (int)$row['id'])->update($data);
                } else {
                    $data['enabled'] = !empty($definition['enabled']) ? 1 : 0;
                    $data['createtime'] = $now;
                    Db::table('fa_api_endpoint')->insert($data);
                }
            }
            self::forget();
            return true;
        } catch (\Throwable $e) {
            // A missing/mid-migration table must not break public APIs or admin.
            return false;
        }
    }

    protected static function overlaySystemDefinition(array $row)
    {
        $key = isset($row['endpoint_key']) ? (string)$row['endpoint_key'] : '';
        $definitions = self::systemDefinitions();
        if (!isset($definitions[$key]) || (isset($row['source']) && (string)$row['source'] !== 'system')) {
            return $row;
        }
        $enabled = isset($row['enabled']) ? (int)$row['enabled'] : (int)$definitions[$key]['enabled'];
        $id = isset($row['id']) ? $row['id'] : null;
        $created = isset($row['createtime']) ? $row['createtime'] : null;
        $updated = isset($row['updatetime']) ? $row['updatetime'] : null;
        $row = array_merge($row, $definitions[$key]);
        $row['enabled'] = $enabled;
        if ($id !== null) $row['id'] = $id;
        if ($created !== null) $row['createtime'] = $created;
        if ($updated !== null) $row['updatetime'] = $updated;
        return $row;
    }

    public static function handlerOptions()
    {
        $result = [];
        foreach (self::systemDefinitions() as $key => $row) {
            $result[$key] = $row['name'];
        }
        return $result;
    }

    public static function all($domain = '')
    {
        self::syncSystemDefinitions();
        $rows = [];
        try {
            $rows = Db::table('fa_api_endpoint')->order('source asc,id asc')->select();
            $rows = is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            $rows = array_values(self::systemDefinitions());
        }

        $today = strtotime(date('Y-m-d 00:00:00'));
        $stats = [];
        try {
            $statRows = Db::query(
                "SELECT endpoint_key,COUNT(*) AS today_requests,MAX(addtime) AS last_request,"
                . "ROUND(AVG(duration_ms),2) AS avg_ms "
                . "FROM fa_api_request_log WHERE addtime>=? GROUP BY endpoint_key",
                [$today]
            );
            foreach ($statRows as $stat) {
                $stats[(string)$stat['endpoint_key']] = $stat;
            }
        } catch (\Throwable $e) {
            $stats = [];
        }

        foreach ($rows as &$row) {
            $row = self::overlaySystemDefinition($row);
            $key = isset($row['endpoint_key']) ? (string)$row['endpoint_key'] : '';
            $stat = isset($stats[$key]) ? $stats[$key] : [];
            $row['today_requests'] = isset($stat['today_requests']) ? (int)$stat['today_requests'] : 0;
            $row['last_request'] = isset($stat['last_request']) ? (int)$stat['last_request'] : 0;
            $row['avg_ms'] = isset($stat['avg_ms']) ? (float)$stat['avg_ms'] : 0.0;
            $row['full_url'] = rtrim((string)$domain, '/') . (isset($row['path']) ? $row['path'] : '');
        }
        unset($row);

        return $rows;
    }

    public static function endpoint($endpointKey)
    {
        $endpointKey = trim((string)$endpointKey);
        if ($endpointKey === '') {
            return null;
        }
        self::syncSystemDefinitions();
        try {
            $row = Db::table('fa_api_endpoint')->where('endpoint_key', $endpointKey)->find();
            if (!$row) {
                return null;
            }
            return self::overlaySystemDefinition($row);
        } catch (\Throwable $e) {
            $definitions = self::systemDefinitions();
            return isset($definitions[$endpointKey]) ? $definitions[$endpointKey] : null;
        }
    }

    public static function testSchema($endpointKey)
    {
        $row = self::endpoint($endpointKey);
        if (!$row) {
            return null;
        }
        $definitions = self::systemDefinitions();
        $definition = null;
        if (isset($row['source']) && (string)$row['source'] === 'system' && isset($definitions[$endpointKey])) {
            $definition = $definitions[$endpointKey];
        } else {
            $handler = isset($row['handler_key']) ? (string)$row['handler_key'] : '';
            if (isset($definitions[$handler])) {
                $definition = $definitions[$handler];
            }
        }
        $fields = $definition && isset($definition['test_fields']) && is_array($definition['test_fields'])
            ? $definition['test_fields'] : [];
        $testMethod = $definition && !empty($definition['test_method'])
            ? strtoupper((string)$definition['test_method']) : strtoupper((string)$row['method']);
        if (!in_array($testMethod, ['GET', 'POST'], true)) {
            $testMethod = strpos(strtoupper((string)$row['method']), 'POST') !== false ? 'POST' : 'GET';
        }
        return [
            'endpoint_key' => $endpointKey,
            'name' => isset($row['name']) ? (string)$row['name'] : $endpointKey,
            'path' => isset($row['path']) ? (string)$row['path'] : '',
            'method' => $testMethod,
            'auth' => isset($row['auth']) ? (string)$row['auth'] : '',
            'fields' => $fields,
        ];
    }

    public static function recentLogs($limit = 100)
    {
        $limit = max(1, min(500, (int)$limit));
        try {
            $rows = Db::table('fa_api_request_log')->order('id desc')->limit($limit)->select();
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function guard($endpointKey)
    {
        $endpointKey = (string)$endpointKey;

        if (self::$bypassHandler !== '' && self::$bypassHandler === $endpointKey) {
            self::$bypassHandler = '';
            return null;
        }

        self::beginRequestLog($endpointKey);
        if (self::enabled($endpointKey)) {
            return null;
        }

        if (!headers_sent()) {
            http_response_code(503);
            header('Cache-Control: no-store');
        }
        return json([
            'code' => 0,
            'msg' => 'API已关闭',
            'endpoint' => $endpointKey,
        ], 503);
    }

    public static function enabled($endpointKey)
    {
        $states = self::states();
        if (array_key_exists($endpointKey, $states)) {
            return (bool)$states[$endpointKey];
        }

        $defaults = self::systemDefinitions();
        return !isset($defaults[$endpointKey]) || !empty($defaults[$endpointKey]['enabled']);
    }

    public static function states()
    {
        try {
            $cached = Cache::get(self::STATE_CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }

            $rows = Db::table('fa_api_endpoint')->field('endpoint_key,enabled')->select();
            $states = [];
            foreach ((array)$rows as $row) {
                if (!isset($row['endpoint_key'])) {
                    continue;
                }
                $states[(string)$row['endpoint_key']] = !empty($row['enabled']) ? 1 : 0;
            }
            Cache::set(self::STATE_CACHE_KEY, $states, self::STATE_CACHE_TTL);
            return $states;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function forget()
    {
        try {
            Cache::rm(self::STATE_CACHE_KEY);
        } catch (\Throwable $e) {
        }
    }

    public static function toggle($endpointKey, $enabled)
    {
        $endpointKey = trim((string)$endpointKey);
        if ($endpointKey === '') {
            throw new \InvalidArgumentException('接口标识不能为空');
        }

        self::syncSystemDefinitions();
        $row = Db::table('fa_api_endpoint')->where('endpoint_key', $endpointKey)->find();
        if (!$row) {
            throw new \InvalidArgumentException('API不存在或尚未完成数据库迁移');
        }

        $target = $enabled ? 1 : 0;
        $updated = Db::table('fa_api_endpoint')
            ->where('endpoint_key', $endpointKey)
            ->update([
                'enabled' => $target,
                'updatetime' => time(),
            ]);
        if ($updated === false) {
            throw new \RuntimeException('接口开关更新失败');
        }

        $verified = Db::table('fa_api_endpoint')
            ->where('endpoint_key', $endpointKey)
            ->value('enabled');
        if ((int)$verified !== $target) {
            throw new \RuntimeException('接口开关写入后校验失败');
        }

        self::forget();
        return true;
    }

    public static function saveCustom(array $data, $id = 0)
    {
        $id = (int)$id;
        $name = trim(isset($data['name']) ? (string)$data['name'] : '');
        $slug = strtolower(trim(isset($data['slug']) ? (string)$data['slug'] : ''));
        $method = strtoupper(trim(isset($data['method']) ? (string)$data['method'] : 'GET'));
        $auth = trim(isset($data['auth']) ? (string)$data['auth'] : '');
        $handlerKey = trim(isset($data['handler_key']) ? (string)$data['handler_key'] : '');
        $description = trim(isset($data['description']) ? (string)$data['description'] : '');
        $enabled = !empty($data['enabled']) ? 1 : 0;

        if ($name === '' || !preg_match('/^[a-z0-9][a-z0-9_-]{1,48}$/', $slug)) {
            throw new \InvalidArgumentException('名称不能为空，API别名仅允许字母、数字、下划线和中划线');
        }
        if (!isset(self::systemDefinitions()[$handlerKey])) {
            throw new \InvalidArgumentException('无效的处理器');
        }
        if (!in_array($method, ['GET', 'POST', 'ANY'], true)) {
            throw new \InvalidArgumentException('Method仅支持GET/POST/ANY');
        }

        $path = '/project-api/' . $slug;
        $now = time();
        if ($id > 0) {
            $row = Db::table('fa_api_endpoint')->where('id', $id)->find();
            if (!$row || (isset($row['source']) && $row['source'] === 'system')) {
                throw new \InvalidArgumentException('系统API不能通过自定义编辑器修改');
            }
            $endpointKey = (string)$row['endpoint_key'];
            Db::table('fa_api_endpoint')->where('id', $id)->update([
                'name' => $name,
                'path' => $path,
                'method' => $method,
                'auth' => $auth,
                'handler_key' => $handlerKey,
                'enabled' => $enabled,
                'description' => $description,
                'updatetime' => $now,
            ]);
        } else {
            $endpointKey = 'custom:' . $slug;
            Db::table('fa_api_endpoint')->insert([
                'endpoint_key' => $endpointKey,
                'name' => $name,
                'path' => $path,
                'method' => $method,
                'source' => 'custom',
                'auth' => $auth,
                'handler_key' => $handlerKey,
                'enabled' => $enabled,
                'description' => $description,
                'createtime' => $now,
                'updatetime' => $now,
            ]);
        }
        self::forget();
        return $endpointKey;
    }

    public static function deleteCustom($id)
    {
        $id = (int)$id;
        $row = Db::table('fa_api_endpoint')->where('id', $id)->find();
        if (!$row) {
            throw new \InvalidArgumentException('API不存在');
        }
        if (isset($row['source']) && $row['source'] === 'system') {
            throw new \InvalidArgumentException('系统API不可删除，只能关闭');
        }
        Db::table('fa_api_endpoint')->where('id', $id)->delete();
        self::forget();
        return true;
    }

    public static function customBySlug($slug)
    {
        $slug = strtolower(trim((string)$slug));
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,48}$/', $slug)) {
            return null;
        }
        try {
            return Db::table('fa_api_endpoint')
                ->where('path', '/project-api/' . $slug)
                ->where('source', 'custom')
                ->find();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function enterAlias($handlerKey)
    {
        self::$bypassHandler = (string)$handlerKey;
    }

    protected static function beginRequestLog($endpointKey)
    {
        if (self::$requestLoggingRegistered) {
            return;
        }
        self::$requestLoggingRegistered = true;
        self::$requestStartedAt = microtime(true);
        self::$requestEndpoint = (string)$endpointKey;

        register_shutdown_function(function () {
            $duration = self::$requestStartedAt > 0
                ? (int)round((microtime(true) - self::$requestStartedAt) * 1000)
                : 0;
            $status = http_response_code();
            if (!$status) {
                $status = 200;
            }
            $uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
            $path = parse_url($uri, PHP_URL_PATH);
            $method = isset($_SERVER['REQUEST_METHOD']) ? (string)$_SERVER['REQUEST_METHOD'] : '';
            $ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';

            try {
                Db::table('fa_api_request_log')->insert([
                    'endpoint_key' => self::$requestEndpoint,
                    'method' => substr($method, 0, 16),
                    'path' => substr((string)$path, 0, 255),
                    'ip' => substr($ip, 0, 64),
                    'status_code' => (int)$status,
                    'duration_ms' => max(0, $duration),
                    'addtime' => time(),
                ]);
            } catch (\Throwable $e) {
            }
        });
    }
}
