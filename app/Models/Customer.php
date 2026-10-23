<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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
        // カルテ番号：生年月日の月日4桁のあとに、同じ月日の中での連番を付ける（例：0202-1、0202-2）
        // 一度付けた番号は変えない（ほかの顧客を削除しても詰めない。年だけ直したときもそのまま）
        static::saving(function (Customer $customer) {
            $md = $customer->birthday?->format('md');
            if (! $md || ! str_starts_with((string) $customer->karte_number, "{$md}-")) {
                $customer->karte_number = null; // 月日が変わった・生年月日を消した → 付け直す
            }
        });

        // 番号は保存したあとに付ける。月日ごとの「最後に付けた番号」を、ロックしてから1つ進める
        // （ほぼ同時に同じ月日の人が登録されても、番号がぶつからない。削除した人の番号も使い回さない）
        static::saved(function (Customer $customer) {
            if ($customer->karte_number || ! $customer->birthday) {
                return;
            }
            $md = $customer->birthday->format('md');

            DB::transaction(function () use ($customer, $md) {
                DB::table('karte_sequences')->insertOrIgnore(['month_day' => $md, 'last_number' => 0]);
                $next = DB::table('karte_sequences')->where('month_day', $md)->lockForUpdate()->value('last_number') + 1;
                DB::table('karte_sequences')->where('month_day', $md)->update(['last_number' => $next]);

                $customer->karte_number = sprintf('%s-%d', $md, $next);
                $customer->saveQuietly();
            });
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
