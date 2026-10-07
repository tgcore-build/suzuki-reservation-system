<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'birthday',
        'karte_number',
        'gender',
        'memo',
        'line_id',
        'external_id',
    ];

    protected $casts = [
        'birthday' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (Customer $customer) {
            // 誕生日の月日4桁(2月2日→0202)をカルテ番号にする
            $customer->karte_number = $customer->birthday?->format('md');
        });
    }
}
