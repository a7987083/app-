<?php

require_once __DIR__ . '/../application/common/library/SourceEncryptionPolicy.php';

use app\common\library\SourceEncryptionPolicy;

function sourcePolicyAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) . "\nActual:   " . var_export($actual, true) . "\n");
        exit(1);
    }
}

putenv('SOURCE_ENCRYPTION_PROVIDER');
putenv('SOURCE_ENCRYPTION_LOCAL_V2');
sourcePolicyAssertSame('nuosike', SourceEncryptionPolicy::mode(), 'default mode must stay Nuosike');
sourcePolicyAssertSame(false, SourceEncryptionPolicy::localRequested(), 'default must not request local encryption');
sourcePolicyAssertSame(true, SourceEncryptionPolicy::localAllowedForAppType('appstore'), 'legacy appstore may use verified local provider');
sourcePolicyAssertSame(false, SourceEncryptionPolicy::localAllowedForAppType('appstore_v2'), 'appstore_v2 must stay remote until explicitly verified');

putenv('SOURCE_ENCRYPTION_PROVIDER=local');
sourcePolicyAssertSame('local', SourceEncryptionPolicy::mode(), 'local mode not selected');
sourcePolicyAssertSame(true, SourceEncryptionPolicy::localRequested(), 'local mode must request local encryption');
sourcePolicyAssertSame(false, SourceEncryptionPolicy::fallbackAllowed(), 'local mode must fail closed');

putenv('SOURCE_ENCRYPTION_PROVIDER=local_fallback');
sourcePolicyAssertSame(true, SourceEncryptionPolicy::localRequested(), 'fallback mode must prefer local');
sourcePolicyAssertSame(true, SourceEncryptionPolicy::fallbackAllowed(), 'fallback mode must allow Nuosike fallback');

putenv('SOURCE_ENCRYPTION_LOCAL_V2=1');
sourcePolicyAssertSame(true, SourceEncryptionPolicy::localAllowedForAppType('appstore_v2'), 'explicit v2 opt-in must be honored');
sourcePolicyAssertSame('https://api.nuosike.com/api.php', SourceEncryptionPolicy::nuosikeUrl('appstore'), 'legacy Nuosike URL changed');
sourcePolicyAssertSame('https://api.nuosike.com/encrypt.php', SourceEncryptionPolicy::nuosikeUrl('appstore_v2'), 'v2 Nuosike URL changed');

putenv('SOURCE_ENCRYPTION_PROVIDER=invalid');
sourcePolicyAssertSame('nuosike', SourceEncryptionPolicy::mode(), 'invalid mode must fail safe to Nuosike');

putenv('SOURCE_ENCRYPTION_PROVIDER');
putenv('SOURCE_ENCRYPTION_LOCAL_V2');

echo "source_encryption_policy_test: PASS\n";
