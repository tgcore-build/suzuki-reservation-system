<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_sent(): void
    {
        $this->get('/book')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_public_registration_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }

    public function test_photo_upload_rejects_non_image_files(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $customer = Customer::create(['name' => 'A', 'phone' => '09011110000']);

        $this->post(route('records.store', $customer), [
            'performed_on' => '2026-10-05',
            'menu' => 'カラー',
            'photo_before' => UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;'),
        ])->assertSessionHasErrors('photo_before');

        $this->assertSame(0, $customer->records()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_csv_import_rejects_non_csv_files(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('customer-import.preview'), [
            'file' => UploadedFile::fake()->createWithContent('list.php', '<?php echo 1;'),
        ])->assertSessionHasErrors('file');
    }

    public function test_login_is_locked_after_too_many_failures(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_passwords_are_hashed(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass-123']);

        $this->assertNotSame('secret-pass-123', $user->getRawOriginal('password'));
        $this->assertTrue(Hash::check('secret-pass-123', $user->password));
    }

    public function test_client_cannot_fake_ip_address_with_forwarded_header(): void
    {
        \Illuminate\Support\Facades\Route::get('/_test-ip', fn (\Illuminate\Http\Request $request) => $request->ip());

        // 直接つないできた相手が書いたヘッダーは信じない
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->get('/_test-ip', ['X-Forwarded-For' => '1.1.1.1'])
            ->assertSee('203.0.113.9');

        // 内部のプロキシ経由なら、プロキシが足した本当のIP（一番右）を使う。お客様が書いた左側の値は使わない
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->get('/_test-ip', ['X-Forwarded-For' => '1.1.1.1, 198.51.100.7'])
            ->assertSee('198.51.100.7')
            ->assertDontSee('1.1.1.1');
    }

    public function test_ai_ip_limit_cannot_be_bypassed_with_forwarded_header(): void
    {
        config(['ai.ip_daily_limit' => 1, 'ai.gemini.key' => 'test-key']);
        \App\Models\Menu::create(['name' => 'カット', 'price' => 6600, 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 1]);
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode(['reply' => '質問です', 'is_final' => false, 'menu_id' => null])]]]]],
        ])]);

        $send = fn (string $fakeIp) => $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->post('/ai', ['message' => '相談です'], ['X-Forwarded-For' => $fakeIp]);

        $send('1.1.1.1')->assertSessionHasNoErrors();
        $send('2.2.2.2')->assertSessionHasErrors('message'); // ヘッダーのIPを変えても、同じ人として数える

        \Illuminate\Support\Facades\Http::assertSentCount(1);
    }
}
