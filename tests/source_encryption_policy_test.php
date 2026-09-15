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
sourcePolicyAssertSame('local_fallback', SourceEncryptionPolicy::mode(), 'default mode must prefer local with Nuosike fallback');
sourcePolicyAssertSame(true, SourceEncryptionPolicy::localRequested(), 'default must request local encryption');
sourcePolicyAssertSame(true, SourceEncryptionPolicy::fallbackAllowed(), 'default must retain Nuosike fallback');
sourcePolicyAssertSame(true, SourceEncryptionPolicy::localAllowedForAppType('appstore'), 'legacy appstore must allow local provider');
sourcePolicyAssertSame(true, SourceEncryptionPolicy::localAllowedForAppType('appstore_v2'), 'appstore_v2 must allow local provider by default');

putenv('SOURCE_ENCRYPTION_PROVIDER=local');
sourcePolicyAssertSame('local', SourceEncryptionPolicy::mode(), 'local mode not selected');
sourcePolicyAssertSame(true, SourceEncryptionPolicy::localRequested(), 'local mode must request local encryption');
sourcePolicyAssertSame(false, SourceEncryptionPolicy::fallbackAllowed(), 'local mode must fail closed');

putenv('SOURCE_ENCRYPTION_PROVIDER=nuosike');
sourcePolicyAssertSame('nuosike', SourceEncryptionPolicy::mode(), 'Nuosike rollback mode not selected');
sourcePolicyAssertSame(false, SourceEncryptionPolicy::localRequested(), 'Nuosike rollback must bypass local encryption');

putenv('SOURCE_ENCRYPTION_PROVIDER=local_fallback');
sourcePolicyAssertSame(true, SourceEncryptionPolicy::localRequested(), 'fallback mode must prefer local');
sourcePolicyAssertSame(true, SourceEncryptionPolicy::fallbackAllowed(), 'fallback mode must allow Nuosike fallback');

putenv('SOURCE_ENCRYPTION_LOCAL_V2=0');
sourcePolicyAssertSame(false, SourceEncryptionPolicy::localAllowedForAppType('appstore_v2'), 'explicit v2 rollback must be honored');
putenv('SOURCE_ENCRYPTION_LOCAL_V2=1');
sourcePolicyAssertSame(true, SourceEncryptionPolicy::localAllowedForAppType('appstore_v2'), 'explicit v2 enable must be honored');
sourcePolicyAssertSame('https://api.nuosike.com/api.php', SourceEncryptionPolicy::nuosikeUrl('appstore'), 'legacy Nuosike URL changed');
sourcePolicyAssertSame('https://api.nuosike.com/encrypt.php', SourceEncryptionPolicy::nuosikeUrl('appstore_v2'), 'v2 Nuosike URL changed');

putenv('SOURCE_ENCRYPTION_PROVIDER=invalid');
sourcePolicyAssertSame('local_fallback', SourceEncryptionPolicy::mode(), 'invalid mode must fall back to safe local_fallback default');

putenv('SOURCE_ENCRYPTION_PROVIDER');
putenv('SOURCE_ENCRYPTION_LOCAL_V2');

echo "source_encryption_policy_test: PASS\n";
