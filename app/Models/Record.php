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

    public const PHOTO_FIELDS = ['photo_before', 'photo_after'];

    // 写真を表示するURL。写真は非公開の場所に置き、URLを知っているだけでは見られないようにする
    //  本番（s3）：30分だけ有効な一時URL
    //  それ以外：ログインした管理者（admin）／確認コードまで済ませた本人のお客様（customer）だけが通れるURL
    public function photoUrl(string $field, string $for = 'admin'): ?string
    {
        if (! in_array($field, self::PHOTO_FIELDS, true) || ! $this->{$field}) {
            return null;
        }

        $diskName = config('salon.photo_disk');
        if (config("filesystems.disks.{$diskName}.driver") === 's3') {
            return Storage::disk($diskName)->temporaryUrl($this->{$field}, now()->addMinutes(30));
        }

        return route($for === 'customer' ? 'my.records.photo' : 'records.photo', ['record' => $this, 'field' => $field]);
    }
}
