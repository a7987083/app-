<?php

namespace app\common\library;

/**
 * Provider selection policy for encrypted software-source responses.
 *
 * New releases prefer the local compatibility provider for both appstore and
 * appstore_v2. Nuosike remains as an emergency fallback and rollback path.
 *
 * Supported modes:
 *   nuosike        - always use the legacy external service
 *   local          - use the local DES-compatible provider; fail closed
 *   local_fallback - prefer local, fall back to Nuosike on local failure
 */
class SourceEncryptionPolicy
{
    const MODE_NUOSIKE = 'nuosike';
    const MODE_LOCAL = 'local';
    const MODE_LOCAL_FALLBACK = 'local_fallback';

    public static function mode()
    {
        $value = getenv('SOURCE_ENCRYPTION_PROVIDER');
        $mode = strtolower(trim($value === false ? '' : (string)$value));
        if ($mode === '') {
            return self::MODE_LOCAL_FALLBACK;
        }
        if (!in_array($mode, [self::MODE_NUOSIKE, self::MODE_LOCAL, self::MODE_LOCAL_FALLBACK], true)) {
            return self::MODE_LOCAL_FALLBACK;
        }
        return $mode;
    }

    public static function localRequested()
    {
        return in_array(self::mode(), [self::MODE_LOCAL, self::MODE_LOCAL_FALLBACK], true);
    }

    public static function fallbackAllowed()
    {
        return self::mode() === self::MODE_LOCAL_FALLBACK;
    }

    public static function localAllowedForAppType($appType)
    {
        if ($appType !== 'appstore_v2') {
            return true;
        }
        return self::envBool('SOURCE_ENCRYPTION_LOCAL_V2', true);
    }

    public static function nuosikeUrl($appType)
    {
        return $appType === 'appstore_v2'
            ? 'https://api.nuosike.com/encrypt.php'
            : 'https://api.nuosike.com/api.php';
    }

    public static function envBool($name, $default)
    {
        $value = getenv($name);
        if ($value === false || trim((string)$value) === '') {
            return (bool)$default;
        }
        return !in_array(strtolower(trim((string)$value)), ['0', 'false', 'off', 'no'], true);
    }
}
