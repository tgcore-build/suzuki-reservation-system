<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">取り込み内容の確認</h2>
    </x-slot>

    @php
        $newCount = $rows->where('status', 'new')->count();
        $dupCount = $rows->where('status', 'duplicate')->count();
        $errCount = $rows->where('status', 'error')->count();
    @endphp

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="bg-white shadow-sm sm:rounded-lg p-6 text-sm">
                <p class="mb-4">
                    登録する：<strong>{{ $newCount }}件</strong>　／　
                    重複でスキップ：{{ $dupCount }}件　／　
                    エラーでスキップ：{{ $errCount }}件
                </p>

                <div class="flex items-center gap-4">
                    @if ($newCount > 0)
                        <form method="POST" action="{{ route('customer-import.confirm') }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">{{ $newCount }}件を登録する</button>
                        </form>
                    @endif
                    <a href="{{ route('customer-import.create') }}" class="text-gray-600 underline">ファイルを選び直す</a>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <table class="w-full text-sm text-left">
                    <thead class="text-gray-500">
                        <tr>
                            <th class="py-2">行</th>
                            <th class="py-2">氏名</th>
                            <th class="py-2">電話番号</th>
                            <th class="py-2">メール</th>
                            <th class="py-2">生年月日</th>
                            <th class="py-2">結果</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $color = match ($row['status']) {
                                    'new' => 'text-green-700',
                                    'duplicate' => 'text-gray-500',
                                    default => 'text-red-700',
                                };
                            @endphp
cat > tests/Feature/CustomerImportTest.php <<'EOF'
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
