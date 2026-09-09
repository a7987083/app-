<?php

return [
    'app' => [
        'name' => '极速网络 Apple 签名系统 Stable',
        'key' => 'base64:7YRaV+dUjnJKP5VyisyMgWR/JQO/mnVAqY/Yf+tVB3M=',
        'url' => 'http://localhost',
        'code' => '',
        'demo' => false,
        'debug' => false,
        'version' => '20260630.1',
        'codes' => [],
        'country' => 'US'
    ],
    'admin' => [
        'path' => 'admin',
        'name' => 'Liuless',
        'email' => '1276117137@qq.com',
        'password' => 'Liuless'
    ],
    'basics' => [
        'ipa_editor' => 'on',
        'cert_new_day' => '31',
        'default_code_generate' => 'on',
        'good_code_generate' => 'on',
        'processing_code_generate' => 'off',
        'own_cert' => 'on',
        'udid_cert' => 'on',
        'free_cert' => 'on',
        'default_code_preferred_create_model' => 'private',
        'first_create_model' => 'private',
        'after_create_model' => 'public',
        'exception_udid' => 'off',
        'auto_cleanup' => '1440',
        'multiple_max' => '5',
        'buy' => null,
        'udid_sign_max' => '1000',
        'not_code_cert_sign_max' => '5',
        'max_processes' => '100',
        'zip_level' => '0',
        'batch_set_id' => '.',
        'batch_set_name_switch' => 'on',
        'batch_set_name' => '-',
        'cert_download' => 'off',
        'list_cache' => 'on',
        'upload_max' => '500',
        'upload_chunk' => '5',
        'code_generate_model' => null,
        'code_generate_prefix' => 'DHM',
        'code_generate_length' => '16',
        'code_generate_character' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        'qnq_top' => 'on',
        'icpbeian' => null,
        'icplicence' => null,
        'wanganbeian' => null,
        'weixin_web_hook_url' => null,
        'not_code_restore_cert' => 'off',
        'exclusion_update' => null,
        'cdn' => '',
        'disable_theme' => null,
        'code_redeem_window_seconds' => '30',
        'ipa_appex' => 'on'
    ],
    'api' => [
        'url' => 'http://api-cer.dvffz8.cn',
        'email' => '',
        'token' => ''
    ],
    'redis' => [
        'host' => '127.0.0.1',
        'port' => '6379',
        'password' => ''
    ],
    'authorization' => [
        'control' => 'off',
        'injection' => 'off',
        'notice' => 'off',
        'time' => '5'
    ],
    'application' => [
        'executable' => null,
        'official_source' => null,
        'tutorial' => null,
        'group_chat' => null,
        'feedback' => null,
        'notice' => '测试公共内容\\r\\n用户定制二开源码\\r\\n修复所有已知的BUG\\r\\n经用户同意对外销售使用权',
        'udid_blacklist' => null,
        'source_blacklist' => null
    ],
    'default' => [
        'cache' => 'file',
        'files' => 'local',
        'queue' => 'database',
        'log' => 'daily',
        'mail' => 'smtp',
        'session' => 'file',
        'theme' => 'newUI',
        'locale' => 'zh_cn'
    ],
    'smtp' => [
        'host' => 'smtp.exmail.qq.com',
        'port' => '465',
        'encryption' => 'ssl',
        'username' => '',
        'password' => ''
    ],
    'mysql' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => '',
        'username' => '',
        'password' => ''
    ],
    'pgsql' => [
        'host' => '127.0.0.1',
        'port' => '5432',
        'database' => '',
        'username' => '',
        'password' => ''
    ],
    'sqlsrv' => [
        'host' => '127.0.0.1',
        'port' => '1433',
        'database' => '',
        'username' => '',
        'password' => ''
    ]
];
