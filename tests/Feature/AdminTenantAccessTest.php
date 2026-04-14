<?php

namespace Tests\Feature;

use App\Models\InspectionRequest;
use App\Models\Property;
use App\Models\TenantProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminTenantAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_tenant_index_and_detail(): void
    {
        $admin = $this->createReviewer('admin');
        $tenantProfile = $this->createTenantProfile();

        $this->actingAs($admin)->get(route('admin.tenants.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.tenants.show', ['tenantProfileId' => $tenantProfile->getKey()]))->assertOk();
    }

    public function test_staff_can_access_tenant_index_and_detail(): void
    {
        $staff = $this->createReviewer('staff');
        $tenantProfile = $this->createTenantProfile();

        $this->actingAs($staff)->get(route('admin.tenants.index'))->assertOk();
        $this->actingAs($staff)->get(route('admin.tenants.show', ['tenantProfileId' => $tenantProfile->getKey()]))->assertOk();
    }

    public function test_landlord_cannot_access_admin_tenant_pages(): void
    {
        $landlord = $this->createLandlord();
        $tenantProfile = $this->createTenantProfile();

        $this->actingAs($landlord)->get(route('admin.tenants.index'))->assertForbidden();
        $this->actingAs($landlord)->get(route('admin.tenants.show', ['tenantProfileId' => $tenantProfile->getKey()]))->assertForbidden();
    }

    public function test_tenant_index_renders_unavailable_state_when_tenant_profiles_table_is_missing(): void
    {
        $admin = $this->createReviewer('admin');

        Schema::dropIfExists('tenant_profiles');

        $response = $this->actingAs($admin)->get(route('admin.tenants.index'));

        $response->assertOk();
        $response->assertSee('Tenant directory');
        $response->assertSee('Tenant data is not available yet in this environment.');
    }

    public function test_tenant_detail_renders_unavailable_state_when_tenant_profiles_table_is_missing(): void
    {
        $admin = $this->createReviewer('admin');

        Schema::dropIfExists('tenant_profiles');

        $response = $this->actingAs($admin)->get(route('admin.tenants.show', ['tenantProfileId' => 1]));

        $response->assertOk();
        $response->assertSee('Tenant detail data is not available yet in this environment.');
    }

    public function test_tenant_pages_still_render_when_inspection_requests_table_is_missing(): void
    {
        $admin = $this->createReviewer('admin');
        $tenantProfile = $this->createTenantProfile();

        Schema::dropIfExists('inspection_request_status_histories');
        Schema::dropIfExists('inspection_requests');

        $indexResponse = $this->actingAs($admin)->get(route('admin.tenants.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee($tenantProfile->user->name);
        $indexResponse->assertSee('Inspection data unavailable');

        $detailResponse = $this->actingAs($admin)->get(route('admin.tenants.show', ['tenantProfileId' => $tenantProfile->getKey()]));
        $detailResponse->assertOk();
        $detailResponse->assertSee($tenantProfile->user->name);
        $detailResponse->assertSee('Inspection request data is not available yet in this environment.');
    }

    public function test_admin_tenant_index_can_search_by_name_and_email(): void
    {
        $admin = $this->createReviewer('admin');
        $matchingTenant = $this->createTenant('tenant-match@example.com');
        $matchingTenant->update([
            'name' => 'Tenant Match',
        ]);

        $otherTenant = $this->createTenant('other-tenant@example.com');
        $otherTenant->update([
            'name' => 'Other Tenant',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.tenants.index', [
            'search' => 'Tenant Match',
        ]));

        $response->assertOk();
        $response->assertSee('Tenant Match');
        $response->assertDontSee('Other Tenant');
    }

    protected function createReviewer(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role);

        return $user;
    }

    protected function createLandlord(?string $email = null): User
    {
        Role::findOrCreate('landlord', 'web');

        $landlord = User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
        ]);

        $landlord->assignRole('landlord');

        return $landlord;
    }

    protected function createTenant(?string $email = null): User
    {
        Role::findOrCreate('tenant', 'web');

        $tenant = User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
        ]);

        $tenant->assignRole('tenant');

        TenantProfile::create([
            'user_id' => $tenant->id,
            'phone' => '08012345678',
            'address' => 'Alagbaka, Akure',
            'occupation' => 'Designer',
            'gender' => 'Female',
        ]);

        return $tenant;
    }

    protected function createTenantProfile(?string $email = null): TenantProfile
    {
        return $this->createTenant($email)->tenantProfile;
    }

    protected function createInspectionRequestForTenant(User $tenant): InspectionRequest
    {
        $property = $this->createPublicProperty();

        return InspectionRequest::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'requested',
            'preferred_date' => now()->addDays(2)->toDateString(),
            'preferred_time_note' => 'Afternoon works best',
            'message' => 'Please confirm the exact time once available.',
        ]);
    }

    protected function createPublicProperty(): Property
    {
        $landlord = $this->createLandlord('tenant-admin-landlord@example.com');

        return Property::create([
            'landlord_id' => $landlord->id,
            'title' => 'Tenant Admin Listing',
            'property_type' => 'flat',
            'rent_amount' => 900000,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'landmark' => 'Near Shoprite',
            'description' => 'A verified apartment in a central area.',
            'status' => 'approved',
            'is_verified' => true,
            'is_published' => true,
        ]);
    }
}
