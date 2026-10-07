<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    public const STATUSES = [
        'confirmed' => '予約済み',
        'completed' => '完了',
        'cancelled' => 'キャンセル',
    ];

    protected $fillable = ['customer_id', 'menu_id', 'scheduled_at', 'source', 'notes', 'status'];

    protected $casts = ['scheduled_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}
