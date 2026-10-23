<?php

namespace App\Http\Controllers;

use App\Mail\ReservationConfirmed;
use App\Models\AiSession;
use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\Reservation;
use App\Services\CustomerLogin;
use App\Support\BirthdayInput;
use App\Support\PhoneNumber;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingController extends Controller
{
    private const STEP = 30;          // 予約できる時間の刻み（分）
    private const LEAD_MINUTES = 60;  // 何分前まで予約できるか
    private const MAX_DAYS = 60;      // 何日先まで予約できるか

    // 空き状況の表（1週間 / 2週間）
    public function index(Request $request, CustomerLogin $login): View
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
            'loggedIn' => $loggedIn = $login->customer($request),
            'maskedEmail' => CustomerLogin::maskEmail($loggedIn?->email),
        ]);
    }

    public function store(Request $request, CustomerLogin $login): RedirectResponse
    {
        // 表で選んだ「日付|時間」を、日付と時間に分ける
        if ($request->filled('slot')) {
            [$slotDate, $slotTime] = array_pad(explode('|', (string) $request->input('slot'), 2), 2, null);
            $request->merge(['date' => $slotDate, 'time' => $slotTime]);
        }

        BirthdayInput::prepare($request); // 「1990/1/1」なども受け付ける

        // 登録済みのお客様は、かんたんログインしてから予約する。ログインしていない人は、はじめてのお客様として登録する
        // かんたんログイン中は、お名前・電話番号の入力を省く（メールは、登録がない場合だけ入力してもらう）
        $loggedIn = $login->customer($request);
        $customerRules = $loggedIn ? [
            'email' => [$loggedIn->email ? 'nullable' : 'required', 'email', 'max:255'],
        ] : [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20', PhoneNumber::rule()], // 「----------」などを通さない
            'email' => ['required', 'email', 'max:255'],
            'birthday' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after:1900-01-01'],
        ];

        $data = $request->validate([
            'menu_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
        ] + $customerRules, ['birthday.date_format' => BirthdayInput::MESSAGE], [
            'menu_id' => 'メニュー',
            'date' => '日付',
            'time' => '時間',
            'name' => 'お名前',
            'phone' => '電話番号',
            'email' => 'メールアドレス',
            'birthday' => '生年月日',
        ]);

        if ($loggedIn) {
            $data['name'] = $loggedIn->name;
            $data['email'] = $loggedIn->email ?: $data['email'];
        }

        $menu = Menu::where('is_active', true)->find($data['menu_id']);
        $date = $this->parseDate($data['date']);
        $unavailable = ValidationException::withMessages([
            'time' => '選択された時間は予約できません。別の時間をお選びください。',
        ]);

        if (! $menu || ! $date) {
            throw $unavailable;
        }

        // 同じ日の予約は1件ずつ処理して、同時送信による二重予約を防ぐ
        try {
            $reservation = Cache::lock('reservation:' . $date->format('Y-m-d'), 10)->block(5, function () use ($data, $menu, $date, $unavailable, $loggedIn) {
                return DB::transaction(function () use ($data, $menu, $date, $unavailable, $loggedIn) {
                    if (! in_array($data['time'], $this->availableSlots($date, $menu), true)) {
                        throw $unavailable;
                    }

                    return $loggedIn
                        ? $this->createLoggedInReservation($loggedIn, $data, $menu, $date)
                        : $this->createNewCustomerReservation($data, $menu, $date);
                });
            });
        } catch (LockTimeoutException $e) {
            throw ValidationException::withMessages([
                'time' => '混み合っています。もう一度お試しください。',
            ]);
        } catch (UniqueConstraintViolationException $e) {
            throw $this->alreadyRegistered(); // 同じ電話番号で、ほぼ同時に登録された場合
        }

        // はじめてのお客様は、登録した内容でログインした状態にする（このあと事前カウンセリングに進めるように）
        $isNewCustomer = ! $loggedIn;
        if ($isNewCustomer) {
            $login->login($request, $reservation->customer);
        }

        $this->linkAiSession($request, $reservation);

        try {
            Mail::to($data['email'])->send(new ReservationConfirmed($reservation->load('menu')));
        } catch (\Throwable $e) {
            report($e); // メール送信に失敗しても予約は成立させる
        }

        return redirect()->route('booking.complete')->with('booked', [
            'at' => $reservation->scheduled_at->format('Y年n月j日 H:i'),
            'menu' => $menu->name,
            'reservation_id' => $reservation->id,
            'new_customer' => $isNewCustomer,
        ]);
    }

    public function complete(): View|RedirectResponse
    {
        if (! session('booked')) {
            return redirect()->route('booking.index');
        }

        return view('booking.complete', ['booked' => session('booked')]);
    }

    // はじめてのお客様の予約。登録済みの電話番号なら、ログインを案内する（他人の名前で予約したり、登録を書き換えたりできないように）
    private function createNewCustomerReservation(array $data, Menu $menu, Carbon $date): Reservation
    {
        $digits = PhoneNumber::normalize($data['phone']);
        if (Customer::whereRaw("REPLACE(phone, '-', '') = ?", [$digits])->exists()) {
            throw $this->alreadyRegistered();
        }

        $customer = Customer::create([
            'name' => $data['name'],
            'phone' => $digits,
            'email' => $data['email'],
            'birthday' => $data['birthday'] ?? null, // 月日がカルテ番号になる（Customer モデルで自動設定）
        ]);

        return Reservation::create([
            'customer_id' => $customer->id,
            'menu_id' => $menu->id,
            'scheduled_at' => $date->copy()->setTimeFromTimeString($data['time']),
            'source' => 'online',
            'status' => 'confirmed',
            'booked_name' => $data['name'],
            'booked_email' => $data['email'],
        ]);
    }

    private function alreadyRegistered(): ValidationException
    {
        return ValidationException::withMessages([
            'phone' => 'この電話番号は、すでにご登録があります。「かんたんログイン」をしてから、ご予約ください。',
        ]);
    }

    // かんたんログイン中の予約（電話番号と生年月日で本人と確認できているので、要確認にしない）
    private function createLoggedInReservation(Customer $customer, array $data, Menu $menu, Carbon $date): Reservation
    {
        // 1つ目のアドレスがない人が入力したアドレスは、2つ目に入れる
        if (! $customer->email && ! $customer->email2) {
            $customer->update(['email2' => $data['email']]);
        }

        return Reservation::create([
            'customer_id' => $customer->id,
            'menu_id' => $menu->id,
            'scheduled_at' => $date->copy()->setTimeFromTimeString($data['time']),
            'source' => 'online',
            'status' => 'confirmed',
            'booked_name' => $customer->name,
            'booked_email' => $data['email'],
            'needs_review' => false,
        ]);
    }

    // 同じブラウザでAI相談をしてから予約した場合、その相談を予約した顧客に紐づける
    // （本人確認ができない「要確認」の予約では紐づけない）
    private function linkAiSession(Request $request, Reservation $reservation): void
    {
        $id = $request->session()->get(AiAdviceController::SESSION_KEY);
        if (! $id || $reservation->needs_review) {
            return;
        }

        AiSession::whereKey($id)
            ->where('status', 'completed')
            ->whereNull('customer_id')
            ->update(['customer_id' => $reservation->customer_id]);
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
                return $t->lt($r->endsAt()) && $end->gt($r->scheduled_at); // 既存の予約は、予約に保存した所要時間で判定
            });
            if (! $busy) {
                $slots[] = $t->format('H:i');
            }
        }

        return $slots;
    }
}
