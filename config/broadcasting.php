<?php

return [
    'default' => 'log',
    'connections' => [
        'reverb' => [
            'driver' => 'reverb',
            'key' => '',
            'secret' => '',
            'app_id' => '',
            'options' => [
                'host' => '',
                'port' => 443,
                'scheme' => 'https',
                'useTLS' => 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],
        'pusher' => [
            'driver' => 'pusher',
            'key' => '',
            'secret' => '',
            'app_id' => '',
            'options' => [
                'cluster' => '',
                'host' => 'api-mt1.pusher.com',
                'port' => 443,
                'scheme' => 'https',
                'encrypted' => true,
                'useTLS' => 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],
        'ably' => [
            'driver' => 'ably',
            'key' => '',
        ],
        'log' => [
            'driver' => 'log',
        ],
        'null' => [
            'driver' => 'null',
        ],
    ],

];
