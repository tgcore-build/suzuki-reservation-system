<?php

namespace Tests\Feature;

use App\Mail\ReservationChanged;
use App\Mail\ReservationConfirmed;
use App\Mail\ReservationReminder;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReservationChangeTest extends TestCase
{
    use RefreshDatabase;

    private Menu $menu;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(9, 0));
        config(['salon.phone' => '000-1234-5678']);
        $this->menu = Menu::create(['name' => 'カット', 'price' => 6600, 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 1]);
        $this->customer = Customer::create(['name' => 'テスト花子', 'phone' => '09011112222', 'email' => 'hanako@example.com']);
        $this->actingAs(User::factory()->create());
    }

    private function reservation(array $attributes = []): Reservation
    {
        return Reservation::create(array_merge([
            'customer_id' => $this->customer->id, 'menu_id' => $this->menu->id,
            'scheduled_at' => '2026-10-06 10:00', 'source' => 'admin', 'status' => 'confirmed',
            'reminder_sent_at' => now(), 'reminder_channel' => 'email',
        ], $attributes));
    }

    private function update(Reservation $r, string $at, array $extra = [])
    {
        return $this->put(route('reservations.update', $r), array_merge([
            'customer_id' => $this->customer->id, 'menu_id' => $this->menu->id, 'scheduled_at' => $at,
        ], $extra));
    }

    public function test_changing_time_sends_notice_and_resets_reminder(): void
    {
        $r = $this->reservation();

        $this->update($r, '2026-10-08 14:00')->assertRedirect(route('reservations.index'));

        $this->assertNull($r->fresh()->reminder_sent_at);
        $this->assertNull($r->fresh()->reminder_channel);
        Mail::assertSent(ReservationChanged::class, function (ReservationChanged $mail) {
            $html = $mail->render();

            return $mail->hasTo('hanako@example.com')
                && str_contains($html, '変更前：2026年10月6日 10:00')
                && str_contains($html, '変更後：2026年10月8日 14:00')
                && str_contains($html, '000-1234-5678');
        });
        $this->get(route('reservations.index'))->assertSee('お客様に変更のお知らせをメールで送りました。');

        // 新しい日時の前日に、もう一度リマインドが送られる
        $this->travelTo(now()->setDate(2026, 10, 7)->setTime(18, 0));
        $this->artisan('reminders:send')->assertSuccessful();
        Mail::assertSent(ReservationReminder::class, 1);
    }

    public function test_changing_other_fields_does_not_notify_or_reset(): void
    {
        $r = $this->reservation();

        $this->update($r, '2026-10-06 10:00', ['notes' => 'メモだけ変更']);

        $this->assertNotNull($r->fresh()->reminder_sent_at);
        Mail::assertNotSent(ReservationChanged::class);
    }

    public function test_online_booking_notice_goes_to_entered_address(): void
    {
        $r = $this->reservation(['source' => 'online', 'booked_name' => 'テスト花子', 'booked_email' => 'booked@example.com']);

        $this->update($r, '2026-10-08 14:00');

        Mail::assertSent(ReservationChanged::class, fn ($mail) => $mail->hasTo('booked@example.com'));
    }

    public function test_no_email_shows_message_to_staff(): void
    {
        $this->customer->update(['email' => null]);
        $r = $this->reservation();

        $this->update($r, '2026-10-08 14:00');

        Mail::assertNotSent(ReservationChanged::class);
        $this->get(route('reservations.index'))->assertSee('メールアドレスがないため、変更のお知らせは送っていません');
    }

    public function test_cancelled_reservation_is_not_notified(): void
    {
        $r = $this->reservation();

        $this->update($r, '2026-10-08 14:00', ['status' => 'cancelled']);

        Mail::assertNotSent(ReservationChanged::class);
    }

    public function test_shop_phone_is_in_reminder_and_confirmation(): void
    {
        $this->reservation(['reminder_sent_at' => null, 'reminder_channel' => null]);

        $this->artisan('reminders:send')->assertSuccessful();
        Mail::assertSent(ReservationReminder::class, fn ($mail) => str_contains($mail->render(), 'お電話：000-1234-5678'));

        $confirmed = (new ReservationConfirmed(Reservation::first()->load('menu')))->render();
        $this->assertStringContainsString('お電話：000-1234-5678', $confirmed);

        // 電話番号の設定がなければ、行ごと出さない
        config(['salon.phone' => null]);
        $this->assertStringNotContainsString('お電話', (new ReservationConfirmed(Reservation::first()->load('menu')))->render());
    }
}
