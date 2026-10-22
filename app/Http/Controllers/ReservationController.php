<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Menu;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReservationController extends Controller
{
    public function index(Request $request): View
    {
        $query = Reservation::with(['customer', 'menu']);

        if ($request->filled('date')) {
            $query->whereDate('scheduled_at', $request->input('date'))->orderBy('scheduled_at');
        } elseif ($request->boolean('past')) {
            $query->orderByDesc('scheduled_at');
        } else {
            // 初期表示は「今日以降」の予約を日時の早い順に表示
            $query->where('scheduled_at', '>=', Carbon::today())->orderBy('scheduled_at');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $reservations = $query->paginate(20)->withQueryString();

        return view('reservations.index', compact('reservations'));
    }

    public function create(Request $request): View
    {
        // 予約表の空き枠から来たときは、その日時を最初から入れておく
        $at = (string) $request->query('scheduled_at');

        return view('reservations.create', $this->formData() + [
            'initialAt' => preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $at) ? $at : null,
            'fromSchedule' => $request->query('from') === 'schedule',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->withDayLock($request, function () use ($request) {
            $data = $this->validated($request, null);
            $data['source'] = 'admin';
            $data['status'] = 'confirmed';

            Reservation::create($data);
        });

        // 予約表から登録したときは、その週の予約表に戻る
        if ($request->input('from') === 'schedule') {
            return redirect()
                ->route('schedule.index', ['start' => Carbon::parse($request->input('scheduled_at'))->format('Y-m-d')])
                ->with('status', '予約を登録しました。');
        }

        return redirect()->route('reservations.index')->with('status', '予約を登録しました。');
    }

    public function edit(Reservation $reservation): View
    {
        return view('reservations.edit', $this->formData($reservation) + ['reservation' => $reservation]);
    }

    public function update(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->withDayLock($request, function () use ($request, $reservation) {
            $data = $this->validated($request, $reservation);
            $data['status'] = $request->input('status', $reservation->status);

            $reservation->update($data);
        });

        return redirect()->route('reservations.index')->with('status', '予約を更新しました。');
    }

    // 削除ではなく「キャンセル」状態にする（履歴を残すため）
    public function destroy(Reservation $reservation): RedirectResponse
    {
        $reservation->update(['status' => 'cancelled']);

        return redirect()->route('reservations.index')->with('status', '予約をキャンセルしました。');
    }

    // 同じ日の予約は1件ずつ処理して、同時操作による二重予約を防ぐ（お客様向け予約と同じロック）
    private function withDayLock(Request $request, callable $callback): void
    {
        try {
            $day = Carbon::parse((string) $request->input('scheduled_at'))->format('Y-m-d');
        } catch (\Throwable $e) {
            $day = 'invalid'; // 日時の形式エラーは、このあとの入力チェックで表示する
        }

        try {
            Cache::lock('reservation:' . $day, 10)->block(5, fn () => DB::transaction($callback));
        } catch (LockTimeoutException $e) {
            throw ValidationException::withMessages([
                'scheduled_at' => '混み合っています。もう一度お試しください。',
            ]);
        }
    }

    private function formData(?Reservation $reservation = null): array
    {
        return [
            'customers' => Customer::orderBy('name')->get(),
            'menus' => Menu::where('is_active', true)
                ->when($reservation, fn ($q) => $q->orWhere('id', $reservation->menu_id))
                ->orderBy('sort_order')->orderBy('id')->get(),
        ];
    }

    private function validated(Request $request, ?Reservation $current): array
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'menu_id' => ['required', 'exists:menus,id'],
            'scheduled_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(array_keys(Reservation::STATUSES))],
        ], [], [
            'customer_id' => '顧客',
            'menu_id' => 'メニュー',
            'scheduled_at' => '予約日時',
            'notes' => 'メモ',
            'status' => '状態',
        ]);

        if ((int) Carbon::parse($data['scheduled_at'])->format('i') % 15 !== 0) {
            throw ValidationException::withMessages([
                'scheduled_at' => '予約時刻は15分単位（00・15・30・45分）で入力してください。',
            ]);
        }

        $newStatus = $request->input('status', $current?->status ?? 'confirmed');

        if ($newStatus !== 'cancelled' && $this->hasConflict(
            Carbon::parse($data['scheduled_at']),
            Menu::findOrFail($data['menu_id']),
            $current?->id
        )) {
            throw ValidationException::withMessages([
                'scheduled_at' => 'この時間帯には、すでに別の予約があります。',
            ]);
        }

        unset($data['status']);

        return $data;
    }

    // 同じ日の予約のうち、時間帯が重なるものがあるか（キャンセル済みは除く）
    private function hasConflict(Carbon $start, Menu $menu, ?int $ignoreId): bool
    {
        $end = $start->copy()->addMinutes($menu->duration_minutes);

        return Reservation::with('menu')
            ->where('status', '!=', 'cancelled')
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->whereBetween('scheduled_at', [$start->copy()->startOfDay(), $start->copy()->endOfDay()])
            ->get()
            ->contains(function (Reservation $r) use ($start, $end) {
                $rEnd = $r->scheduled_at->copy()->addMinutes($r->menu->duration_minutes);

                return $start < $rEnd && $end > $r->scheduled_at;
            });
    }
}
