<?php

use Illuminate\Support\Str;

return [
    'default' => config('api.default.cache'),
    'stores' => [
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],
        'database' => [
            'driver' => 'database',
            'connection' => null,
            'table' => 'cache',
            'lock_connection' => null,
            'lock_table' => null,
        ],
        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],
        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => null,
            'sasl' => [
                null,
                null,
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => '127.0.0.1',
                    'port' => 11211,
                    'weight' => 100,
                ],
            ],
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' =>  'default',
        ],
        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => null,
            'secret' => null,
            'region' => 'us-east-1',
            'table' => 'cache',
            'endpoint' => null,
        ],
        'octane' => [
            'driver' => 'octane',
        ],
    ],
    'prefix' => Str::slug(config('app.name'), '_').'_cache_',
];
