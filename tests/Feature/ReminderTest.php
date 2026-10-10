<?php

namespace Tests\Feature;

use App\Mail\ReservationReminder;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminder_is_sent_once_only_for_valid_reservations_tomorrow(): void
    {
        Mail::fake();
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(9, 0));

        $menu = Menu::create([
            'name' => 'カット', 'price' => 6600, 'duration_minutes' => 60,
            'is_active' => true, 'sort_order' => 1,
        ]);
        $withMail = Customer::create(['name' => 'A', 'phone' => '09011110000', 'email' => 'a@example.com']);
        $noMail = Customer::create(['name' => 'B', 'phone' => '09022220000']);
        $tomorrow = today()->addDay();

        $make = fn (Customer $c, $at, string $status = 'confirmed') => Reservation::create([
            'customer_id' => $c->id,
            'menu_id' => $menu->id,
            'scheduled_at' => $at,
            'source' => 'admin',
            'status' => $status,
        ]);

        $make($withMail, $tomorrow->copy()->setTime(10, 0));
        $make($noMail, $tomorrow->copy()->setTime(14, 0));
        $make($withMail, $tomorrow->copy()->setTime(16, 0), 'cancelled');
        $make($withMail, today()->addDays(2)->setTime(10, 0));

        $this->artisan('reminders:send')->assertSuccessful();
        $this->artisan('reminders:send')->assertSuccessful();

        Mail::assertSent(ReservationReminder::class, 1);
    }
}
