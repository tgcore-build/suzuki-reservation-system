<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// カルテ番号：生年月日の月日4桁 - 同じ月日の中での連番（重ならない）
class KarteNumberTest extends TestCase
{
    use RefreshDatabase;

    private function customer(string $phone, ?string $birthday): Customer
    {
        return Customer::create(['name' => "客{$phone}", 'phone' => $phone, 'birthday' => $birthday]);
    }

    public function test_same_birthday_gets_different_numbers(): void
    {
        $a = $this->customer('09000000001', '1990-02-02');
        $b = $this->customer('09000000002', '1985-02-02');
        $c = $this->customer('09000000003', '1990-03-03');

        $this->assertSame('0202-1', $a->fresh()->karte_number);
        $this->assertSame('0202-2', $b->fresh()->karte_number);
        $this->assertSame('0303-1', $c->fresh()->karte_number);
    }

    public function test_numbers_are_not_reused_after_delete(): void
    {
        $this->customer('09000000001', '1990-02-02');
        $second = $this->customer('09000000002', '1990-02-02');
        $second->delete();

        $this->assertSame('0202-3', $this->customer('09000000003', '1990-02-02')->fresh()->karte_number);
    }

    public function test_number_is_kept_when_only_year_changes_and_renewed_when_month_day_changes(): void
    {
        $a = $this->customer('09000000001', '1990-02-02');
        $this->customer('09000000002', '1990-05-05');

        $a->update(['birthday' => '1991-02-02']);
        $this->assertSame('0202-1', $a->fresh()->karte_number);

        $a->update(['birthday' => '1991-05-05']);
        $this->assertSame('0505-2', $a->fresh()->karte_number);

        $a->update(['birthday' => null]);
        $this->assertNull($a->fresh()->karte_number);

        $this->assertNull($this->customer('09000000003', null)->fresh()->karte_number);
    }

    public function test_search_by_month_day_finds_all_of_them(): void
    {
        $this->customer('09000000001', '1990-02-02');
        $this->customer('09000000002', '1985-02-02');
        $this->actingAs(User::factory()->create());

        $this->get(route('customers.index', ['q' => '0202']))->assertSee('0202-1')->assertSee('0202-2');
        $this->get(route('customers.index', ['q' => '0202-2']))->assertSee('客09000000002')->assertDontSee('客09000000001');
    }

    public function test_admin_form_assigns_number(): void
    {
        $this->actingAs(User::factory()->create());
        $this->customer('09000000001', '1990-02-02');

        $this->post(route('customers.store'), ['name' => '新規', 'phone' => '09000000009', 'birthday' => '2000-02-02'])->assertRedirect();

        $this->assertSame('0202-2', Customer::where('name', '新規')->first()->karte_number);
    }
}
