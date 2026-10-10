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
}
