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

    public function test_one_failure_does_not_stop_other_reminders(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(9, 0));
        $menu = Menu::create(['name' => 'カット', 'price' => 6600, 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 1]);
        $tomorrow = today()->addDay();

        $reservations = collect(['fail@example.com', 'ok@example.com'])->map(function (string $email, int $i) use ($menu, $tomorrow) {
            $customer = Customer::create(['name' => "客{$i}", 'phone' => '0901111000' . $i, 'email' => $email]);

            return Reservation::create([
                'customer_id' => $customer->id, 'menu_id' => $menu->id,
                'scheduled_at' => $tomorrow->copy()->setTime(10 + $i * 2, 0), 'source' => 'admin', 'status' => 'confirmed',
            ]);
        });

        // 1人目への送信だけ失敗させる
        $sentTo = [];
        Mail::shouldReceive('to')->andReturnUsing(function (string $email) use (&$sentTo) {
            return new class($email, $sentTo) {
                public function __construct(private string $email, private array &$sentTo) {}

                public function send($mailable): void
                {
                    if ($this->email === 'fail@example.com') {
                        throw new \RuntimeException('SMTP error');
                    }
                    $this->sentTo[] = $this->email;
                }
            };
        });

        $this->artisan('reminders:send')->assertFailed();

        $this->assertSame(['ok@example.com'], $sentTo);
        $this->assertNull($reservations[0]->fresh()->reminder_sent_at);   // 失敗した人は、再実行で送り直せる
        $this->assertNotNull($reservations[1]->fresh()->reminder_sent_at);
    }

    public function test_online_reminder_goes_to_address_entered_for_that_booking(): void
    {
        Mail::fake();
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(9, 0));
        $menu = Menu::create(['name' => 'カット', 'price' => 6600, 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 1]);
        $customer = Customer::create(['name' => 'A', 'phone' => '09011110000', 'email' => 'old@example.com']);
        Reservation::create([
            'customer_id' => $customer->id, 'menu_id' => $menu->id,
            'scheduled_at' => today()->addDay()->setTime(10, 0), 'source' => 'online', 'status' => 'confirmed',
            'booked_name' => 'A', 'booked_email' => 'new@example.com',
        ]);

        $this->artisan('reminders:send')->assertSuccessful();

        Mail::assertSent(ReservationReminder::class, fn ($mail) => $mail->hasTo('new@example.com') && ! $mail->hasTo('old@example.com'));
    }
}
