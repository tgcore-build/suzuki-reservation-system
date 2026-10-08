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
        foreach ($reservations as $reservation) {
            $email = $reservation->customer?->email;
            if (! $email) {
                $this->line("スキップ（メール未登録）: 予約#{$reservation->id}");
                continue;
            }

            Mail::to($email)->send(new ReservationReminder($reservation));
            Reservation::whereKey($reservation->id)->update(['reminder_sent_at' => now()]);
            $sent++;
        }

        $this->info("送信: {$sent}件 / 対象日: {$date->format('Y-m-d')}");

        return self::SUCCESS;
    }
}
