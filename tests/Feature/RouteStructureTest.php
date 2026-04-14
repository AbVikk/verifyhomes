<?php

namespace Tests\Feature;

use App\Models\TenantProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RouteStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_named_routes_still_exist_for_public_tenant_landlord_and_admin_flows(): void
    {
        $this->assertTrue(Route::has('home'));
        $this->assertTrue(Route::has('properties.index'));
        $this->assertTrue(Route::has('properties.show'));
        $this->assertTrue(Route::has('dashboard'));
        $this->assertTrue(Route::has('tenant.dashboard'));
        $this->assertTrue(Route::has('tenant.inspection-requests.index'));
        $this->assertTrue(Route::has('landlord.dashboard'));
        $this->assertTrue(Route::has('landlord.inspection-requests.index'));
        $this->assertTrue(Route::has('admin.dashboard'));
        $this->assertTrue(Route::has('admin.inspection-requests.index'));
    }

    public function test_public_routes_still_resolve_for_guests(): void
    {
        $this->get(route('home'))->assertOk();
        $this->get(route('properties.index'))->assertOk();
    }

    public function test_role_routes_still_redirect_guests_to_login(): void
    {
        $this->get(route('tenant.dashboard'))->assertRedirect(route('login'));
        $this->get(route('landlord.dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_dashboard_route_still_redirects_tenant_users_to_their_role_dashboard(): void
    {
        $tenant = $this->createTenant();

        $this->actingAs($tenant)
            ->get(route('dashboard'))
            ->assertRedirect(route('tenant.dashboard'));
    }

    public function test_dashboard_route_still_redirects_landlord_users_to_their_role_dashboard(): void
    {
        $landlord = $this->createRoleUser('landlord');

        $this->actingAs($landlord)
            ->get(route('dashboard'))
            ->assertRedirect(route('landlord.dashboard'));
    }

    public function test_dashboard_route_still_redirects_admin_users_to_their_role_dashboard(): void
    {
        $admin = $this->createRoleUser('admin');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard'));
    }

    protected function createTenant(): User
    {
        $tenant = $this->createRoleUser('tenant');

        TenantProfile::create([
            'user_id' => $tenant->id,
        ]);

        return $tenant;
    }

    protected function createRoleUser(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role);

        return $user;
    }
}
