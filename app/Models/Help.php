<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class Help extends Model
{
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->setTimezone('Asia/Shanghai')->format($this->getDateFormat());
    }

    protected $table = 'help';

    protected $fillable = [
        'id',
        'title',
        'content',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];
}
