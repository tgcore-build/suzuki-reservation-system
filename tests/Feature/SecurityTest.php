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
}
