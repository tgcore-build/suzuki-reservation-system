<?php

namespace Tests\Feature;

use App\Models\AiSession;
use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BooksAsNewCustomer;
use Tests\TestCase;

class AiSessionTest extends TestCase
{
    use RefreshDatabase, BooksAsNewCustomer;

    private function completedSession(array $attributes = []): AiSession
    {
        $session = AiSession::create(array_merge(['status' => 'completed', 'result_menu' => 'うる艶トリートメント'], $attributes));
        $session->messages()->create(['role' => 'user', 'content' => '髪の乾燥が気になります']);
        $session->messages()->create(['role' => 'ai', 'content' => 'トリートメントがおすすめです']);

        return $session;
    }

    private function bookingData(Menu $menu, array $override = []): array
    {
        return array_merge([
            'menu_id' => $menu->id,
            'date' => today()->addDays(3)->format('Y-m-d'),
            'time' => '10:00',
            'name' => 'テスト花子',
            'phone' => '090-1111-2222',
            'email' => 'hanako@example.com',
        ], $override);
    }

    private function prepareBooking(): Menu
    {
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(9, 0));
        BusinessHour::create(['weekday' => today()->addDays(3)->dayOfWeek, 'is_open' => true, 'open_time' => '10:00', 'close_time' => '19:00']);

        return Menu::create(['name' => 'カット', 'price' => 6600, 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 1]);
    }

    public function test_guests_cannot_see_ai_results(): void
    {
        $session = $this->completedSession();

        $this->get(route('ai-sessions.index'))->assertRedirect(route('login'));
        $this->get(route('ai-sessions.show', $session))->assertRedirect(route('login'));
    }

    public function test_admin_sees_list_with_booking_status(): void
    {
        $customer = Customer::create(['name' => '予約した花子', 'phone' => '09000000001']);
        $this->completedSession(['customer_id' => $customer->id, 'result_menu' => 'カラー']);
        $this->completedSession(['result_menu' => 'ヘッドスパ']);

        $this->actingAs(User::factory()->create());

        $this->get(route('ai-sessions.index'))->assertOk()
            ->assertSee('カラー')->assertSee('予約した花子')
            ->assertSee('ヘッドスパ')->assertSee('未');

        $this->get(route('ai-sessions.index', ['booked' => 'no']))->assertOk()
            ->assertSee('ヘッドスパ')->assertDontSee('予約した花子');
    }

    public function test_admin_sees_conversation_and_customer_page_shows_result(): void
    {
        $customer = Customer::create(['name' => '予約した花子', 'phone' => '09000000001']);
        $session = $this->completedSession(['customer_id' => $customer->id]);

        $this->actingAs(User::factory()->create());

        $this->get(route('ai-sessions.show', $session))->assertOk()
            ->assertSee('髪の乾燥が気になります')->assertSee('トリートメントがおすすめです');

        $this->get(route('customers.show', $customer))->assertOk()
            ->assertSee('うる艶トリートメント');
    }

    public function test_booking_after_ai_advice_links_session_to_customer(): void
    {
        Mail::fake();
        $menu = $this->prepareBooking();
        $session = $this->completedSession();

        $this->withSession(['ai_session_id' => $session->id])
            ->bookAsNewCustomer($this->bookingData($menu))
            ->assertRedirect(route('booking.complete'));

        $this->assertSame(Customer::first()->id, $session->fresh()->customer_id);
    }

    public function test_in_progress_session_is_not_linked(): void
    {
        Mail::fake();
        $menu = $this->prepareBooking();
        $session = AiSession::create(['status' => 'in_progress']);

        $this->withSession(['ai_session_id' => $session->id])
            ->bookAsNewCustomer($this->bookingData($menu))
            ->assertRedirect(route('booking.complete'));

        $this->assertNull($session->fresh()->customer_id);
    }
}
