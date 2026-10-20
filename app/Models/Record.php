<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Record extends Model
{
    protected $fillable = [
        'customer_id', 'reservation_id', 'performed_on', 'menu', 'concerns', 'concern_note', 'materials',
        'duration_minutes', 'memo', 'photo_before', 'photo_after',
    ];

    protected $casts = [
        'performed_on' => 'date',
        'concerns' => 'array',
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
    // チェックしたお悩みの項目名（設定から外した項目は表示しない）
    public function concernLabels(): array
    {
        return collect($this->concerns ?? [])
            ->map(fn (string $key) => config("salon.concerns.{$key}"))
            ->filter()
            ->values()
            ->all();
    }

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
