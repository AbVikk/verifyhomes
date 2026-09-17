<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupportTeamAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'tenant', 'landlord', 'support_staff'] as $role) Role::findOrCreate($role, 'web');
    }

    public function test_admin_can_create_support_staff_with_one_time_activation_credentials(): void
    {
        $admin = $this->user('admin');
        $response = $this->actingAs($admin)->post(route('admin.support-team.store'), ['name' => 'Support One', 'email' => 'support@example.test', 'phone' => '08000000000']);
        $response->assertRedirect(route('admin.support-team.index'))->assertSessionHas('support_team_credentials');
        $member = User::where('email', 'support@example.test')->firstOrFail();
        $credentials = $response->getSession()->get('support_team_credentials');
        $this->assertTrue($member->hasRole('support_staff'));
        $this->assertTrue($member->must_change_password);
        $this->assertTrue(Hash::check($credentials['password'], $member->password));
        $this->assertNotSame($credentials['activation_url'], $member->activation_token_hash);
        $this->assertNotNull($member->activation_expires_at);
        $this->actingAs($admin)->get(route('admin.support-team.index'))->assertOk()->assertSee('Support One');
    }

    public function test_activation_is_single_use_and_forces_a_new_password(): void
    {
        $admin = $this->user('admin');
        $response = $this->actingAs($admin)->post(route('admin.support-team.store'), ['name' => 'Support One', 'email' => 'support@example.test']);
        $credentials = $response->getSession()->get('support_team_credentials');
        $this->app['auth']->forgetGuards();
        $this->get($credentials['activation_url'])->assertOk()->assertSee('Welcome to VerifyHomes Support');
        $this->post($credentials['activation_url'], ['temporary_password' => 'wrong'])->assertSessionHasErrors('temporary_password');
        $this->post($credentials['activation_url'], ['temporary_password' => $credentials['password']])->assertRedirect(route('support-team.password.create'));
        $this->post(route('support-team.password.update'), ['password' => 'New-password-123!', 'password_confirmation' => 'New-password-123!'])->assertRedirect(route('support-team.dashboard'));
        $member = User::where('email', 'support@example.test')->firstOrFail();
        $this->assertFalse($member->must_change_password);
        $this->assertNull($member->activation_token_hash);
        $this->post(route('logout'));
        $this->get($credentials['activation_url'])->assertNotFound();
    }

    public function test_suspension_reissue_and_access_boundaries_are_enforced(): void
    {
        $admin = $this->user('admin');
        $member = $this->user('support_staff', ['must_change_password' => false]);
        $this->actingAs($member)->get(route('support-team.dashboard'))->assertOk();
        $this->actingAs($member)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.support-team.suspend', $member));
        $this->app['auth']->forgetGuards();
        $this->post(route('support-team.login.store'), ['email' => $member->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->actingAs($admin)->post(route('admin.support-team.reactivate', $member));
        $this->app['auth']->forgetGuards();
        $this->post(route('support-team.login.store'), ['email' => $member->email, 'password' => 'password'])->assertRedirect(route('support-team.dashboard'));
        $this->assertNotNull($member->fresh()->last_login_at);
    }

    public function test_non_admin_cannot_manage_support_staff_and_old_activation_links_are_invalidated_on_reissue(): void
    {
        $tenant = $this->user('tenant');
        $this->actingAs($tenant)->get(route('admin.support-team.index'))->assertForbidden();
        $admin = $this->user('admin');
        $first = $this->actingAs($admin)->post(route('admin.support-team.store'), ['name' => 'Support One', 'email' => 'support@example.test']);
        $oldUrl = $first->getSession()->get('support_team_credentials.activation_url');
        $member = User::where('email', 'support@example.test')->firstOrFail();
        $second = $this->actingAs($admin)->post(route('admin.support-team.reissue', $member));
        $second->assertSessionHas('support_team_credentials');
        $this->app['auth']->forgetGuards();
        $this->get($oldUrl)->assertNotFound();
    }

    private function user(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['email_verified_at' => now()], $attributes));
        $user->assignRole($role);
        return $user;
    }
}
