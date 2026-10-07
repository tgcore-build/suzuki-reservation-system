<?php

namespace App\Http\Controllers;

use App\Models\BusinessHour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BusinessHourController extends Controller
{
    public function edit(): View
    {
        // 日〜土の7行が無ければ作る（初期値: 営業・10:00〜19:00）
        foreach (range(0, 6) as $day) {
            BusinessHour::firstOrCreate(
                ['weekday' => $day],
                ['is_open' => true, 'open_time' => '10:00', 'close_time' => '19:00']
            );
        }

        $hours = BusinessHour::orderBy('weekday')->get();

        return view('business-hours.edit', compact('hours'));
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'hours.*.open_time' => ['nullable', 'date_format:H:i'],
            'hours.*.close_time' => ['nullable', 'date_format:H:i'],
        ]);

        $errors = [];
        $rows = [];

        foreach (range(0, 6) as $day) {
            $row = $request->input("hours.$day", []);
            $isOpen = isset($row['is_open']);
            $open = $row['open_time'] ?? null;
            $close = $row['close_time'] ?? null;

            if ($isOpen && (! $open || ! $close)) {
                $errors["hours.$day"] = BusinessHour::WEEKDAYS[$day] . '曜日: 開店・閉店の時刻を入力してください。';
            } elseif ($isOpen && $close <= $open) {
                $errors["hours.$day"] = BusinessHour::WEEKDAYS[$day] . '曜日: 閉店は開店より後の時刻にしてください。';
            }

            $rows[$day] = [
                'is_open' => $isOpen,
                'open_time' => $isOpen ? $open : null,
                'close_time' => $isOpen ? $close : null,
            ];
        }

        if ($errors) {
            return back()->withErrors($errors)->withInput();
        }

        foreach ($rows as $day => $data) {
            BusinessHour::where('weekday', $day)->update($data);
        }

        return redirect()->route('business-hours.edit')->with('status', '営業時間を保存しました。');
    }
}
