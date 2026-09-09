<?php

use Illuminate\Support\Str;

return [
    'driver' => config('api.default.session'),
    'lifetime' => 120,
    'expire_on_close' => false,
    'encrypt' => env('SESSION_ENCRYPT', false),
    'files' => storage_path('framework/sessions'),
    'connection' => null,
    'table' => 'sessions',
    'store' => 'redis',
    'lottery' => [2, 100],
    'cookie' => Str::slug(config('app.name'), '_').'_session',
    'path' => '/',
    'domain' => '',
    'secure' => '',
    'http_only' => true,
    'same_site' => 'lax', # Supported: "lax", "strict", "none", null
    'partitioned' => false,
];
