<?php

namespace Tests\Feature;

use App\Livewire\Landlord\Properties\Create as LandlordPropertyCreate;
use App\Livewire\Landlord\Properties\Edit as LandlordPropertyEdit;
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

class LandlordPropertyWorkflowClarityTest extends TestCase
{
    use RefreshDatabase;

    public function test_landlord_property_create_page_surfaces_clear_workflow_and_upload_guidance(): void
    {
        $landlord = $this->createLandlord();

        $response = $this->actingAs($landlord)->get(route('landlord.properties.create'));

        $response->assertOk();
        $response->assertSee('Listing basics');
        $response->assertSee('Pricing and layout');
        $response->assertSee('Location details');
        $response->assertSee('Media and review uploads');
        $response->assertSee('What happens next');
        $response->assertSee('Rent amount');
        $response->assertSee('Property images');
        $response->assertSee('Property documents');
        $response->assertSee('This listing will read as a rental listing');
        $response->assertSee('Unit inventory foundation');
        $response->assertSee('Units only reduce after successful completed rent payments, not inspection requests, saves, favorites, or early checkout steps.');
    }

    public function test_listing_intent_context_updates_primary_amount_label_for_sale_and_lease(): void
    {
        $landlord = $this->createLandlord();

        $this->actingAs($landlord);

        Livewire::test(LandlordPropertyCreate::class)
            ->set('listingIntent', 'for_sale')
            ->assertSet('primaryAmountLabel', 'Sale price')
            ->assertSee('Sale price')
            ->assertSee('sale listing')
            ->set('listingIntent', 'for_lease')
            ->assertSet('primaryAmountLabel', 'Lease amount')
            ->assertSee('Lease amount')
            ->assertSee('lease listing')
            ->set('propertyType', 'shop')
            ->assertSet('supportsMultiUnitInventory', true)
            ->assertSee('Total available units at listing start')
            ->assertSee('inspection requests, saves, and early checkout steps do not.')
            ->set('propertyType', 'bungalow')
            ->assertSet('supportsMultiUnitInventory', false)
            ->assertSee('Single-home listings usually stay at 1 unit.');
    }

    public function test_land_listings_surface_land_size_fields_and_hide_room_counts(): void
    {
        $landlord = $this->createLandlord();

        $this->actingAs($landlord);

        Livewire::test(LandlordPropertyCreate::class)
            ->set('propertyType', 'land')
            ->assertSet('showsBedroomField', false)
            ->assertSet('showsBathroomField', false)
            ->assertSet('showsToiletField', false)
            ->assertSet('showsLandSizeFields', true)
            ->assertSee('Land size')
            ->assertSee('Land size unit')
            ->assertSee('Total plots available');
    }

    public function test_land_listing_can_be_created_with_land_size_fields(): void
    {
        $landlord = $this->createLandlord();

        $this->actingAs($landlord);

        $this->completeListingTermsGate('listing-terms:create');

        Livewire::test(LandlordPropertyCreate::class)
            ->set('title', 'Igbatoro Land Parcel')
            ->set('listingIntent', 'for_sale')
            ->set('propertyType', 'land')
            ->set('landSize', '650')
            ->set('landSizeUnit', 'sqm')
            ->set('rentAmount', '18500000')
            ->set('city', 'Akure')
            ->set('state', 'Ondo')
            ->set('lga', 'Akure South')
            ->set('area', 'Igbatoro')
            ->set('hasAcceptedListingTerms', true)
            ->call('save')
            ->assertRedirect(route('landlord.properties'));

        $property = Property::query()->where('title', 'Igbatoro Land Parcel')->sole();

        $this->assertSame('land', $property->property_type);
        $this->assertSame('for_sale', $property->listing_intent);
        $this->assertSame('650.00', (string) $property->land_size);
        $this->assertSame('sqm', $property->land_size_unit);
        $this->assertNull($property->bedrooms);
        $this->assertNull($property->bathrooms);
        $this->assertNull($property->toilets);
    }

    public function test_landlord_property_edit_page_surfaces_readiness_summary_visibility_and_request_activity(): void
    {
        $landlord = $this->createLandlord();
        $property = $this->createProperty($landlord, [
            'title' => 'Readiness Listing',
            'status' => 'pending_review',
            'is_verified' => false,
            'is_published' => false,
        ]);

        $this->createInspectionRequest($property, [
            'status' => 'requested',
        ]);

        $response = $this->actingAs($landlord)->get(route('landlord.properties.edit', $property));

        $response->assertOk();
        $response->assertSee('Use this page as the main landlord detail and readiness surface for the listing.');
        $response->assertSee('Current state');
        $response->assertSee('Visibility');
        $response->assertSee('Request activity');
        $response->assertSee('Readiness summary');
        $response->assertSee('Not publicly visible yet.');
        $response->assertSee('1 open request');
        $response->assertSee('Unit inventory');
        $response->assertSee('1 available of 1 total unit with 0 occupied.');
        $response->assertSee('This listing is waiting for review. Tighten any missing details or uploads before the next review pass.');
        $response->assertSee('Add at least one image so the listing has a cover photo.');
        $response->assertSee('Add at least one supporting document so the review file is not empty.');
    }

    public function test_multi_unit_property_types_can_store_total_unit_count_and_derive_availability_safely(): void
    {
        $landlord = $this->createLandlord();
        $property = $this->createProperty($landlord, [
            'title' => 'Shop Block Listing',
            'property_type' => 'shop',
            'total_units' => 6,
            'occupied_units' => 2,
        ]);

        $this->assertSame(4, $property->available_units);

        $this->actingAs($landlord);

        $this->completeListingTermsGate('listing-terms:property:'.$property->getKey());

        Livewire::test(LandlordPropertyEdit::class, ['property' => $property])
            ->set('propertyType', 'shop')
            ->set('totalUnits', '8')
            ->set('hasAcceptedListingTerms', true)
            ->call('save')
            ->assertRedirect(route('landlord.properties'));

        $property->refresh();

        $this->assertSame(8, $property->total_units);
        $this->assertSame(2, $property->occupied_units);
        $this->assertSame(6, $property->available_units);
    }

    public function test_single_unit_property_types_default_sensibly_and_inspection_request_creation_does_not_reduce_units(): void
    {
        $landlord = $this->createLandlord();

        $this->actingAs($landlord);

        $this->completeListingTermsGate('listing-terms:create');

        Livewire::test(LandlordPropertyCreate::class)
            ->set('title', 'Single Unit Bungalow')
            ->set('listingIntent', 'for_sale')
            ->set('propertyType', 'bungalow')
            ->set('rentAmount', '18000000')
            ->set('city', 'Akure')
            ->set('state', 'Ondo')
            ->set('lga', 'Akure North')
            ->set('area', 'Oba Ile')
            ->set('hasAcceptedListingTerms', true)
            ->call('save')
            ->assertRedirect(route('landlord.properties'));

        $property = Property::query()->where('title', 'Single Unit Bungalow')->sole();

        $this->assertSame(1, $property->total_units);
        $this->assertSame(0, $property->occupied_units);
        $this->assertSame(1, $property->available_units);
        $this->assertSame('for_sale', $property->listing_intent);
        $this->assertSame('bungalow', $property->property_type);

        $this->createInspectionRequest($property, [
            'status' => 'requested',
        ]);

        $property->refresh();

        $this->assertSame(1, $property->total_units);
        $this->assertSame(0, $property->occupied_units);
        $this->assertSame(1, $property->available_units);
    }

    protected function createLandlord(?string $email = null): User
    {
        Role::findOrCreate('landlord', 'web');

        $landlord = User::factory()->create([
            'email' => $email ?? 'property-workflow-landlord@example.com',
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

    protected function createProperty(User $landlord, array $overrides = []): Property
    {
        return Property::create(array_merge([
            'landlord_id' => $landlord->id,
            'title' => 'Workflow Listing',
            'property_type' => 'flat',
            'listing_intent' => 'for_rent',
            'rent_amount' => 750000,
            'total_units' => 1,
            'occupied_units' => 0,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
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

    protected function completeListingTermsGate(string $gate): void
    {
        $service = app(\App\Support\TermsGateService::class);

        $service->open($gate);
        $this->travel(11)->seconds();
        $service->complete($gate);
        $this->travelBack();
    }
}
