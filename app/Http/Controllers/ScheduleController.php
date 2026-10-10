<?php

namespace App\Http\Controllers;

use App\Models\BusinessHour;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    private const ROW_HEIGHT = 48; // 1時間あたりの高さ（px）

    public function index(Request $request): View
    {
        $weeks = (int) $request->query('weeks') === 2 ? 2 : 1;
        $start = $this->parseStart($request->query('start'));
        $end = $start->copy()->addDays($weeks * 7); // この日の前日まで表示

        $reservations = Reservation::with(['customer', 'menu'])
            ->where('status', '!=', 'cancelled')
            ->where('scheduled_at', '>=', $start)
            ->where('scheduled_at', '<', $end)
            ->orderBy('scheduled_at')
            ->get();

        $hours = BusinessHour::all()->keyBy('weekday');

        // 表示する時間帯（営業時間と予約の両方が収まるように広げる）
        $startHour = 9;
        $endHour = 20;
        foreach ($hours as $h) {
            if ($h->is_open) {
                $startHour = min($startHour, (int) substr($h->open_time, 0, 2));
                $endHour = max($endHour, $this->ceilHour($h->close_time));
            }
        }
        foreach ($reservations as $r) {
            $rEnd = $r->scheduled_at->copy()->addMinutes($r->menu?->duration_minutes ?? 60);
            $startHour = min($startHour, $r->scheduled_at->hour);
            $endHour = max($endHour, $rEnd->isSameDay($r->scheduled_at) ? $this->ceilHour($rEnd->format('H:i')) : 24);
        }

        $weekGrids = [];
        for ($w = 0; $w < $weeks; $w++) {
            $days = [];
            for ($d = 0; $d < 7; $d++) {
                $days[] = $start->copy()->addDays($w * 7 + $d);
            }
            $weekGrids[] = $days;
        }

        return view('reservations.schedule', [
            'weeks' => $weeks,
            'start' => $start,
            'end' => $end,
            'weekGrids' => $weekGrids,
            'byDay' => $reservations->groupBy(fn (Reservation $r) => $r->scheduled_at->format('Y-m-d')),
            'hours' => $hours,
            'startHour' => $startHour,
            'endHour' => $endHour,
            'rowHeight' => self::ROW_HEIGHT,
            'prevStart' => $start->copy()->subDays($weeks * 7)->format('Y-m-d'),
            'nextStart' => $start->copy()->addDays($weeks * 7)->format('Y-m-d'),
            'thisWeek' => today()->startOfWeek(Carbon::MONDAY)->format('Y-m-d'),
        ]);
    }

    // 指定日を含む週の月曜日。指定がなければ今週の月曜日
    private function parseStart(?string $value): Carbon
    {
        try {
            $date = $value ? Carbon::createFromFormat('Y-m-d', $value) : today();
        } catch (\Throwable $e) {
            $date = today();
        }

        return $date->startOfDay()->startOfWeek(Carbon::MONDAY);
    }

    // "19:30" → 20（時刻を切り上げた「時」）
    private function ceilHour(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($time, 0, 5)));

        return $h + ($m > 0 ? 1 : 0);
    }
}
