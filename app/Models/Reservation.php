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

    protected $fillable = [
        'customer_id', 'menu_id', 'scheduled_at', 'source', 'notes', 'status',
        'booked_name', 'booked_email', 'needs_review',
        'concerns', 'concern_note', 'counseled_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'needs_review' => 'boolean',
        'concerns' => 'array',
        'counseled_at' => 'datetime',
    ];

    // 事前カウンセリングでチェックしたお悩みの項目名
    public function concernLabels(): array
    {
        return collect($this->concerns ?? [])
            ->map(fn (string $key) => config("salon.concerns.{$key}"))
            ->filter()
            ->values()
            ->all();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}
