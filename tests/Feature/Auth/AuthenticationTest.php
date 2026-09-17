<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_support_origin_login_redirects_tenants_and_landlords_to_their_own_support_workspaces(): void
    {
        $tenant = $this->roleUser('tenant');
        $landlord = $this->roleUser('landlord');

        $this->get(route('login', ['support' => 1]));
        $this->post('/login', ['email' => $tenant->email, 'password' => 'password'])
            ->assertRedirect(route('tenant.support.index'));

        $this->post('/logout');
        $this->get(route('login', ['support' => 1, 'redirect' => 'https://example.com']));
        $response = $this->post('/login', ['email' => $landlord->email, 'password' => 'password']);
        $response->assertRedirect(route('landlord.support.index'));
        $this->assertNotSame('https://example.com', $response->headers->get('Location'));
    }

    public function test_normal_role_login_keeps_the_existing_dashboard_redirect(): void
    {
        $tenant = $this->roleUser('tenant');

        $this->post('/login', ['email' => $tenant->email, 'password' => 'password'])
            ->assertRedirect(route('tenant.dashboard'));
    }

    private function roleUser(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }
}
