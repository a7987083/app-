<?php

namespace app\index\controller;

use app\common\library\ApiEndpointRegistry;

/**
 * Safe aliases for project-owned APIs created from the admin API center.
 *
 * Custom endpoints never execute arbitrary PHP. They can only point to one of
 * the predefined project handlers.
 */
class ManagedApi
{
    public function dispatch($slug = '')
    {
        $row = ApiEndpointRegistry::customBySlug($slug);
        if (!$row) {
            return json(['code' => 0, 'msg' => 'API不存在'], 404);
        }

        $guard = ApiEndpointRegistry::guard((string)$row['endpoint_key']);
        if ($guard !== null) {
            return $guard;
        }

        $method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string)$_SERVER['REQUEST_METHOD'] : 'GET');
        $allowed = strtoupper(isset($row['method']) ? (string)$row['method'] : 'GET');
        if ($allowed !== 'ANY' && strpos(',' . $allowed . ',', ',' . $method . ',') === false) {
            return json(['code' => 0, 'msg' => 'Method Not Allowed'], 405);
        }

        $handler = isset($row['handler_key']) ? (string)$row['handler_key'] : '';
        $map = [
            'appstore' => 'index/App/list',
            'dylib_config' => 'index/Index/dylib',
            'dylib_auth' => 'index/Index/apiface',
            'unbind' => 'index/Index/unbind',
            'unbind_query' => 'index/Index/unbindQuery',
            'license' => 'index/Index/license',
        ];
        if (!isset($map[$handler])) {
            return json(['code' => 0, 'msg' => 'API处理器不存在'], 500);
        }

        ApiEndpointRegistry::enterAlias($handler);
        return action($map[$handler]);
    }
}
