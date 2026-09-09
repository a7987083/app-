<?php

return [
    'default' => config('api.default.mail'),
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'url' => null,
            'host' => config('api.smtp.host'),
            'port' => config('api.smtp.port'),
            'encryption' => config('api.smtp.encryption'),
            'username' => config('api.smtp.username'),
            'password' => config('api.smtp.password'),
            'timeout' => null,
            'local_domain' => '',
        ],
        'ses' => [
            'transport' => 'ses',
        ],
        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],
        'sendmail' => [
            'transport' => 'sendmail',
            'path' => '/usr/sbin/sendmail -bs -i',
        ],
        'log' => [
            'transport' => 'log',
            'channel' => '',
        ],
        'array' => [
            'transport' => 'array',
        ],
        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],
        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
        ],

    ],
    'from' => [
        'address' => config('api.smtp.username'),
        'name' => config('api.app.name'),
    ],
];
