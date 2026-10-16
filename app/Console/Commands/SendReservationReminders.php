<?php

namespace App\Console\Commands;

use App\Mail\ReservationReminder;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendReservationReminders extends Command
{
    protected $signature = 'reminders:send {--date= : 対象日（省略時は明日）}';

    protected $description = '対象日の予約にリマインドメールを送る';

    public function handle(): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : today()->addDay();

        $reservations = Reservation::with(['customer', 'menu'])
            ->where('status', 'confirmed')
            ->whereNull('reminder_sent_at')
            ->whereDate('scheduled_at', $date)
            ->get();

        $sent = 0;
        $failed = 0;
        foreach ($reservations as $reservation) {
            // オンライン予約は、その予約で入力されたアドレスに送る（確認メールと同じ宛先）
            $email = $reservation->booked_email ?: $reservation->customer?->email;
            if (! $email) {
                $this->line("スキップ（メール未登録）: 予約#{$reservation->id}");
                continue;
            }

            // 1件失敗しても、残りの人には送る（失敗した予約は送信済みにしないので、再実行で送り直せる）
            try {
                Mail::to($email)->send(new ReservationReminder($reservation));
            } catch (\Throwable $e) {
                report($e);
                $this->error("送信失敗: 予約#{$reservation->id}");
                $failed++;
                continue;
            }

            Reservation::whereKey($reservation->id)->update(['reminder_sent_at' => now()]);
            $sent++;
        }

        $this->info("送信: {$sent}件 / 失敗: {$failed}件 / 対象日: {$date->format('Y-m-d')}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
