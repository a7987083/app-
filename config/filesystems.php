<?php

return [
    'default' => config('api.default.files'),
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => config('app.url').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],
        's3' => [
            'driver' => 's3',
            'key' => null,
            'secret' => null,
            'region' => null,
            'bucket' => null,
            'url' => null,
            'endpoint' => null,
            'use_path_style_endpoint' => false,
            'throw' => false,
        ],

    ],
    'links' => [
        public_path('asset') => storage_path('app/public'),
    ],

];
