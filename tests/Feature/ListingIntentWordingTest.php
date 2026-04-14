<?php

namespace Tests\Feature;

use App\Livewire\PublicProperties\Index as PublicPropertiesIndex;
use App\Models\LandlordProfile;
use App\Models\Property;
use App\Models\TenantProfile;
use App\Models\User;
use App\Support\PublicPropertyVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ListingIntentWordingTest extends TestCase
{
    use RefreshDatabase;

    public function test_browse_filter_wording_tracks_selected_listing_intent(): void
    {
        $landlord = $this->createLandlord();

        $this->createProperty($landlord, [
            'title' => 'Rental Listing',
            'listing_intent' => 'for_rent',
            'rent_amount' => 700000,
        ]);

        $this->createProperty($landlord, [
            'title' => 'Sale Listing',
            'listing_intent' => 'for_sale',
            'rent_amount' => 18000000,
        ]);

        $this->createProperty($landlord, [
            'title' => 'Lease Listing',
            'listing_intent' => 'for_lease',
            'rent_amount' => 2500000,
        ]);

        Livewire::test(PublicPropertiesIndex::class)
            ->assertSee('Min price')
            ->assertSee('Max price')
            ->assertSee('Discover approved, verified listings and use the listing-intent tabs to narrow the results quickly.')
            ->assertSee('Rent amount')
            ->assertSee('Sale price')
            ->assertSee('Lease amount')
            ->call('setListingIntent', 'for_sale')
            ->assertSet('listingIntent', 'for_sale')
            ->assertSee('Min sale price')
            ->assertSee('Max sale price')
            ->assertSee('Discover approved, verified sale listings that are ready for property review and inspection requests.')
            ->call('setListingIntent', 'for_lease')
            ->assertSet('listingIntent', 'for_lease')
            ->assertSee('Min lease amount')
            ->assertSee('Max lease amount')
            ->assertSee('For Lease stays visible because the current listing-intent values already support lease listings in the workspace.')
            ->call('setListingIntent', 'for_rent')
            ->assertSet('listingIntent', 'for_rent')
            ->assertSee('Min rent')
            ->assertSee('Max rent')
            ->assertSee('Discover approved, verified rental listings that are ready for tenant inspection requests.');
    }

    public function test_property_detail_surfaces_use_listing_intent_aware_price_labels(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->createAdmin();
        $landlord = $this->createLandlord('intent-owner@example.com');

        $saleProperty = $this->createProperty($landlord, [
            'title' => 'Sale Detail Listing',
            'listing_intent' => 'for_sale',
            'rent_amount' => 15000000,
        ]);

        $leaseProperty = $this->createProperty($landlord, [
            'title' => 'Lease Detail Listing',
            'listing_intent' => 'for_lease',
            'rent_amount' => 2200000,
        ]);

        $publicResponse = $this->get(route('properties.show', $saleProperty));

        $publicResponse->assertOk();
        $publicResponse->assertSee('For Sale');
        $publicResponse->assertSee('Sale price');
        $publicResponse->assertSee(html_entity_decode('&#8358;').'15,000,000.00');

        $tenantResponse = $this->actingAs($tenant)->get(route('properties.show', $leaseProperty));

        $tenantResponse->assertOk();
        $tenantResponse->assertSee('For Lease');
        $tenantResponse->assertSee('Lease amount');
        $tenantResponse->assertSee(html_entity_decode('&#8358;').'2,200,000.00');

        $adminResponse = $this->actingAs($admin)->get(route('admin.properties.show', $saleProperty));

        $adminResponse->assertOk();
        $adminResponse->assertSee('Listing intent');
        $adminResponse->assertSee('For Sale');
        $adminResponse->assertSee('Sale price');
        $adminResponse->assertSee(html_entity_decode('&#8358;').'15,000,000.00');
    }

    public function test_tenant_saved_listings_show_listing_intent_aware_price_labels(): void
    {
        $tenant = $this->createTenant();
        $landlord = $this->createLandlord('saved-intent-owner@example.com');
        $property = $this->createProperty($landlord, [
            'title' => 'Saved Sale Listing',
            'listing_intent' => 'for_sale',
            'rent_amount' => 12500000,
        ]);

        $tenant->savedProperties()->syncWithoutDetaching([$property->id]);

        $response = $this->actingAs($tenant)->get(route('tenant.saved-listings.index'));

        $response->assertOk();
        $response->assertSee('For Sale');
        $response->assertSee('Sale price');
        $response->assertSee(html_entity_decode('&#8358;').'12,500,000.00');
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

    protected function createLandlord(?string $email = null): User
    {
        Role::findOrCreate('landlord', 'web');

        $landlord = User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
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

    protected function createProperty(User $landlord, array $overrides = []): Property
    {
        return Property::create(array_merge([
            'landlord_id' => $landlord->id,
            'title' => 'Listing Intent Property',
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
            'description' => 'Listing intent wording test property.',
            'status' => PublicPropertyVisibility::APPROVED_STATUS,
            'is_verified' => true,
            'is_published' => true,
        ], $overrides));
    }
}

