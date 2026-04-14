<?php

namespace Tests\Feature;

use App\Models\LandlordProfile;
use App\Models\TenantProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OnboardingChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_dashboard_renders_onboarding_checklist(): void
    {
        $tenant = $this->createRoleUser('tenant');

        TenantProfile::create([
            'user_id' => $tenant->id,
        ]);

        $response = $this->actingAs($tenant)->get(route('tenant.dashboard'));

        $response->assertOk();
        $response->assertSee('Tenant onboarding checklist');
        $response->assertSee('Complete profile');
    }

    public function test_landlord_dashboard_renders_onboarding_checklist(): void
    {
        $landlord = $this->createRoleUser('landlord');

        LandlordProfile::create([
            'user_id' => $landlord->id,
            'verification_status' => 'pending',
            'city' => 'Akure',
            'state' => 'Ondo',
        ]);

        $response = $this->actingAs($landlord)->get(route('landlord.dashboard'));

        $response->assertOk();
        $response->assertSee('Landlord onboarding checklist');
        $response->assertSee('Complete profile');
    }

    public function test_admin_dashboard_renders_onboarding_checklist(): void
    {
        $admin = $this->createRoleUser('admin');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Admin onboarding checklist');
        $response->assertSee('Review landlord documents');
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
