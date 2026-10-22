<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_from_admin_pages(): void
    {
        foreach (['/dashboard', '/customers', '/reservations', '/menus', '/business-hours'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }

    public function test_logged_in_user_can_open_admin_pages(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['/dashboard', '/customers', '/reservations', '/menus', '/business-hours'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_booking_page_is_public(): void
    {
        $this->get('/book')->assertOk();
    }

    public function test_mobile_menu_has_all_admin_pages(): void
    {
        $this->actingAs(\App\Models\User::factory()->create());

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $mobile = substr($html, strpos($html, '<!-- Responsive Navigation Menu -->'));

        foreach (['customers.index', 'reservations.index', 'schedule.index', 'menus.index', 'business-hours.edit', 'ai-guidelines.edit', 'ai-sessions.index'] as $name) {
            $this->assertStringContainsString('href="' . route($name) . '"', $mobile, "スマホ用メニューに {$name} がない");
        }
    }
}
