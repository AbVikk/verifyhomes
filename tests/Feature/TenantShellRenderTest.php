<?php

namespace Tests\Feature;

use App\Models\InspectionRequest;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\TenantProfile;
use App\Models\User;
use App\Support\PaymentTransactionRecorder;
use App\Support\PublicPropertyVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantShellRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_shell_pages_render_without_livewire_page_view_type_errors(): void
    {
        $tenant = $this->createTenant();
        $inspectionRequest = $this->createInspectionRequest($tenant);
        $paymentTransaction = PaymentTransactionRecorder::createPending([
            'payer_id' => $tenant->id,
            'property_id' => $inspectionRequest->property_id,
            'inspection_request_id' => $inspectionRequest->id,
            'transaction_type' => 'inspection_booking_fee',
            'gross_amount' => 5000,
            'status' => 'initiated',
            'provider' => 'stub',
        ]);

        $dashboardResponse = $this->actingAs($tenant)->get(route('tenant.dashboard'));
        $dashboardResponse->assertOk()
            ->assertSee('data-admin-shell-key="tenant"', false)
            ->assertSee('href="'.route('tenant.profile').'"', false)
            ->assertSee('href="'.route('tenant.payments.index').'"', false);

        $this->actingAs($tenant)->get(route('tenant.profile'))
            ->assertOk()
            ->assertSee('data-admin-shell-key="tenant"', false)
            ->assertSee('Account and workspace details')
            ->assertSee('Profile Information')
            ->assertSee('Update Password')
            ->assertSee('Delete Account');

        $this->actingAs($tenant)->get(route('tenant.payments.index'))
            ->assertOk()
            ->assertSee('data-admin-shell-key="tenant"', false)
            ->assertSee($paymentTransaction->reference);

        $this->actingAs($tenant)->get(route('tenant.inspection-requests.index'))
            ->assertOk()
            ->assertSee('data-admin-shell-key="tenant"', false);

        $this->actingAs($tenant)->get(route('tenant.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()]))
            ->assertOk()
            ->assertSee('data-admin-shell-key="tenant"', false);

        $this->actingAs($tenant)->get(route('tenant.occupancy.index'))
            ->assertOk()
            ->assertSee('data-admin-shell-key="tenant"', false);
    }

    public function test_shared_profile_route_redirects_tenant_to_tenant_shell_profile(): void
    {
        $tenant = $this->createTenant();

        $this->actingAs($tenant)
            ->get(route('profile.edit'))
            ->assertRedirect(route('tenant.profile'));
    }

    public function test_tenant_can_update_account_information_from_shell_profile(): void
    {
        $tenant = $this->createTenant();

        $response = $this->actingAs($tenant)->patch(route('profile.update'), [
            'name' => 'Tenant Account Updated',
            'email' => 'tenant.updated@example.com',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('tenant.profile'));

        $tenant->refresh();

        $this->assertSame('Tenant Account Updated', $tenant->name);
        $this->assertSame('tenant.updated@example.com', $tenant->email);
        $this->assertNull($tenant->email_verified_at);
    }

    public function test_tenant_can_update_password_from_shell_profile(): void
    {
        $tenant = $this->createTenant();

        $response = $this->actingAs($tenant)
            ->from(route('tenant.profile'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'new-tenant-password',
                'password_confirmation' => 'new-tenant-password',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('tenant.profile'));

        $this->assertTrue(Hash::check('new-tenant-password', $tenant->refresh()->password));
    }

    public function test_tenant_navigation_profile_links_use_the_tenant_shell_profile_route(): void
    {
        $tenant = $this->createTenant();

        $response = $this->actingAs($tenant)->get(route('properties.index'));

        $response->assertOk();
        $response->assertSee('href="'.route('tenant.profile').'"', false);
        $response->assertDontSee('href="'.route('profile.edit').'"', false);
    }

    protected function createTenant(): User
    {
        Role::findOrCreate('tenant', 'web');

        $tenant = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $tenant->assignRole('tenant');

        TenantProfile::create([
            'user_id' => $tenant->id,
        ]);

        return $tenant;
    }

    protected function createLandlord(): User
    {
        Role::findOrCreate('landlord', 'web');

        $landlord = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $landlord->assignRole('landlord');

        return $landlord;
    }

    protected function createProperty(User $landlord): Property
    {
        return Property::create([
            'landlord_id' => $landlord->id,
            'title' => 'Tenant Shell Render Property',
            'property_type' => 'flat',
            'listing_intent' => 'for_rent',
            'rent_amount' => 650000,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'status' => PublicPropertyVisibility::APPROVED_STATUS,
            'is_verified' => true,
            'is_published' => true,
        ]);
    }

    protected function createInspectionRequest(User $tenant): InspectionRequest
    {
        $property = $this->createProperty($this->createLandlord());

        $inspectionRequest = InspectionRequest::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'requested',
            'preferred_date' => now()->addDays(2)->toDateString(),
            'preferred_time_note' => 'Afternoon works best',
            'message' => 'Please confirm access.',
        ]);

        $inspectionRequest->statusHistories()->create([
            'from_status' => null,
            'to_status' => 'requested',
            'changed_by' => null,
            'notes' => null,
        ]);

        return $inspectionRequest;
    }
}
