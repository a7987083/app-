<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class AppClass extends Model
{
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->setTimezone('Asia/Shanghai')->format($this->getDateFormat());
    }

    protected $table = 'app_class';

    protected $fillable = [
        'id',
        'name',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];
}
