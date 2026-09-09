<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SignLog extends Model
{
    use HasUuids;

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->setTimezone('Asia/Shanghai')->format($this->getDateFormat());
    }

    protected $table = 'sign_log';

    protected $fillable = [
        'id',
        'app_id',
        'app_bid',
        'app_name',
        'app_version',
        'multiple_num',
        'multiple_init',
        'udid',
        'cert_id',
        'progressing',
        'queue_id',
        'connection_name',
        'attempts',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];
}
