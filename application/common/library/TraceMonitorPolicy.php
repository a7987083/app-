<?php

namespace app\common\library;

/**
 * Legacy source-trace payload semantics used by /appstore.
 *
 * The client payload is base64("添加者UDID|破解者UDID").
 * This class intentionally preserves the historical first/second-position
 * meaning and 25/40-character UDID acceptance rules.
 */
class TraceMonitorPolicy
{
    const IDENTITY_ADDER = '添加者';
    const IDENTITY_CRACKER = '破解者';

    /**
     * Decode the legacy trace payload into the two historical identities.
     *
     * @param mixed $traceValue
     * @return array
     */
    public static function entries($traceValue)
    {
        if ($traceValue === null || $traceValue === '') {
            return [];
        }

        $decoded = base64_decode($traceValue);
        $parts = explode('|', $decoded);

        return [
            [
                'udid' => isset($parts[0]) ? $parts[0] : null,
                'identity' => self::IDENTITY_ADDER,
                'config' => 'openblack',
            ],
            [
                'udid' => isset($parts[1]) ? $parts[1] : null,
                'identity' => self::IDENTITY_CRACKER,
                'config' => 'openblack2',
            ],
        ];
    }

    /**
     * Preserve the original server-side UDID gate exactly.
     */
    public static function isSupportedUdid($udid)
    {
        if (!is_string($udid) || $udid === '') {
            return false;
        }

        $length = strlen($udid);
        return $length === 25 || $length === 40;
    }
}
