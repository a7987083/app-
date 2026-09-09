<?php

return [
    'name' => config('api.app.name'),
    'env' => 'local',
    'debug' => (bool) config('api.app.debug'),
    'url' => config('api.app.url'),
    'asset_url' => config('api.app.url'),
    'timezone' => 'Asia/Shanghai',
    'locale' => config('api.default.locale'),
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',
    'key' => config('api.app.key'),
    'cipher' => 'AES-256-CBC',
    'maintenance' => [
        'driver' => 'file',
        'store' => 'database'
    ],
    # Laravel 11 新增密钥串
    'previous_keys' => [
        ...array_filter(
            explode(',', '') # 密钥串 暂未设置变量
        ),
    ],
];
