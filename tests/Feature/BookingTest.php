<?php

namespace Tests\Feature;

use App\Mail\ReservationConfirmed;
use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private Menu $menu;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(9, 0)); // 月曜日

        $day = today()->addDays(3); // 2026-10-08（木）
        $this->date = $day->format('Y-m-d');

        BusinessHour::create([
            'weekday' => $day->dayOfWeek,
            'is_open' => true,
            'open_time' => '10:00',
            'close_time' => '19:00',
        ]);

        $this->menu = Menu::create([
            'name' => 'カット',
            'price' => 6600,
            'duration_minutes' => 60,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function bookingData(array $override = []): array
    {
        return array_merge([
            'menu_id' => $this->menu->id,
            'date' => $this->date,
            'time' => '10:00',
            'name' => 'テスト花子',
            'phone' => '090-1111-2222',
            'email' => 'hanako@example.com',
        ], $override);
    }

    private function gridUrl(array $extra = []): string
    {
        return '/book?' . http_build_query(array_merge(['menu_id' => $this->menu->id], $extra));
    }

    public function test_open_slots_are_listed_in_the_weekly_table(): void
    {
        $this->get($this->gridUrl())
            ->assertOk()
            ->assertSee('value="' . $this->date . '|10:00"', false)
            ->assertSee('value="' . $this->date . '|18:00"', false)
            ->assertDontSee('value="' . $this->date . '|18:30"', false);
    }

    public function test_two_week_view_includes_next_week(): void
    {
        $nextWeekDay = today()->addDays(10); // 2026-10-15（木）。営業日は曜日で決まるので、来週の木曜日も営業日

        $this->get($this->gridUrl(['weeks' => 1]))
            ->assertDontSee('value="' . $nextWeekDay->format('Y-m-d') . '|10:00"', false);

        $this->get($this->gridUrl(['weeks' => 2]))
            ->assertSee('value="' . $nextWeekDay->format('Y-m-d') . '|10:00"', false);
    }

    public function test_booked_time_is_not_offered(): void
    {
        $customer = Customer::create(['name' => '既存', 'phone' => '08000000000']);
        Reservation::create([
            'customer_id' => $customer->id,
            'menu_id' => $this->menu->id,
            'scheduled_at' => $this->date . ' 10:00:00',
            'source' => 'admin',
            'status' => 'confirmed',
        ]);

        $this->get($this->gridUrl())
            ->assertDontSee('value="' . $this->date . '|10:00"', false)
            ->assertDontSee('value="' . $this->date . '|10:30"', false)
            ->assertSee('value="' . $this->date . '|11:00"', false);
    }

    public function test_closed_day_has_no_slots(): void
    {
        BusinessHour::query()->update(['is_open' => false]);

        $this->get($this->gridUrl())
            ->assertSee('予約できる時間がありません');
    }

    public function test_booking_from_the_table_selection_creates_reservation(): void
    {
        Mail::fake();

        $this->post('/book', [
            'menu_id' => $this->menu->id,
            'slot' => $this->date . '|13:30',
            'name' => 'テスト花子',
            'phone' => '090-1111-2222',
            'email' => 'hanako@example.com',
        ])->assertRedirect(route('booking.complete'));

        $this->assertDatabaseHas('reservations', [
            'source' => 'online',
            'status' => 'confirmed',
            'scheduled_at' => $this->date . ' 13:30:00',
        ]);
        Mail::assertSent(ReservationConfirmed::class);
    }

    public function test_booking_creates_customer_and_reservation(): void
    {
        Mail::fake();

        $this->post('/book', $this->bookingData())
            ->assertRedirect(route('booking.complete'));

        $this->assertDatabaseHas('customers', ['name' => 'テスト花子', 'phone' => '09011112222']);
        $this->assertDatabaseHas('reservations', ['source' => 'online', 'status' => 'confirmed']);
        Mail::assertSent(ReservationConfirmed::class);
    }

    public function test_existing_customer_is_reused_by_phone(): void
    {
        Mail::fake();
        Customer::create(['name' => '既存の花子', 'phone' => '090-1111-2222']);

        $this->post('/book', $this->bookingData())->assertRedirect(route('booking.complete'));

        $this->assertSame(1, Customer::count());
        $this->assertSame(1, Reservation::count());
    }

    public function test_double_booking_is_rejected(): void
    {
        Mail::fake();

        $this->post('/book', $this->bookingData())->assertRedirect(route('booking.complete'));
        $this->post('/book', $this->bookingData(['name' => '別の人', 'phone' => '08099998888']))
            ->assertSessionHasErrors('time');

        $this->assertSame(1, Reservation::count());
    }

    public function test_date_too_far_ahead_is_rejected(): void
    {
        $this->post('/book', $this->bookingData(['date' => today()->addDays(90)->format('Y-m-d')]))
            ->assertSessionHasErrors('time');

        $this->assertSame(0, Reservation::count());
    }
}
