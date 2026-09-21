<?php

use think\Env;

return [
    // Required in production. Use a random secret of at least 32 bytes.
    'server_secret' => Env::get('ipa.server_secret', ''),
    'verify_timestamp_skew' => (int)Env::get('ipa.verify_timestamp_skew', 300),
    'session_ttl' => (int)Env::get('ipa.session_ttl', 900),
    'default_offline_grace' => (int)Env::get('ipa.offline_grace', 900),
    'verify_log_retention_days' => (int)Env::get('ipa.verify_log_retention_days', 30),
];
