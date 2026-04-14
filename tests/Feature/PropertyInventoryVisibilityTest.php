<?php

namespace Tests\Feature;

use App\Livewire\Admin\Properties\Show as AdminPropertyShow;
use App\Models\AuditLog;
use App\Models\InspectionRequest;
use App\Models\LandlordProfile;
use App\Models\Property;
use App\Models\TenantProfile;
use App\Models\User;
use App\Support\PublicPropertyVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PropertyInventoryVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_property_surfaces_show_unit_inventory_clearly(): void
    {
        $admin = $this->createAdmin();
        $property = $this->createProperty([
            'title' => 'Inventory Review Listing',
            'property_type' => 'shop',
            'total_units' => 6,
            'occupied_units' => 2,
        ]);

        $indexResponse = $this->actingAs($admin)->get(route('admin.properties.index'));

        $indexResponse->assertOk();
        $indexResponse->assertSee('Inventory');
        $indexResponse->assertSee('4 available / 6 total');
        $indexResponse->assertSee('2 occupied');

        $showResponse = $this->actingAs($admin)->get(route('admin.properties.show', $property));

        $showResponse->assertOk();
        $showResponse->assertSee('Availability');
        $showResponse->assertSee('Total units');
        $showResponse->assertSee('Occupied units');
        $showResponse->assertSee('Available units');
        $showResponse->assertSee('Occupancy adjustment');
        $showResponse->assertSee('Inspection requests, saves, and early payments do not change inventory automatically.');
    }

    public function test_public_and_tenant_listing_surfaces_show_availability_messages(): void
    {
        $tenant = $this->createTenant();
        $availableProperty = $this->createProperty([
            'title' => 'Available Block',
            'total_units' => 5,
            'occupied_units' => 1,
        ]);
        $fullProperty = $this->createProperty([
            'title' => 'Full Block',
            'total_units' => 1,
            'occupied_units' => 1,
        ]);

        $publicBrowseResponse = $this->get(route('properties.index'));
        $publicBrowseResponse->assertOk();
        $publicBrowseResponse->assertSee('4 units available');
        $publicBrowseResponse->assertSee('Fully occupied');

        $tenantBrowseResponse = $this->actingAs($tenant)->get(route('properties.index'));
        $tenantBrowseResponse->assertOk();
        $tenantBrowseResponse->assertSee('4 units available');
        $tenantBrowseResponse->assertSee('Fully occupied');

        $tenantDetailResponse = $this->actingAs($tenant)->get(route('properties.show', $availableProperty));
        $tenantDetailResponse->assertOk();
        $tenantDetailResponse->assertSee('Availability');
        $tenantDetailResponse->assertSee('4 units available');
        $tenantDetailResponse->assertSee('4 available of 5 total units');
        $tenantDetailResponse->assertDontSee('Caution fee');
        $tenantDetailResponse->assertDontSee('Service charge');

        $publicDetailResponse = $this->get(route('properties.show', $fullProperty));
        $publicDetailResponse->assertOk();
        $publicDetailResponse->assertSee('Fully occupied');
        $publicDetailResponse->assertSee('0 available of 1 total unit');
        $publicDetailResponse->assertDontSee('Caution fee');
        $publicDetailResponse->assertDontSee('Service charge');
    }

    public function test_tenant_saved_listings_show_availability_badges(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createProperty([
            'title' => 'Saved Inventory Listing',
            'total_units' => 2,
            'occupied_units' => 1,
        ]);

        $tenant->savedProperties()->syncWithoutDetaching([$property->id]);

        $response = $this->actingAs($tenant)->get(route('tenant.saved-listings.index'));

        $response->assertOk();
        $response->assertSee('1 unit left');
    }

    public function test_tenant_and_public_property_images_are_clickable_and_processing_copy_is_visible(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $tenant = $this->createTenant();
        $property = $this->createProperty();

        \Illuminate\Support\Facades\Storage::disk('public')->put("property-images/{$property->id}/detail-view.jpg", 'image-bytes');

        \App\Models\PropertyImage::create([
            'property_id' => $property->id,
            'image_path' => "property-images/{$property->id}/detail-view.jpg",
            'sort_order' => 0,
            'is_cover' => true,
        ]);

        $tenantResponse = $this->actingAs($tenant)->get(route('properties.show', $property));
        $tenantResponse->assertOk();
        $tenantResponse->assertSee('target="_blank"', false);
        $tenantResponse->assertSee('Submitting...');
        $tenantResponse->assertSee('Processing...');

        $publicResponse = $this->get(route('properties.show', $property));
        $publicResponse->assertOk();
        $publicResponse->assertSee('target="_blank"', false);
        $publicResponse->assertSee('Submitting...');
    }

    public function test_shared_button_styles_keep_distinct_variants(): void
    {
        $adminCss = file_get_contents(resource_path('css/admin.css'));
        $appCss = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('.admin-button-primary', $adminCss);
        $this->assertStringContainsString('.admin-button-success', $adminCss);
        $this->assertStringContainsString('.admin-button-warning', $adminCss);
        $this->assertStringContainsString('.admin-button-danger', $adminCss);
        $this->assertStringContainsString('.vh-button-primary', $appCss);
        $this->assertStringContainsString('.vh-button-secondary', $appCss);
    }

    public function test_admin_can_adjust_occupied_units_manually_and_safely(): void
    {
        $admin = $this->createAdmin();
        $property = $this->createProperty([
            'title' => 'Manual Occupancy Listing',
            'total_units' => 5,
            'occupied_units' => 1,
        ]);

        $this->actingAs($admin);

        Livewire::test(AdminPropertyShow::class, ['property' => $property])
            ->set('occupiedUnits', '3')
            ->call('updateOccupancy')
            ->assertHasNoErrors();

        $property->refresh();

        $this->assertSame(3, $property->occupied_units);
        $this->assertSame(2, $property->available_units);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'property_occupancy_updated',
            'target_type' => Property::class,
            'target_id' => $property->id,
        ]);

        /** @var AuditLog $auditLog */
        $auditLog = AuditLog::query()->where('action', 'property_occupancy_updated')->latest('id')->firstOrFail();

        $this->assertSame(1, $auditLog->metadata['from_occupied_units']);
        $this->assertSame(3, $auditLog->metadata['to_occupied_units']);
        $this->assertSame(2, $auditLog->metadata['available_units']);
    }

    public function test_admin_cannot_set_occupied_units_above_total_units(): void
    {
        $admin = $this->createAdmin();
        $property = $this->createProperty([
            'title' => 'Unsafe Occupancy Listing',
            'total_units' => 2,
            'occupied_units' => 0,
        ]);

        $this->actingAs($admin);

        Livewire::test(AdminPropertyShow::class, ['property' => $property])
            ->set('occupiedUnits', '3')
            ->call('updateOccupancy')
            ->assertHasErrors(['occupiedUnits']);

        $property->refresh();

        $this->assertSame(0, $property->occupied_units);
        $this->assertSame(2, $property->available_units);
    }

    public function test_inspection_request_creation_still_does_not_reduce_units_automatically(): void
    {
        $property = $this->createProperty([
            'title' => 'No Auto Reduction Listing',
            'total_units' => 4,
            'occupied_units' => 1,
        ]);

        $this->createInspectionRequest($property, [
            'status' => 'requested',
        ]);

        $property->refresh();

        $this->assertSame(4, $property->total_units);
        $this->assertSame(1, $property->occupied_units);
        $this->assertSame(3, $property->available_units);
    }

    protected function createAdmin(): User
    {
        Role::findOrCreate('admin', 'web');

        $admin = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $admin->assignRole('admin');

        return $admin;
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

        LandlordProfile::create([
            'user_id' => $landlord->id,
            'verification_status' => 'approved',
            'city' => 'Akure',
            'state' => 'Ondo',
        ]);

        return $landlord;
    }

    protected function createProperty(array $overrides = []): Property
    {
        return Property::create(array_merge([
            'landlord_id' => $this->createLandlord()->id,
            'title' => 'Inventory Visibility Property',
            'property_type' => 'flat',
            'listing_intent' => 'for_rent',
            'rent_amount' => 850000,
            'total_units' => 1,
            'occupied_units' => 0,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'landmark' => 'Near Shoprite',
            'description' => 'Inventory visibility test property.',
            'status' => PublicPropertyVisibility::APPROVED_STATUS,
            'is_verified' => true,
            'is_published' => true,
        ], $overrides));
    }

    protected function createInspectionRequest(Property $property, array $overrides = []): InspectionRequest
    {
        $tenant = $this->createTenant();

        $inspectionRequest = InspectionRequest::create(array_merge([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'requested',
            'preferred_date' => now()->addDays(2)->toDateString(),
            'preferred_time_note' => 'Afternoon works best',
            'message' => 'Please confirm access.',
        ], $overrides));

        $inspectionRequest->statusHistories()->create([
            'from_status' => null,
            'to_status' => $inspectionRequest->status,
            'changed_by' => null,
            'notes' => null,
        ]);

        return $inspectionRequest;
    }
}
