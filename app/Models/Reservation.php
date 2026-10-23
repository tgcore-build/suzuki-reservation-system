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
        'customer_id', 'menu_id', 'duration_minutes', 'price', 'scheduled_at', 'source', 'notes', 'status',
        'booked_name', 'booked_email', 'needs_review',
        'concerns', 'concern_note', 'counseled_at',
        'reminder_sent_at', 'reminder_channel',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'duration_minutes' => 'integer',
        'price' => 'integer',
        'needs_review' => 'boolean',
        'concerns' => 'array',
        'counseled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // 予約を作るとき・メニューを変えたときに、そのときのメニューの所要時間と料金を予約に保存する
        // （あとでメニューの所要時間や料金を変えても、既存の予約の重なりや料金は変わらない）
        static::saving(function (Reservation $r) {
            $menuChanged = $r->isDirty('menu_id') && $r->exists;
            $needsFill = ! $r->exists || $menuChanged;
            if (! $needsFill || ! $r->menu_id) {
                return;
            }
            $menu = Menu::find($r->menu_id);
            if (! $r->isDirty('duration_minutes') || $r->duration_minutes === null) {
                $r->duration_minutes = $menu?->duration_minutes;
            }
            if (! $r->isDirty('price') || $r->price === null) {
                $r->price = $menu?->price;
            }
        });
    }

    // 終わる時刻（予約に保存した所要時間で計算する）
    public function endsAt(): \Illuminate\Support\Carbon
    {
        return $this->scheduled_at->copy()->addMinutes($this->minutes());
    }

    public function minutes(): int
    {
        return $this->duration_minutes ?? $this->menu?->duration_minutes ?? 60;
    }

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
