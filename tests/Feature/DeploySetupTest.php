<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeploySetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_seeder_creates_first_admin_from_env(): void
    {
        config(['salon.admin.email' => 'owner@example.com', 'salon.admin.password' => 'long-enough-password']);

        $this->seed(AdminUserSeeder::class);

        $admin = User::where('email', 'owner@example.com')->first();
        $this->assertNotNull($admin);
        $this->assertTrue(Hash::check('long-enough-password', $admin->password));
        $this->assertNotNull($admin->email_verified_at);
    }

    public function test_admin_seeder_does_not_change_existing_password(): void
    {
        User::factory()->create(['email' => 'owner@example.com', 'password' => 'changed-in-admin-screen']);
        config(['salon.admin.email' => 'owner@example.com', 'salon.admin.password' => 'long-enough-password']);

        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertTrue(Hash::check('changed-in-admin-screen', User::first()->password));
    }

    public function test_admin_seeder_does_nothing_without_env_or_with_short_password(): void
    {
        config(['salon.admin.email' => null, 'salon.admin.password' => null]);
        $this->seed(AdminUserSeeder::class);

        config(['salon.admin.email' => 'owner@example.com', 'salon.admin.password' => 'short']);
        $this->seed(AdminUserSeeder::class);

        $this->assertSame(0, User::count());
    }

    public function test_render_yaml_keeps_secrets_out_and_sets_production_values(): void
    {
        $yaml = file_get_contents(base_path('render.yaml'));

        foreach ([
            'APP_DEBUG' => 'value: "false"', 'APP_ENV' => 'value: production', 'APP_LOCALE' => 'value: ja',
            'APP_TIMEZONE' => 'value: Asia/Tokyo', 'GEMINI_MODEL' => 'value: gemini-3.1-flash-lite', 'PHOTO_DISK' => 'value: s3',
        ] as $key => $expected) {
            $this->assertMatchesRegularExpression('/key: ' . $key . '\s+' . preg_quote($expected, '/') . '/', $yaml, $key);
        }

        // 秘密の値は、値を書かずに sync: false にする
        foreach (['APP_KEY', 'DB_PASSWORD', 'DB_SSL_CA_CERT', 'MAIL_PASSWORD', 'AWS_SECRET_ACCESS_KEY', 'GEMINI_API_KEY',
            'LINE_LOGIN_CHANNEL_SECRET', 'LINE_MESSAGING_ACCESS_TOKEN', 'ADMIN_PASSWORD'] as $key) {
            $this->assertMatchesRegularExpression('/key: ' . $key . '\s+sync: false/', $yaml, $key);
        }
    }

    public function test_sender_name_is_not_laravel_by_default(): void
    {
        $this->assertStringNotContainsString('APP_NAME=Laravel', file_get_contents(base_path('.env.example')));
        $this->assertStringNotContainsString("'Laravel'", file_get_contents(base_path('config/mail.php')));
        $this->assertStringNotContainsString("'Laravel'", file_get_contents(base_path('config/app.php')));
    }

    public function test_docker_ignore_keeps_env_out_of_image(): void
    {
        $ignore = file_get_contents(base_path('.dockerignore'));

        $this->assertMatchesRegularExpression('/^\.env$/m', $ignore);
        $this->assertMatchesRegularExpression('/^\.git$/m', $ignore);
    }
}
