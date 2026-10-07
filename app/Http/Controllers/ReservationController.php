<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Menu;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function create(): View
    {
        return view('reservations.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $data['source'] = 'admin';
        $data['status'] = 'confirmed';

        Reservation::create($data);

        return redirect()->route('reservations.index')->with('status', '予約を登録しました。');
    }

    public function edit(Reservation $reservation): View
    {
        return view('reservations.edit', $this->formData($reservation) + ['reservation' => $reservation]);
    }

    public function update(Request $request, Reservation $reservation): RedirectResponse
    {
        $data = $this->validated($request, $reservation);
        $data['status'] = $request->input('status', $reservation->status);

        $reservation->update($data);

        return redirect()->route('reservations.index')->with('status', '予約を更新しました。');
    }

    // 削除ではなく「キャンセル」状態にする（履歴を残すため）
    public function destroy(Reservation $reservation): RedirectResponse
    {
        $reservation->update(['status' => 'cancelled']);

        return redirect()->route('reservations.index')->with('status', '予約をキャンセルしました。');
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
