<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CustomerImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_and_confirm_import(): void
    {
        $this->actingAs(User::factory()->create());
        Customer::create(['name' => '既存 二郎', 'phone' => '09011110002']);

        $csv = "氏名,電話番号,メールアドレス,生年月日,住所,メモ\n"
            . "新規 一郎,090-1111-0001,a@example.com,1980/5/20,名古屋,メモ1\n"
            . "既存 二郎,09011110002,,,,\n"
            . "新規 三郎,9011110003,,,,\n"
            . ",09011110004,,,,\n"
            . "エラー 五郎,abc,,,,\n";

        $file = UploadedFile::fake()->createWithContent('customers.csv', $csv);

        $this->post(route('customer-import.preview'), ['file' => $file])
            ->assertOk()
            ->assertSee('2件を登録する');

        $this->post(route('customer-import.confirm'))
            ->assertRedirect(route('customers.index'));

        $this->assertSame(3, Customer::count());
        $this->assertDatabaseHas('customers', ['name' => '新規 一郎', 'karte_number' => '0520']);
        $this->assertDatabaseHas('customers', ['name' => '新規 三郎', 'phone' => '09011110003']);
    }

    public function test_import_requires_login(): void
    {
        $this->get(route('customer-import.create'))->assertRedirect('/login');
    }

    public function test_file_without_required_columns_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $file = UploadedFile::fake()->createWithContent('bad.csv', "あ,い\n1,2\n");

        $this->post(route('customer-import.preview'), ['file' => $file])
            ->assertSessionHasErrors('file');
    }
}
