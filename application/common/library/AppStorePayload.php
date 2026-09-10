<?php

namespace app\common\library;

/**
 * Pure AppStore payload mapper.
 *
 * Keeps protocol field names and legacy serialization semantics in one place,
 * so controllers only coordinate database / activation / encryption flows.
 */
class AppStorePayload
{
    const SITE_KEYS = [
        'name',
        'message',
        'identifier',
        'sourceURL',
        'sourceicon',
        'payURL',
        'unlockURL',
    ];

    /**
     * Resolve the response wrapper expected by legacy clients.
     */
    public static function appType($headerValue)
    {
        return $headerValue === 'v2' ? 'appstore_v2' : 'appstore';
    }

    /**
     * Preserve legacy config semantics: a missing field serializes as null.
     */
    public static function siteInfo(array $configRows)
    {
        $info = array_fill_keys(self::SITE_KEYS, null);
        foreach ($configRows as $row) {
            if (!isset($row['name']) || !array_key_exists($row['name'], $info)) {
                continue;
            }
            $info[$row['name']] = array_key_exists('value', $row) ? $row['value'] : null;
        }
        return $info;
    }

    /**
     * Map fa_category rows into the public software-source schema.
     *
     * $mode:
     * - licensed: only lock === '1' is treated as locked, and access depends on $allowLockedDownload.
     * - guest: preserve old PHP truthiness behavior for bt2b.
     */
    public static function apps(array $rows, $mode, $allowLockedDownload = false)
    {
        $data = [];
        foreach ($rows as $key => $row) {
            $type = isset($row['type']) && $row['type'] === 'default' ? 0 : (isset($row['type']) ? $row['type'] : null);
            $lock = isset($row['bt2b']) ? $row['bt2b'] : null;
            $download = isset($row['bt1a']) ? $row['bt1a'] : null;

            if ($mode === 'licensed') {
                if ($lock === '1' && !$allowLockedDownload) {
                    $download = '';
                }
            } else {
                // Legacy no-license branch used `$row['bt2b'] ? '' : $row['bt1a']`.
                if ($lock) {
                    $download = '';
                }
            }

            $data[$key] = [
                'name' => isset($row['name']) ? $row['name'] : null,
                'type' => $type,
                'version' => isset($row['nickname']) ? $row['nickname'] : null,
                'versionDate' => date('Y-m-d\TH:i:s\+08:00', isset($row['updatetime']) ? $row['updatetime'] : 0),
                'versionDescription' => str_replace('\\n', '@@@', isset($row['keywords']) ? $row['keywords'] : ''),
                'lock' => $lock,
                'downloadURL' => $download,
                'isLanZouCloud' => isset($row['flag']) ? $row['flag'] : null,
                'iconURL' => isset($row['image']) ? $row['image'] : null,
                'tintColor' => isset($row['bt1b']) ? $row['bt1b'] : null,
                'size' => isset($row['bt2a']) ? $row['bt2a'] : null,
            ];
        }
        return $data;
    }

    public static function source(array $info, $udid, $nowTime, array $apps)
    {
        return [
            'name' => array_key_exists('name', $info) ? $info['name'] : null,
            'message' => array_key_exists('message', $info) ? $info['message'] : null,
            'identifier' => array_key_exists('identifier', $info) ? $info['identifier'] : null,
            'sourceURL' => array_key_exists('sourceURL', $info) ? $info['sourceURL'] : null,
            'sourceicon' => array_key_exists('sourceicon', $info) ? $info['sourceicon'] : null,
            'payURL' => array_key_exists('payURL', $info) ? $info['payURL'] : null,
            'unlockURL' => array_key_exists('unlockURL', $info) ? $info['unlockURL'] : null,
            'UDID' => $udid,
            'Time' => $nowTime,
            'apps' => $apps,
        ];
    }

    public static function blacklisted($udid, $nowTime)
    {
        return [
            'name' => '已被源主拉黑',
            'message' => '你已被源主拉黑！',
            'identifier' => '长按此处删除软件源',
            'payURL' => '',
            'unlockURL' => '',
            'UDID' => $udid,
            'Time' => $nowTime,
            'apps' => [
                0 => [
                    'name' => '你已被源主拉黑！',
                    'version' => '1.0',
                    'type' => '1.0',
                    'versionDate' => '2021-01-24',
                    'versionDescription' => '你已被源主拉黑！',
                    'lock' => '1',
                    'downloadURL' => '',
                    'isLanZouCloud' => '0',
                    'tintColor' => '',
                    'size' => '123973140.48',
                ],
            ],
        ];
    }

    public static function withoutRuntimeFields(array $payload)
    {
        unset($payload['UDID'], $payload['Time']);
        return $payload;
    }

    /**
     * Legacy source JSON uses @@@ as an internal newline placeholder.
     */
    public static function encodeSourceJson(array $payload, $flags = 320)
    {
        return str_replace('@@@', '\\n', json_encode($payload, $flags));
    }
}
