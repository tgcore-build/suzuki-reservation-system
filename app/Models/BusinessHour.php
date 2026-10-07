<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessHour extends Model
{
    public const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

    protected $fillable = ['weekday', 'is_open', 'open_time', 'close_time'];

    protected $casts = ['is_open' => 'boolean'];
}
