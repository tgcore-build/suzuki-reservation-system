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
}
