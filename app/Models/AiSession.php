<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiSession extends Model
{
    protected $fillable = ['customer_id', 'result_menu', 'result_menu_id', 'status'];

    public const STATUSES = [
        'in_progress' => '相談中',
        'completed' => '完了',
    ];

    // 顧客への緩い参照（AI相談のあとに予約した場合だけ入る）
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function resultMenu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'result_menu_id');
    }

    // 画面に出すメニュー名（今のメニュー名。メニューが削除されていたら、提案した時点の名前）
    public function resultMenuName(): ?string
    {
        return $this->resultMenu?->name ?? $this->result_menu;
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'session_id')->orderBy('id');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
