<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Reservation;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $tomorrow = today()->addDay();

        $todayReservations = Reservation::with(['customer', 'menu'])
            ->where('status', '!=', 'cancelled')
            ->whereBetween('scheduled_at', [today()->startOfDay(), today()->endOfDay()])
            ->orderBy('scheduled_at')
            ->get();

        // 明日の予約（リマインドメールの送信対象）
        $tomorrowReservations = Reservation::with(['customer', 'menu'])
            ->where('status', '!=', 'cancelled')
            ->whereBetween('scheduled_at', [$tomorrow->copy()->startOfDay(), $tomorrow->copy()->endOfDay()])
            ->orderBy('scheduled_at')
            ->get();

        // 明日から7日間の予約
        $weekCount = Reservation::where('status', '!=', 'cancelled')
            ->whereBetween('scheduled_at', [$tomorrow->copy()->startOfDay(), today()->addDays(7)->endOfDay()])
            ->count();

        // 最近送ったリマインド
        $recentReminders = Reservation::with(['customer', 'menu'])
            ->whereNotNull('reminder_sent_at')
            ->orderByDesc('reminder_sent_at')
            ->limit(5)
            ->get();

        return view('dashboard', [
            'tomorrowCount' => $tomorrowReservations->count(),
            'weekCount' => $weekCount,
            'customerCount' => Customer::count(),
            'todayReservations' => $todayReservations,
            'tomorrowReservations' => $tomorrowReservations,
            'recentReminders' => $recentReminders,
        ]);
    }
}
