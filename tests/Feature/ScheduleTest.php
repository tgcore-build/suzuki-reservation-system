<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Menu;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function reserve(string $name, string $at, string $status = 'confirmed'): void
    {
        $menu = Menu::firstOrCreate(
            ['name' => 'カット'],
            ['price' => 6600, 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 1],
        );
        $customer = Customer::create(['name' => $name, 'phone' => '090' . random_int(10000000, 99999999)]);

        Reservation::create([
            'customer_id' => $customer->id,
            'menu_id' => $menu->id,
            'scheduled_at' => $at,
            'source' => 'admin',
            'status' => $status,
        ]);
    }

    public function test_schedule_requires_login(): void
    {
        $this->get(route('schedule.index'))->assertRedirect('/login');
    }

    public function test_week_view_shows_this_week_only_and_two_week_view_shows_next_week(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 7)->setTime(9, 0)); // 水曜日（週は月曜 10/5 から）
        $this->actingAs(User::factory()->create());

        $this->reserve('今週 太郎', '2026-10-08 10:00:00');
        $this->reserve('来週 花子', '2026-10-13 14:00:00');
        $this->reserve('取消 次郎', '2026-10-09 11:00:00', 'cancelled');

        $this->get(route('schedule.index'))
            ->assertOk()
            ->assertSee('今週 太郎')
            ->assertDontSee('来週 花子')
            ->assertDontSee('取消 次郎');

        $this->get(route('schedule.index', ['weeks' => 2]))
            ->assertOk()
            ->assertSee('今週 太郎')
            ->assertSee('来週 花子');
    }

    public function test_navigation_moves_to_the_next_week(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 7)->setTime(9, 0));
        $this->actingAs(User::factory()->create());

        $this->reserve('来週 花子', '2026-10-13 14:00:00');

        $this->get(route('schedule.index', ['start' => '2026-10-12']))
            ->assertOk()
            ->assertSee('来週 花子')
            ->assertSee('2026/10/12');
    }

    public function test_empty_slots_link_to_reservation_form(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 7)->setTime(12, 10)); // 水曜日 12:10
        \App\Models\BusinessHour::create(['weekday' => 3, 'is_open' => true, 'open_time' => '10:00', 'close_time' => '19:00']); // 水
        \App\Models\BusinessHour::create(['weekday' => 4, 'is_open' => true, 'open_time' => '10:00', 'close_time' => '19:00']); // 木
        \App\Models\BusinessHour::create(['weekday' => 5, 'is_open' => false, 'open_time' => '10:00', 'close_time' => '19:00']); // 金は休み
        $this->actingAs(User::factory()->create());

        $html = $this->get(route('schedule.index'))->assertOk()->getContent();
        $slot = fn (string $at) => e(route('reservations.create', ['scheduled_at' => $at, 'from' => 'schedule']));

        $this->assertStringContainsString($slot('2026-10-07 12:30'), $html); // 今日の、今より後
        $this->assertStringContainsString($slot('2026-10-08 10:00'), $html); // 明日の開店時刻
        $this->assertStringContainsString($slot('2026-10-08 18:30'), $html); // 閉店前の最後の枠
        $this->assertStringNotContainsString($slot('2026-10-07 12:00'), $html); // 過ぎた時間
        $this->assertStringNotContainsString($slot('2026-10-08 19:00'), $html); // 閉店時刻
        $this->assertStringNotContainsString($slot('2026-10-09 10:00'), $html); // 休業日
    }

    public function test_reservation_from_schedule_is_prefilled_and_returns_to_schedule(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 7)->setTime(9, 0));
        $this->actingAs(User::factory()->create());
        $menu = Menu::create(['name' => 'カット', 'price' => 6600, 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 1]);
        $customer = Customer::create(['name' => '予約表の人', 'phone' => '09012340000']);

        $this->get(route('reservations.create', ['scheduled_at' => '2026-10-08 14:30', 'from' => 'schedule']))->assertOk()
            ->assertSee('value="2026-10-08"', false)
            ->assertSee('<option value="14" selected', false)
            ->assertSee('<option value="30" selected', false)
            ->assertSee('name="from" value="schedule"', false);

        // おかしな値は入れない
        $this->get(route('reservations.create', ['scheduled_at' => '<script>']))->assertOk()
            ->assertDontSee('&lt;script&gt;', false);

        $this->post(route('reservations.store'), [
            'customer_id' => $customer->id, 'menu_id' => $menu->id,
            'scheduled_at' => '2026-10-08 14:30', 'from' => 'schedule',
        ])->assertRedirect(route('schedule.index', ['start' => '2026-10-08']));

        $this->assertDatabaseHas('reservations', ['customer_id' => $customer->id, 'scheduled_at' => '2026-10-08 14:30:00']);
        $this->followingRedirects()->get(route('schedule.index', ['start' => '2026-10-08']))->assertSee('予約表の人');

        // 一覧から登録したときは、今までどおり一覧に戻る
        $this->post(route('reservations.store'), [
            'customer_id' => $customer->id, 'menu_id' => $menu->id, 'scheduled_at' => '2026-10-09 10:00',
        ])->assertRedirect(route('reservations.index'));
    }
}
