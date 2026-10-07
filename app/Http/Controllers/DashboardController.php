<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Reservation;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = today();

        $todayReservations = Reservation::with(['customer', 'menu'])
            ->whereDate('scheduled_at', $today)
            ->where('status', '!=', 'cancelled')
            ->orderBy('scheduled_at')
            ->get();

        $tomorrowCount = Reservation::whereDate('scheduled_at', $today->copy()->addDay())
            ->where('status', '!=', 'cancelled')
            ->count();

        $weekCount = Reservation::whereBetween('scheduled_at', [$today->copy()->addDay()->startOfDay(), $today->copy()->addDays(7)->endOfDay()])
            ->where('status', '!=', 'cancelled')
            ->count();

        $customerCount = Customer::count();

        return view('dashboard', compact('todayReservations', 'tomorrowCount', 'weekCount', 'customerCount'));
    }
}
