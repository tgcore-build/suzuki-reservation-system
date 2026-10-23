<?php

namespace Tests\Feature;

use App\Mail\BookingEmailCode;
use App\Mail\ReservationConfirmed;
use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BooksAsNewCustomer;
use Tests\TestCase;

// はじめての予約：メールアドレスがご本人のものか確かめてから、顧客と予約を作る
class BookingEmailVerifyTest extends TestCase
{
    use RefreshDatabase, BooksAsNewCustomer;

    private Menu $menu;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(9, 0));
        $day = today()->addDays(3);
        $this->date = $day->format('Y-m-d');
        BusinessHour::create(['weekday' => $day->dayOfWeek, 'is_open' => true, 'open_time' => '10:00', 'close_time' => '19:00']);
        $this->menu = Menu::create(['name' => 'カット', 'price' => 6600, 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 1]);
    }

    private function data(array $override = []): array
    {
        return array_merge([
            'menu_id' => $this->menu->id, 'date' => $this->date, 'time' => '10:00',
            'name' => 'テスト花子', 'phone' => '090-1111-2222', 'email' => 'hanako@example.com',
        ], $override);
    }

    private function wrong(): string
    {
        return $this->lastBookingCode() === '000000' ? '111111' : '000000';
    }

    public function test_nothing_is_created_until_the_email_code_is_entered(): void
    {
        $this->post('/book', $this->data())->assertRedirect(route('booking.verify'));

        $this->assertSame(0, Customer::count());
        $this->assertSame(0, Reservation::count());
        Mail::assertSent(BookingEmailCode::class, fn ($mail) => $mail->hasTo('hanako@example.com'));
        Mail::assertNotSent(ReservationConfirmed::class);

        $this->get('/book/verify')->assertOk()
            ->assertSee('hanako@example.com')
            ->assertSee('2026年10月8日 10:00')
            ->assertSee('カット');
    }

    public function test_correct_code_creates_customer_and_reservation(): void
    {
        $this->bookAsNewCustomer($this->data())->assertRedirect(route('booking.complete'));

        $customer = Customer::first();
        $this->assertSame(['テスト花子', '09011112222', 'hanako@example.com'], [$customer->name, $customer->phone, $customer->email]);
        $this->assertSame($customer->id, Reservation::first()->customer_id);
        Mail::assertSent(ReservationConfirmed::class, fn ($mail) => $mail->hasTo('hanako@example.com'));

        // 確認コードはもう使えず、預けていた入力も消えている
        $this->get('/book/verify')->assertRedirect(route('booking.index'));
    }

    public function test_wrong_code_does_not_create_and_too_many_mistakes_invalidate_the_code(): void
    {
        $this->post('/book', $this->data());
        $code = $this->lastBookingCode();

        $this->post('/book/verify', ['code' => $this->wrong()])->assertRedirect(route('booking.verify'));
        $this->assertSame(0, Customer::count());

        for ($i = 0; $i < 4; $i++) {
            $this->post('/book/verify', ['code' => $this->wrong()]);
        }
        $this->post('/book/verify', ['code' => $code])->assertSessionHasErrors('code'); // 5回まちがえたので無効
        $this->assertSame(0, Customer::count());
    }

    public function test_code_expires_after_10_minutes(): void
    {
        $this->post('/book', $this->data());
        $code = $this->lastBookingCode();
        $this->travel(11)->minutes();

        $this->post('/book/verify', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertSame(0, Customer::count());
    }

    public function test_resend_is_limited(): void
    {
        $this->post('/book', $this->data()); // 1回目
        $this->post('/book/verify/resend'); // 2回目
        $this->post('/book/verify/resend'); // 3回目
        $this->post('/book/verify/resend')->assertRedirect(route('booking.verify'));
        $this->get('/book/verify')->assertSee('コードの送信回数が多すぎます');

        Mail::assertSent(BookingEmailCode::class, 3);
    }

    public function test_slot_taken_while_verifying_is_rejected(): void
    {
        $this->post('/book', $this->data());
        $code = $this->lastBookingCode();

        // そのあいだに、ほかの人が同じ枠を予約した
        $other = Customer::create(['name' => '先の人', 'phone' => '08099998888']);
        Reservation::create(['customer_id' => $other->id, 'menu_id' => $this->menu->id, 'scheduled_at' => "{$this->date} 10:00", 'source' => 'admin', 'status' => 'confirmed']);

        $this->post('/book/verify', ['code' => $code])
            ->assertRedirect(route('booking.index', ['menu_id' => $this->menu->id]))
            ->assertSessionHasErrors('time');
        $this->assertSame(1, Customer::count());
        $this->assertSame(1, Reservation::count());
    }

    public function test_phone_registered_while_verifying_is_rejected(): void
    {
        $this->post('/book', $this->data());
        $code = $this->lastBookingCode();
        Customer::create(['name' => '同じ番号の人', 'phone' => '09011112222']);

        $this->post('/book/verify', ['code' => $code])->assertSessionHasErrors('phone');
        $this->assertSame(0, Reservation::count());
    }

    public function test_pending_input_expires_after_30_minutes(): void
    {
        $this->post('/book', $this->data());
        $this->travel(31)->minutes();

        $this->get('/book/verify')->assertRedirect(route('booking.index'));
        $this->post('/book/verify', ['code' => '123456'])->assertRedirect(route('booking.index'));
    }

    public function test_verify_page_without_booking_goes_back(): void
    {
        $this->get('/book/verify')->assertRedirect(route('booking.index'));
    }
}
