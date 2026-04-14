<?php

namespace Tests\Feature;

use App\Models\InspectionRequest;
use App\Models\LandlordProfile;
use App\Models\Property;
use App\Models\TenantProfile;
use App\Models\User;
use App\Support\PublicPropertyVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LandlordShellRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_landlord_shell_pages_render_without_livewire_page_view_type_errors(): void
    {
        $landlord = $this->createLandlord();
        $property = $this->createProperty($landlord);
        $inspectionRequest = $this->createInspectionRequest($landlord, $property);

        $this->actingAs($landlord)->get(route('landlord.dashboard'))
            ->assertOk()
            ->assertSee('data-admin-shell-key="landlord"', false);

        $this->actingAs($landlord)->get(route('landlord.profile'))
            ->assertOk()
            ->assertSee('data-admin-shell-key="landlord"', false);

        $this->actingAs($landlord)->get(route('landlord.documents'))
            ->assertOk()
            ->assertSee('data-admin-shell-key="landlord"', false);

        $this->actingAs($landlord)->get(route('landlord.properties'))
            ->assertOk()
            ->assertSee('data-admin-shell-key="landlord"', false);

        $this->actingAs($landlord)->get(route('landlord.properties.create'))
            ->assertOk()
            ->assertSee('data-admin-shell-key="landlord"', false);

        $this->actingAs($landlord)->get(route('landlord.properties.edit', $property))
            ->assertOk()
            ->assertSee('data-admin-shell-key="landlord"', false);

        $this->actingAs($landlord)->get(route('landlord.inspection-requests.index'))
            ->assertOk()
            ->assertSee('data-admin-shell-key="landlord"', false);

        $this->actingAs($landlord)->get(route('landlord.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()]))
            ->assertOk()
            ->assertSee('data-admin-shell-key="landlord"', false);

        $this->actingAs($landlord)->get(route('landlord.occupancy.index'))
            ->assertOk()
            ->assertSee('data-admin-shell-key="landlord"', false);
    }

    protected function createLandlord(): User
    {
        Role::findOrCreate('landlord', 'web');

        $landlord = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $landlord->assignRole('landlord');

        LandlordProfile::create([
            'user_id' => $landlord->id,
            'verification_status' => 'approved',
            'city' => 'Akure',
            'state' => 'Ondo',
        ]);

        return $landlord;
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

    protected function createProperty(User $landlord): Property
    {
        return Property::create([
            'landlord_id' => $landlord->id,
            'title' => 'Landlord Shell Render Property',
            'property_type' => 'flat',
            'listing_intent' => 'for_rent',
            'rent_amount' => 750000,
            'caution_fee' => 100000,
            'service_charge' => 25000,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'status' => PublicPropertyVisibility::APPROVED_STATUS,
            'is_verified' => true,
            'is_published' => true,
        ]);
    }

    protected function createInspectionRequest(User $landlord, Property $property): InspectionRequest
    {
        $tenant = $this->createTenant();

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
