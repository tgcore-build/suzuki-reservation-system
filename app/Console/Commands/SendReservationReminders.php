<?php

namespace App\Console\Commands;

use App\Mail\ReservationReminder;
use App\Models\Reservation;
use App\Services\Line\LineClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendReservationReminders extends Command
{
    protected $signature = 'reminders:send {--date= : 対象日（省略時は明日）}';

    protected $description = '対象日の予約にリマインドを送る（LINE 連携済みなら LINE、それ以外はメール）';

    public function handle(LineClient $line): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : today()->addDay();

        $reservations = Reservation::with(['customer', 'menu'])
            ->where('status', 'confirmed')
            ->whereNull('reminder_sent_at')
            ->whereDate('scheduled_at', $date)
            ->get();

        $sent = ['line' => 0, 'email' => 0];
        $failed = 0;
        foreach ($reservations as $reservation) {
            // 1件失敗しても、残りの人には送る（失敗した予約は送信済みにしないので、再実行で送り直せる）
            $channel = $this->sendLine($line, $reservation) ?? $this->sendEmail($reservation);

            if ($channel === 'skip') {
                $this->line("スキップ（LINE 未連携・メール未登録）: 予約#{$reservation->id}");
                continue;
            }
            if ($channel === null) {
                $this->error("送信失敗: 予約#{$reservation->id}");
                $failed++;
                continue;
            }

            Reservation::whereKey($reservation->id)->update(['reminder_sent_at' => now(), 'reminder_channel' => $channel]);
            $sent[$channel]++;
        }

        $this->info("送信: LINE {$sent['line']}件・メール {$sent['email']}件 / 失敗: {$failed}件 / 対象日: {$date->format('Y-m-d')}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    // LINE 連携済みなら LINE で送る。送れたら 'line'、送らなかった・送れなかったら null（メールで送る）
    private function sendLine(LineClient $line, Reservation $reservation): ?string
    {
        $userId = $reservation->customer?->line_user_id;
        // 以前の「要確認」の予約は、登録した本人の予約か分からないので LINE には送らない
        if (! $userId || ! $line->messagingEnabled() || $reservation->needs_review) {
            return null;
        }

        try {
            $line->push($userId, $this->lineText($reservation));

            return 'line';
        } catch (\Throwable $e) {
            report($e); // 友だち追加されていない・ブロックされているなど。メールで送り直す
            $this->warn("LINE 送信失敗、メールで送ります: 予約#{$reservation->id}");

            return null;
        }
    }

    // メールで送る。送れたら 'email'、アドレスがなければ 'skip'、失敗したら null
    private function sendEmail(Reservation $reservation): ?string
    {
        // オンライン予約は、その予約で入力されたアドレスに送る（確認メールと同じ宛先）
        $email = $reservation->booked_email ?: $reservation->customer?->email;
        if (! $email) {
            return 'skip';
        }

        try {
            Mail::to($email)->send(new ReservationReminder($reservation));

            return 'email';
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private function lineText(Reservation $reservation): string
    {
        $name = $reservation->booked_name ?? $reservation->customer?->name;

        return implode("\n", [
            '【' . config('app.name') . '】ご予約のリマインド',
            '',
            "{$name} 様",
            'ご予約のお知らせです。',
            '',
            '日時：' . $reservation->scheduled_at->format('Y年n月j日 H:i'),
            'メニュー：' . ($reservation->menu?->name ?? '—'),
            '',
            'ご都合が悪くなった場合は、お早めにご連絡ください。',
            'ご来店をお待ちしております。',
        ]);
    }
}
