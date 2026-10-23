<?php

namespace Tests\Feature;

use App\Mail\ReservationReminder;
use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

// 予約に、予約したときの所要時間と料金を保存する（あとでメニューを変えても、既存の予約は変わらない）
class ReservationSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private Menu $menu;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(9, 0));
        $this->menu = Menu::create(['name' => 'カラー', 'price' => 8800, 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 1]);
        $this->customer = Customer::create(['name' => 'テスト花子', 'phone' => '09011112222', 'email' => 'hanako@example.com']);
        BusinessHour::create(['weekday' => today()->addDay()->dayOfWeek, 'is_open' => true, 'open_time' => '10:00', 'close_time' => '19:00']);
    }

    private function reserveAt(string $time, array $attributes = []): Reservation
    {
        return Reservation::create(array_merge([
            'customer_id' => $this->customer->id, 'menu_id' => $this->menu->id,
            'scheduled_at' => today()->addDay()->format('Y-m-d') . " {$time}", 'source' => 'admin', 'status' => 'confirmed',
        ], $attributes));
    }

    public function test_duration_and_price_are_saved_when_reserving(): void
    {
        $r = $this->reserveAt('10:00');

        $this->assertSame(60, $r->fresh()->duration_minutes);
        $this->assertSame(8800, $r->fresh()->price);
    }

    public function test_changing_menu_later_does_not_change_existing_reservation(): void
    {
        $r = $this->reserveAt('10:00');
        $this->menu->update(['duration_minutes' => 90, 'price' => 9900]);

        $this->assertSame(60, $r->fresh()->duration_minutes);
        $this->assertSame(8800, $r->fresh()->price);
        $this->assertSame('11:00', $r->fresh()->endsAt()->format('H:i'));
    }

    public function test_booking_slots_use_reservation_duration(): void
    {
        // 予約したときは60分（10:00〜11:00）。そのあとメニューを30分に短くしても、10:30 は空かない
        $this->reserveAt('10:00');
        $this->menu->update(['duration_minutes' => 30]);

        $html = $this->get(route('booking.index', ['menu_id' => $this->menu->id]))->getContent();
        $day = today()->addDay()->format('Y-m-d');
        $this->assertStringNotContainsString("value=\"{$day}|10:30\"", $html);
        $this->assertStringContainsString("value=\"{$day}|11:00\"", $html);
    }

    public function test_admin_conflict_check_uses_reservation_duration(): void
    {
        // 予約したときは90分（10:00〜11:30）。メニューを60分に短くしても、11:00 の予約は重なる
        $this->menu->update(['duration_minutes' => 90]);
        $this->reserveAt('10:00');
        $this->menu->update(['duration_minutes' => 60]);
        $this->actingAs(User::factory()->create());

        $this->post(route('reservations.store'), [
            'customer_id' => $this->customer->id, 'menu_id' => $this->menu->id,
            'scheduled_at' => today()->addDay()->format('Y-m-d') . ' 11:00',
        ])->assertSessionHasErrors('scheduled_at');

        $this->post(route('reservations.store'), [
            'customer_id' => $this->customer->id, 'menu_id' => $this->menu->id,
            'scheduled_at' => today()->addDay()->format('Y-m-d') . ' 11:30',
        ])->assertSessionHasNoErrors();
    }

    public function test_editing_keeps_saved_values_unless_menu_changes(): void
    {
        $r = $this->reserveAt('10:00');
        $this->menu->update(['duration_minutes' => 90, 'price' => 9900]);
        $cut = Menu::create(['name' => 'カット', 'price' => 5500, 'duration_minutes' => 45, 'is_active' => true, 'sort_order' => 2]);
        $this->actingAs(User::factory()->create());

        // メニューはそのまま、時刻だけ変える → 予約したときの値のまま
        $this->put(route('reservations.update', $r), ['customer_id' => $this->customer->id, 'menu_id' => $this->menu->id, 'scheduled_at' => today()->addDay()->format('Y-m-d') . ' 13:00'])->assertSessionHasNoErrors();
        $this->assertSame([60, 8800], [$r->fresh()->duration_minutes, $r->fresh()->price]);

        // メニューを変える → 新しいメニューの値
        $this->put(route('reservations.update', $r), ['customer_id' => $this->customer->id, 'menu_id' => $cut->id, 'scheduled_at' => today()->addDay()->format('Y-m-d') . ' 13:00'])->assertSessionHasNoErrors();
        $this->assertSame([45, 5500], [$r->fresh()->duration_minutes, $r->fresh()->price]);
    }

    public function test_schedule_and_reminder_use_reservation_values(): void
    {
        $this->reserveAt('10:00');
        $this->menu->update(['duration_minutes' => 120, 'price' => 12000]);
        $this->actingAs(User::factory()->create());

        $this->get(route('reservations.index'))->assertSee('カラー（60分）');

        $this->artisan('reminders:send')->assertSuccessful();
        Mail::assertSent(ReservationReminder::class, function (ReservationReminder $mail) {
            $html = $mail->render();

            return str_contains($html, '所要時間：約60分') && str_contains($html, '料金：8,800円');
        });
    }
}
