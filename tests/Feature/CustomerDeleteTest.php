<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Menu;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function reserve(Customer $customer): Reservation
    {
        $menu = Menu::firstOrCreate(['name' => 'カット'], ['price' => 6600, 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 1]);

        return Reservation::create(['customer_id' => $customer->id, 'menu_id' => $menu->id, 'scheduled_at' => '2026-10-10 10:00', 'source' => 'admin', 'status' => 'confirmed']);
    }

    public function test_customer_without_history_can_be_deleted(): void
    {
        $customer = Customer::create(['name' => '履歴なし', 'phone' => '09011112222']);

        $this->delete(route('customers.destroy', $customer))->assertRedirect(route('customers.index'));

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_customer_with_reservations_or_records_cannot_be_deleted(): void
    {
        $customer = Customer::create(['name' => '履歴あり', 'phone' => '09011112222']);
        $this->reserve($customer);
        $customer->records()->create(['performed_on' => '2026-10-10', 'menu' => 'カット']);
        $customer->records()->create(['performed_on' => '2026-10-11', 'menu' => 'カラー']);

        $this->get(route('customers.show', $customer))
            ->assertSee('この顧客には予約1件・対応記録2件があるため、削除できません');

        $this->delete(route('customers.destroy', $customer))->assertRedirect(route('customers.show', $customer));
        $this->get(route('customers.show', $customer))->assertSee('削除できません');

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
        $this->assertSame(1, Reservation::count());
        $this->assertSame(2, $customer->records()->count());
    }

    public function test_database_also_refuses_to_delete_customer_with_history(): void
    {
        $customer = Customer::create(['name' => '履歴あり', 'phone' => '09011112222']);
        $this->reserve($customer);

        $this->expectException(QueryException::class);
        $customer->delete(); // 画面を通さずに消そうとしても、DB が止める
    }
}
