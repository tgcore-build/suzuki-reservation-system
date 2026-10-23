<?php

namespace App\Console\Commands;

use App\Models\Record;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

// 以前 public ディスク（URLを知っていれば見られる場所）に保存した施術写真を、今の保存先（PHOTO_DISK）へ移す
class MovePhotosToPrivate extends Command
{
    protected $signature = 'photos:move-to-private {--dry-run : 移さずに、移す対象だけ表示する}';

    protected $description = 'public ディスクに残っている施術写真を、非公開の保存先（PHOTO_DISK）へ移す';

    public function handle(): int
    {
        $from = Storage::disk('public');
        $toName = config('salon.photo_disk');
        if ($toName === 'public') {
            $this->error('PHOTO_DISK が public のままです。local か s3 にしてから実行してください。');

            return self::FAILURE;
        }
        $to = Storage::disk($toName);

        $moved = 0;
        foreach (Record::all() as $record) {
            foreach (Record::PHOTO_FIELDS as $field) {
                $path = $record->{$field};
                if (! $path || $to->exists($path) || ! $from->exists($path)) {
                    continue;
                }
                $this->line("{$path}（記録#{$record->id}）");
                if ($this->option('dry-run')) {
                    continue;
                }

                // 写せたことを確かめてから、公開の場所のほうを消す
                $to->put($path, $from->get($path));
                if ($to->exists($path) && $to->size($path) === $from->size($path)) {
                    $from->delete($path);
                    $moved++;
                } else {
                    $this->error("移せませんでした: {$path}");
                }
            }
        }

        $this->info($this->option('dry-run') ? '（確認だけ。移していません）' : "移した写真: {$moved}件 → {$toName}");

        return self::SUCCESS;
    }
}
