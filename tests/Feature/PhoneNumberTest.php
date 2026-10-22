<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize(): void
    {
        $this->assertSame('09011112222', PhoneNumber::normalize('090-1111-2222'));
        $this->assertSame('09011112222', PhoneNumber::normalize('０９０－１１１１－２２２２'));
        $this->assertSame('0312345678', PhoneNumber::normalize('(03) 1234-5678'));
        $this->assertSame('09011112222', PhoneNumber::normalize('090ー1111ー2222')); // 長音記号のハイフン

        foreach (['----------', 'abc090-1111-2222', '090-1111-22223', '901111222', '1234567890', '', '090.1111.2222'] as $bad) {
            $this->assertNull(PhoneNumber::normalize($bad), $bad);
        }

        // Excel で先頭の0が消えた携帯番号は、CSV取り込みのときだけ補う
        $this->assertNull(PhoneNumber::normalize('9011112222'));
        $this->assertSame('09011112222', PhoneNumber::normalize('9011112222', fixMissingZero: true));
    }

    public function test_admin_rejects_invalid_phone_and_saves_digits_only(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['----------', 'abc', '090-1111-22223'] as $bad) {
            $this->post(route('customers.store'), ['name' => 'テスト', 'phone' => $bad])
                ->assertSessionHasErrors(['phone' => PhoneNumber::MESSAGE]);
        }
        $this->assertSame(0, Customer::count());

        $this->post(route('customers.store'), ['name' => 'テスト', 'phone' => '090-1111-2222'])->assertRedirect();
        $this->assertDatabaseHas('customers', ['name' => 'テスト', 'phone' => '09011112222']);
    }

    public function test_admin_finds_duplicates_regardless_of_hyphens(): void
    {
        $this->actingAs(User::factory()->create());
        $existing = Customer::create(['name' => '既存', 'phone' => '090-1111-2222']); // 以前の、ハイフン付きのデータ

        $this->post(route('customers.store'), ['name' => '新規', 'phone' => '09011112222'])
            ->assertSessionHasErrors(['phone' => 'この電話番号は、すでに登録されています。']);

        // 自分自身の番号のままなら、更新できる
        $this->put(route('customers.update', $existing), ['name' => '既存（更新）', 'phone' => '090-1111-2222'])
            ->assertSessionHasNoErrors();
        $this->assertSame('09011112222', $existing->fresh()->phone);
    }

    public function test_admin_search_matches_with_or_without_hyphens(): void
    {
        $this->actingAs(User::factory()->create());
        Customer::create(['name' => 'ハイフンあり', 'phone' => '090-1111-2222']);
        Customer::create(['name' => 'ハイフンなし', 'phone' => '08033334444']);

        $this->get(route('customers.index', ['q' => '09011112222']))->assertSee('ハイフンあり')->assertDontSee('ハイフンなし');
        $this->get(route('customers.index', ['q' => '080-3333']))->assertSee('ハイフンなし')->assertDontSee('ハイフンあり');
    }

    public function test_csv_rejects_letters_in_phone(): void
    {
        $this->actingAs(User::factory()->create());

        $csv = "氏名,電話番号\n"
            . "全角,０９０－２２２２－０００１\n"
            . "文字混じり,abc090-2222-0002\n"
            . "ハイフンだけ,----------\n"
            . "先頭の0なし,9022220004\n";

        $this->post(route('customer-import.preview'), ['file' => UploadedFile::fake()->createWithContent('c.csv', $csv)])
            ->assertOk()
            ->assertSee('2件を登録する');
        $this->post(route('customer-import.confirm'));

        $this->assertDatabaseHas('customers', ['name' => '全角', 'phone' => '09022220001']);
        $this->assertDatabaseHas('customers', ['name' => '先頭の0なし', 'phone' => '09022220004']);
        $this->assertDatabaseMissing('customers', ['name' => '文字混じり']);
        $this->assertDatabaseMissing('customers', ['name' => 'ハイフンだけ']);
    }
}
