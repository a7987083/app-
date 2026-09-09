<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class App extends Model
{
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->setTimezone('Asia/Shanghai')->format($this->getDateFormat());
    }

    protected $table = 'app';

    protected $fillable = [
        'id',
        'app_id',
        'app_bid',
        'app_name',
        'app_version',
        'app_introduction',
        'notice',
        'class_id',
        'notice',
        'injection_framework',
        'status'
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];
}
