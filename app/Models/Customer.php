<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    // 会員ステータスの条件（あとで変更しやすいよう定数にしてあります）
    public const DORMANT_DAYS = 180;
    public const REGULAR_VISITS = 3;

    protected $fillable = [
        'name', 'phone', 'email', 'email2', 'address', 'birthday',
        'karte_number', 'gender', 'memo', 'line_id', 'external_id', 'line_user_id', 'line_linked_at',
    ];

    protected $casts = [
        'birthday' => 'date',
        'line_linked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Customer $customer) {
            $customer->karte_number = $customer->birthday?->format('md');
        });
    }

    // 削除できない理由（予約・施術記録が残っていると、履歴が消えてしまうため削除しない）。なければ空
    public function deletionBlockers(): array
    {
        return array_filter([
            '予約' => $this->reservations()->count(),
            '対応記録' => $this->records()->count(),
        ]);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function aiSessions(): HasMany
    {
        return $this->hasMany(AiSession::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(Record::class);
    }

    // 来店済み = キャンセルでなく、日時が現在以前の予約
    public function visits(): HasMany
    {
        return $this->hasMany(Reservation::class)
            ->where('status', '!=', 'cancelled')
            ->where('scheduled_at', '<=', now())
            ->orderByDesc('scheduled_at');
    }

    public function getLastVisitAtAttribute(): ?Carbon
    {
        return $this->visits->first()?->scheduled_at;
    }

    public function getDaysSinceLastVisitAttribute(): ?int
    {
        $last = $this->last_visit_at;

        return $last ? (int) $last->copy()->startOfDay()->diffInDays(today()) : null;
    }

    public function getMemberStatusAttribute(): string
    {
        if ($this->visits->isEmpty()) {
            return '未来店';
        }
        if ($this->days_since_last_visit >= self::DORMANT_DAYS) {
            return '休眠';
        }
        if ($this->visits->where('scheduled_at', '>=', now()->subYear())->count() >= self::REGULAR_VISITS) {
            return '常連';
        }

        return '通常';
    }
}
