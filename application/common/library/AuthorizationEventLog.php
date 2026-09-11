<?php

namespace app\common\library;

use think\Db;

class AuthorizationEventLog
{
    public static function record($event, array $data = [])
    {
        AuthorizationSchema::ensure();
        $row = [
            'event' => (string)$event,
            'kami_id' => isset($data['kami_id']) ? (int)$data['kami_id'] : 0,
            'kami' => isset($data['kami']) ? (string)$data['kami'] : '',
            'udid' => isset($data['udid']) ? (string)$data['udid'] : '',
            'related_udid' => isset($data['related_udid']) ? (string)$data['related_udid'] : '',
            'duration' => isset($data['duration']) ? (int)$data['duration'] : 0,
            'endtime' => isset($data['endtime']) ? (int)$data['endtime'] : 0,
            'ip' => isset($data['ip']) ? (string)$data['ip'] : '',
            'detail' => isset($data['detail']) ? mb_substr((string)$data['detail'], 0, 255, 'UTF-8') : '',
            'addtime' => isset($data['addtime']) ? (int)$data['addtime'] : time(),
        ];
        return Db::table('fa_authorization_event')->insert($row) === 1;
    }
}
