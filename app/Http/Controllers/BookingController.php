<?php

namespace App\Http\Controllers;

use App\Mail\ReservationConfirmed;
use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingController extends Controller
{
    private const STEP = 15;          // 時間の刻み（分）
    private const LEAD_MINUTES = 60;  // 何分前まで予約できるか
    private const MAX_DAYS = 60;      // 何日先まで予約できるか

    public function index(Request $request): View
    {
        $menus = Menu::where('is_active', true)->orderBy('sort_order')->get();
        $menu = $menus->firstWhere('id', (int) $request->query('menu_id'));
        $date = $this->parseDate($request->query('date'));
        $slots = ($menu && $date) ? $this->availableSlots($date, $menu) : null;

        return view('booking.index', compact('menus', 'menu', 'date', 'slots'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'menu_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^[0-9\-]{10,13}$/'],
            'email' => ['required', 'email', 'max:255'],
        ], [], [
            'menu_id' => 'メニュー',
            'date' => '日付',
            'time' => '時間',
            'name' => 'お名前',
            'phone' => '電話番号',
            'email' => 'メールアドレス',
        ]);

        $menu = Menu::where('is_active', true)->find($data['menu_id']);
        $date = $this->parseDate($data['date']);

        if (! $menu || ! $date || ! in_array($data['time'], $this->availableSlots($date, $menu), true)) {
            throw ValidationException::withMessages([
                'time' => '選択された時間は予約できません。別の時間をお選びください。',
            ]);
        }

        $digits = preg_replace('/\D/', '', $data['phone']);
        $customer = Customer::whereRaw("REPLACE(phone, '-', '') = ?", [$digits])->first();
        if (! $customer) {
            $customer = Customer::create([
                'name' => $data['name'],
                'phone' => $digits,
                'email' => $data['email'],
            ]);
        } elseif (! $customer->email) {
            $customer->update(['email' => $data['email']]);
        }

        $reservation = Reservation::create([
            'customer_id' => $customer->id,
            'menu_id' => $menu->id,
            'scheduled_at' => $date->copy()->setTimeFromTimeString($data['time']),
            'source' => 'online',
            'status' => 'confirmed',
        ]);

        try {
            Mail::to($data['email'])->send(new ReservationConfirmed($reservation->load('customer', 'menu')));
        } catch (\Throwable $e) {
            report($e); // メール送信に失敗しても予約は成立させる
        }

        return redirect()->route('booking.complete')->with('booked', [
            'at' => $reservation->scheduled_at->format('Y年n月j日 H:i'),
            'menu' => $menu->name,
        ]);
    }

    public function complete(): View|RedirectResponse
    {
        if (! session('booked')) {
            return redirect()->route('booking.index');
        }

        return view('booking.complete', ['booked' => session('booked')]);
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }
        try {
            $date = Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
        if ($date->lt(today()) || $date->gt(today()->addDays(self::MAX_DAYS))) {
            return null;
        }

        return $date;
    }

    // 営業時間・既存の予約・現在時刻から、予約できる開始時刻（H:i）の一覧を返す
    private function availableSlots(Carbon $date, Menu $menu): array
    {
        $hour = BusinessHour::where('weekday', $date->dayOfWeek)->first();
        if (! $hour || ! $hour->is_open) {
            return [];
        }

        $open = $date->copy()->setTimeFromTimeString($hour->open_time);
        $close = $date->copy()->setTimeFromTimeString($hour->close_time);
        $earliest = now()->addMinutes(self::LEAD_MINUTES);

        $existing = Reservation::with('menu')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('scheduled_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->get();

        $slots = [];
        for ($t = $open->copy(); $t->copy()->addMinutes($menu->duration_minutes)->lte($close); $t->addMinutes(self::STEP)) {
            if ($t->lt($earliest)) {
                continue;
            }
            $end = $t->copy()->addMinutes($menu->duration_minutes);
            $busy = $existing->contains(function (Reservation $r) use ($t, $end) {
                $rEnd = $r->scheduled_at->copy()->addMinutes($r->menu?->duration_minutes ?? 60);

                return $t->lt($rEnd) && $end->gt($r->scheduled_at);
            });
            if (! $busy) {
                $slots[] = $t->format('H:i');
            }
        }

        return $slots;
    }
}
