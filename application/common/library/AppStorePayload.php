<?php

namespace app\common\library;

/**
 * Pure AppStore payload mapper.
 *
 * Public protocol keys stay here, while SourceAppRecord translates the legacy
 * physical fa_category columns into semantic names.
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

    public static function appType($headerValue)
    {
        return $headerValue === 'v2' ? 'appstore_v2' : 'appstore';
    }

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

    public static function apps(array $rows, $mode, $allowLockedDownload = false)
    {
        $data = [];
        foreach ($rows as $key => $row) {
            $rawType = SourceAppRecord::value($row, 'type');
            $type = $rawType === 'default' ? 0 : $rawType;
            $lock = SourceAppRecord::value($row, 'paid');
            $download = SourceAppRecord::value($row, 'download_url');

            if ($mode === 'licensed') {
                if ($lock === '1' && !$allowLockedDownload) {
                    $download = '';
                }
            } else {
                // Preserve legacy guest truthiness for the paid flag.
                if ($lock) {
                    $download = '';
                }
            }

            $data[$key] = [
                'name' => SourceAppRecord::value($row, 'name'),
                'type' => $type,
                'version' => SourceAppRecord::value($row, 'version'),
                'versionDate' => date('Y-m-d\TH:i:s\+08:00', (int)SourceAppRecord::value($row, 'updated_at', 0)),
                'versionDescription' => str_replace('\\n', '@@@', (string)SourceAppRecord::value($row, 'description', '')),
                'lock' => $lock,
                'downloadURL' => $download,
                'isLanZouCloud' => SourceAppRecord::value($row, 'cloud_flag'),
                'iconURL' => SourceAppRecord::value($row, 'icon_url'),
                'tintColor' => SourceAppRecord::value($row, 'button_color'),
                'size' => SourceAppRecord::value($row, 'file_size'),
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

    public static function encodeSourceJson(array $payload, $flags = 320)
    {
        return str_replace('@@@', '\\n', json_encode($payload, $flags));
    }
}
