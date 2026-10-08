<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Record extends Model
{
    protected $fillable = [
        'customer_id', 'reservation_id', 'performed_on', 'menu', 'materials',
        'duration_minutes', 'memo', 'photo_before', 'photo_after',
    ];

    protected $casts = ['performed_on' => 'date'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
