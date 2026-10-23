<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Menu;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function menu(string $name = 'カット'): Menu
    {
        return Menu::create(['name' => $name, 'price' => 6600, 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 1]);
    }

    public function test_menu_without_reservations_is_deleted(): void
    {
        $menu = $this->menu();

        $this->delete(route('menus.destroy', $menu))->assertRedirect(route('menus.index'));

        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
    }

    public function test_menu_with_reservations_is_hidden_instead_of_deleted(): void
    {
        $menu = $this->menu();
        $customer = Customer::create(['name' => 'テスト花子', 'phone' => '09011112222']);
        Reservation::create(['customer_id' => $customer->id, 'menu_id' => $menu->id, 'scheduled_at' => '2026-10-10 10:00', 'source' => 'admin', 'status' => 'completed']);

        $this->get(route('menus.index'))->assertSee('非公開にする')->assertSee('予約が1件あるため');

        $this->delete(route('menus.destroy', $menu))->assertRedirect(route('menus.index')); // エラーにならない

        $this->assertDatabaseHas('menus', ['id' => $menu->id, 'is_active' => false]);
        $this->assertSame(1, Reservation::count());
        $this->get(route('menus.index'))->assertSee('予約があるため削除せず、非公開にしました')->assertSee('非公開');

        // お客様の予約画面には出ない
        $this->get('/book')->assertDontSee('<option value="' . $menu->id . '"', false);
        // 予約の一覧では、メニュー名がそのまま出る
        $this->get(route('reservations.index', ['past' => 1]))->assertOk()->assertSee('カット');
    }
}
