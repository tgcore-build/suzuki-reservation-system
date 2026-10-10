<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Record extends Model
{
    protected $fillable = [
        'customer_id', 'reservation_id', 'performed_on', 'menu', 'materials',
        'duration_minutes', 'memo', 'photo_before', 'photo_after',
    ];

    protected $casts = [
        'performed_on' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    // 写真のURL。S3のときは30分だけ有効な一時URLにする（写真を公開しないため）
    public function photoUrl(string $field): ?string
    {
        $path = $this->{$field};
        if (! $path) {
            return null;
        }

        $diskName = config('salon.photo_disk');
        $disk = Storage::disk($diskName);

        return config("filesystems.disks.{$diskName}.driver") === 's3'
            ? $disk->temporaryUrl($path, now()->addMinutes(30))
            : $disk->url($path);
    }
}
