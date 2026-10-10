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
    private const STEP = 30;          // 予約できる時間の刻み（分）
    private const LEAD_MINUTES = 60;  // 何分前まで予約できるか
    private const MAX_DAYS = 60;      // 何日先まで予約できるか

    // 空き状況の表（1週間 / 2週間）
    public function index(Request $request): View
    {
        $menus = Menu::where('is_active', true)->orderBy('sort_order')->get();
        $menu = $menus->firstWhere('id', (int) $request->query('menu_id'));
        $weeks = (int) $request->query('weeks') === 2 ? 2 : 1;
        $start = $this->parseStart($request->query('start'));

        $weekGrids = [];
        $slotMap = [];
        $closed = [];
        $times = [];

        if ($menu) {
            $hours = BusinessHour::all()->keyBy('weekday');

            for ($w = 0; $w < $weeks; $w++) {
                $days = [];
                for ($d = 0; $d < 7; $d++) {
                    $day = $start->copy()->addDays($w * 7 + $d);
                    $key = $day->format('Y-m-d');
                    $days[] = $day;
                    $slotMap[$key] = $this->availableSlots($day, $menu);
                    $closed[$key] = isset($hours[$day->dayOfWeek]) && ! $hours[$day->dayOfWeek]->is_open;
                }
                $weekGrids[] = $days;
            }

            $times = collect($slotMap)->flatten()->unique()->sort()->values()->all();
        }

        $thisWeek = today()->startOfWeek(Carbon::MONDAY);
        $prev = $start->copy()->subDays($weeks * 7);
        $next = $start->copy()->addDays($weeks * 7);

        return view('booking.index', [
            'menus' => $menus,
            'menu' => $menu,
            'weeks' => $weeks,
            'start' => $start,
            'weekGrids' => $weekGrids,
            'slotMap' => $slotMap,
            'closed' => $closed,
            'times' => $times,
            'prevStart' => $prev->lt($thisWeek) ? null : $prev->format('Y-m-d'),
            'nextStart' => $next->gt(today()->addDays(self::MAX_DAYS)) ? null : $next->format('Y-m-d'),
            'thisWeek' => $thisWeek->format('Y-m-d'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // 表で選んだ「日付|時間」を、日付と時間に分ける
        if ($request->filled('slot')) {
            [$slotDate, $slotTime] = array_pad(explode('|', (string) $request->input('slot'), 2), 2, null);
            $request->merge(['date' => $slotDate, 'time' => $slotTime]);
        }

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

    // 指定日を含む週の月曜日。今週より前や不正な値は、今週の月曜日にする
    private function parseStart(?string $value): Carbon
    {
        $thisWeek = today()->startOfWeek(Carbon::MONDAY);
        if (! $value) {
            return $thisWeek;
        }
        try {
            $monday = Carbon::createFromFormat('Y-m-d', $value)->startOfDay()->startOfWeek(Carbon::MONDAY);
        } catch (\Throwable $e) {
            return $thisWeek;
        }

        return $monday->lt($thisWeek) ? $thisWeek : $monday;
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
        if ($date->lt(today()) || $date->gt(today()->addDays(self::MAX_DAYS))) {
            return [];
        }

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
