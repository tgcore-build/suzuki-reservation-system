<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_has_import_link_and_shows_flash_message(): void
    {
        $this->actingAs(User::factory()->create());

        $this->withSession(['status' => '2件を登録しました'])
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee('CSV取り込み')
            ->assertSee('2件を登録しました');
    }

    public function test_search_filters_by_name(): void
    {
        $this->actingAs(User::factory()->create());
        Customer::create(['name' => '検索 太郎', 'phone' => '09011110001']);
        Customer::create(['name' => '別人 花子', 'phone' => '09011110002']);

        $this->get(route('customers.index', ['q' => '検索']))
            ->assertOk()
            ->assertSee('検索 太郎')
            ->assertDontSee('別人 花子');
    }
}
