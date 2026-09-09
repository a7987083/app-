<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class Code extends Model
{
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->setTimezone('Asia/Shanghai')->format($this->getDateFormat());
    }

    protected $table = 'code';

    protected $fillable = [
        'id',
        'code',
        'udid',
        'remark',
        'status',
        'after_sale_day',
        'use_after_sale',
        'after_sale_num',
        'verified_at',
        'maturity_at',
        'agent_id',
        'product',
        'transition_type',
        'transition_create',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'verified_at' => 'datetime:Y-m-d H:i:s',
        'maturity_at' => 'datetime:Y-m-d H:i:s',
    ];
}
