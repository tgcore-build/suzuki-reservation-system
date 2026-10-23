<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Menu;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function reserve(Customer $customer, string $time = '10:00:00'): Reservation
    {
        $menu = Menu::firstOrCreate(['name' => 'カット'], [
            'price' => 6600, 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 1,
        ]);

        return Reservation::create([
            'customer_id' => $customer->id,
            'menu_id' => $menu->id,
            'scheduled_at' => today()->addDay()->format('Y-m-d') . ' ' . $time,
            'source' => 'admin',
            'status' => 'confirmed',
        ]);
    }

    public function test_dashboard_shows_reminder_status_for_tomorrow(): void
    {
        $user = User::factory()->create();

        $unsent = Customer::create(['name' => '未送信さん', 'phone' => '09011110001', 'email' => 'a@example.com']);
        $sent = Customer::create(['name' => '送信済みさん', 'phone' => '09011110002', 'email' => 'b@example.com']);
        $noMail = Customer::create(['name' => 'メールなしさん', 'phone' => '09011110003']);

        $this->reserve($unsent, '10:00:00');
        $sentReservation = $this->reserve($sent, '12:00:00');
        $this->reserve($noMail, '14:00:00');
        Reservation::where('id', $sentReservation->id)->update(['reminder_sent_at' => now(), 'reminder_channel' => 'line']);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('未送信（18:00に自動送信）')
            ->assertSee('送信済み・LINE（')
            ->assertSee('LINE 未連携・メール未登録（送れません）')
            ->assertSee('送信済みさん 様');
    }

    public function test_dashboard_without_reservations_shows_empty_messages(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')
            ->assertOk()
            ->assertSee('明日の予約はありません。')
            ->assertSee('まだ送信履歴はありません。');
    }
}
