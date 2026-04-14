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

class BrowsePropertiesTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_browse_properties_tabs_filter_all_supported_listing_intents(): void
    {
        $landlord = $this->createLandlord();

        $rentProperty = $this->createProperty($landlord, [
            'title' => 'Rent Listing',
            'listing_intent' => 'for_rent',
        ]);

        $saleProperty = $this->createProperty($landlord, [
            'title' => 'Sale Listing',
            'listing_intent' => 'for_sale',
        ]);

        $leaseProperty = $this->createProperty($landlord, [
            'title' => 'Lease Listing',
            'listing_intent' => 'for_lease',
        ]);

        Livewire::test(PublicPropertiesIndex::class)
            ->assertSee('All')
            ->assertSee('For Rent')
            ->assertSee('For Sale')
            ->assertSee('For Lease')
            ->assertSee($rentProperty->title)
            ->assertSee($saleProperty->title)
            ->assertSee($leaseProperty->title)
            ->call('setListingIntent', 'for_rent')
            ->assertSet('listingIntent', 'for_rent')
            ->assertSee($rentProperty->title)
            ->assertDontSee($saleProperty->title)
            ->assertDontSee($leaseProperty->title)
            ->call('setListingIntent', 'for_sale')
            ->assertSet('listingIntent', 'for_sale')
            ->assertSee($saleProperty->title)
            ->assertDontSee($rentProperty->title)
            ->assertDontSee($leaseProperty->title)
            ->call('setListingIntent', 'for_lease')
            ->assertSet('listingIntent', 'for_lease')
            ->assertSee($leaseProperty->title)
            ->assertDontSee($rentProperty->title)
            ->assertDontSee($saleProperty->title)
            ->assertSee('For Lease stays visible because the current listing-intent values already support lease listings in the workspace.')
            ->call('setListingIntent', '')
            ->assertSet('listingIntent', '')
            ->assertSee($rentProperty->title)
            ->assertSee($saleProperty->title)
            ->assertSee($leaseProperty->title);
    }

    public function test_tenant_browse_properties_page_still_renders_listing_intent_tabs_inside_tenant_workspace(): void
    {
        $tenant = $this->createTenant();
        $landlord = $this->createLandlord('tabs-landlord@example.com');

        $this->createProperty($landlord, [
            'title' => 'Tenant Tabs Listing',
            'listing_intent' => 'for_rent',
        ]);

        $response = $this->actingAs($tenant)->get(route('properties.index'));

        $response->assertOk();
        $response->assertSee('Browse by listing intent');
        $response->assertSee('All');
        $response->assertSee('For Rent');
        $response->assertSee('For Sale');
        $response->assertSee('For Lease');
        $response->assertSee('Saved listings');
        $response->assertSee('Tenant Tabs Listing');
        $response->assertSee('Use the tabs as the main quick filter');
    }

    protected function createLandlord(?string $email = null): User
    {
        Role::findOrCreate('landlord', 'web');

        $landlord = User::factory()->create([
            'email' => $email ?? 'browse-landlord@example.com',
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
            'title' => 'Browse Listing',
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
        ], $overrides));
    }
}
