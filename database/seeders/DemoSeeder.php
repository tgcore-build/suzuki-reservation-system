<?php

namespace Database\Seeders;

use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\Record;
use App\Models\Reservation;
use Illuminate\Database\Seeder;

// デモ・画面確認用のダミーデータ（実在の人物・店とは無関係）。何度実行しても二重には増えない。
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $menus = Menu::where('is_active', true)->orderBy('sort_order')->get();
        if ($menus->isEmpty()) {
            foreach ([['カット', 6600, 60], ['カラー', 8800, 90], ['パーマ', 11000, 120]] as $i => [$name, $price, $min]) {
                Menu::create(['name' => $name, 'price' => $price, 'duration_minutes' => $min, 'is_active' => true, 'sort_order' => $i + 1]);
            }
            $menus = Menu::orderBy('sort_order')->get();
        }

        $people = [
            ['デモ 花子', '09000000001', 'demo-hanako@example.com'],
            ['デモ 桜', '09000000002', 'demo-sakura@example.com'],
            ['デモ 葵', '09000000003', 'demo-aoi@example.com'],
            ['デモ 椿', '09000000004', null], // メール未登録の例
        ];
        $customers = [];
        foreach ($people as [$name, $phone, $email]) {
            $customers[] = Customer::firstOrCreate(['phone' => $phone], ['name' => $name, 'email' => $email]);
        }

        // 今日以降の営業日を8日分
        $hours = BusinessHour::all()->keyBy('weekday');
        $days = [];
        for ($i = 0; count($days) < 8 && $i < 21; $i++) {
            $d = today()->addDays($i);
            if (! isset($hours[$d->dayOfWeek]) || $hours[$d->dayOfWeek]->is_open) {
                $days[] = $d;
            }
        }

        $plan = [
            [0, '11:00', 0, 0], [0, '14:00', 1, 1],
            [1, '10:00', 2, 0], [1, '13:30', 3, 1],
            [2, '10:30', 0, 1], [3, '15:00', 1, 0],
            [4, '11:00', 2, 2], [5, '13:00', 3, 0],
            [6, '10:00', 0, 0], [7, '14:30', 1, 1],
        ];
        foreach ($plan as [$dayIndex, $time, $customerIndex, $menuIndex]) {
            if (! isset($days[$dayIndex])) {
                continue;
            }
            $menu = $menus[$menuIndex % $menus->count()];
            $reservation = Reservation::firstOrCreate([
                'customer_id' => $customers[$customerIndex]->id,
                'scheduled_at' => $days[$dayIndex]->format('Y-m-d') . ' ' . $time . ':00',
            ], [
                'menu_id' => $menu->id,
                'source' => $dayIndex % 2 === 0 ? 'admin' : 'online',
                'status' => 'confirmed',
            ]);
            // 今日の予約は、昨日の18:00にリマインド送信済みとして表示する
            if ($dayIndex === 0 && $customers[$customerIndex]->email && $reservation->wasRecentlyCreated) {
                Reservation::where('id', $reservation->id)->update(['reminder_sent_at' => now()->subDay()->setTime(18, 0)]);
            }
        }

        // 対応記録（写真なし）
        $records = [
            [0, -35, 'カラー', 'オーガニックカラー 7レベル・トリートメント', 90, '根元のリタッチ。頭皮の状態は良好。次回は3か月後を目安に。'],
            [0, -70, 'カット', null, 60, '前下がりボブ。顔まわりを少し軽く。'],
            [1, -20, 'パーマ', 'デジタルパーマ用1剤・2剤', 120, 'ゆるめのカール。ホームケアを案内。'],
            [2, -45, 'カット', null, 50, '毛先を1cmカット。'],
        ];
        foreach ($records as [$customerIndex, $daysAgo, $menuName, $materials, $minutes, $memo]) {
            Record::firstOrCreate([
                'customer_id' => $customers[$customerIndex]->id,
                'performed_on' => today()->addDays($daysAgo)->format('Y-m-d'),
            ], [
                'menu' => $menuName,
                'materials' => $materials,
                'duration_minutes' => $minutes,
                'memo' => $memo,
            ]);
        }
    }
}
