<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_customer_even_if_role_is_sent(): void
    {
        $this->post('/register', [
            'name' => 'Pembeli Baru',
            'email' => 'baru@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticated();
        $this->assertSame(User::ROLE_CUSTOMER, User::where('email', 'baru@example.com')->value('role'));
    }

    public function test_login_redirects_admin_to_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_guest_is_redirected_from_admin(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_admin(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_admin_can_access_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
    }
}
