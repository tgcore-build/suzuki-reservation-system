<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiSession extends Model
{
    protected $fillable = ['customer_id', 'result_menu', 'status'];

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'session_id')->orderBy('id');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
