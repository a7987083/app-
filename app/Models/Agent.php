<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use DateTimeInterface;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Agent extends Authenticatable
{
    use Notifiable;

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->setTimezone('Asia/Shanghai')->format($this->getDateFormat());
    }

    protected $table = 'agents';

    protected $fillable = [
        'name',
        'remark',
        'email',
        'password',
        'credit',
        'price',
        'status',
        'token',
        'api_secret',
        'good_price',
        'processing_price',
        'ipad_price',
        'ipad_good_price',
        'ipad_processing_price',
    ];

    protected $hidden = [
        'password',
        'api_secret',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];
}
