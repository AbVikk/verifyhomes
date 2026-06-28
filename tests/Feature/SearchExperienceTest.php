<?php

namespace Tests\Feature;

use App\Models\InspectionRequest;
use App\Models\LandlordProfile;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\TenantProfile;
use App\Models\User;
use App\Support\PublicPropertyVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SearchExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('staff', 'web');
        Role::findOrCreate('tenant', 'web');
        Role::findOrCreate('landlord', 'web');
    }

    public function test_admin_search_returns_matching_property(): void
    {
        $admin = $this->createRoleUser('admin');
        $landlord = $this->createLandlord();

        $property = Property::create([
            'landlord_id' => $landlord->id,
            'title' => 'Searchable Listing',
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

        $response = $this->actingAs($admin)->get(route('admin.search', ['q' => 'Searchable']));

        $response->assertOk();
        $response->assertSee('Matching listings');
        $response->assertSee($property->title);
        $response->assertSee('href="'.route('admin.properties.show', $property).'"', false);
    }

    public function test_admin_search_returns_matching_tenant_profile(): void
    {
        $admin = $this->createRoleUser('admin');
        $tenant = $this->createTenant('Search Tenant', 'tenant-search@example.com');

        $response = $this->actingAs($admin)->get(route('admin.search', ['q' => 'Search Tenant']));

        $response->assertOk();
        $response->assertSee('Matching tenant profiles');
        $response->assertSee($tenant->name);
        $response->assertSee('href="'.route('admin.tenants.show', $tenant->tenantProfile).'"', false);
    }

    public function test_admin_search_returns_matching_landlord_profile(): void
    {
        $admin = $this->createRoleUser('admin');
        $landlord = $this->createLandlord('Search Landlord', 'landlord-search@example.com');

        $response = $this->actingAs($admin)->get(route('admin.search', ['q' => 'Search Landlord']));

        $response->assertOk();
        $response->assertSee('Matching landlord profiles');
        $response->assertSee($landlord->name);
        $response->assertSee('href="'.route('admin.landlords.show', $landlord->landlordProfile).'"', false);
    }

    public function test_admin_search_returns_matching_payment_reference(): void
    {
        $admin = $this->createRoleUser('admin');
        $landlord = $this->createLandlord();
        $tenant = $this->createTenant();

        $property = $this->createProperty($landlord, 'Payment Listing');

        $transaction = PaymentTransaction::create([
            'reference' => 'PAYMENT-REF-ALPHA',
            'payer_id' => $tenant->id,
            'property_id' => $property->id,
            'transaction_type' => 'rent_payment',
            'provider' => 'stub',
            'status' => 'initiated',
            'gross_amount' => 850000,
            'platform_fee_percentage' => 0,
            'platform_fee_amount' => 0,
            'net_amount' => 850000,
            'currency' => 'NGN',
        ]);

        PaymentTransaction::create([
            'reference' => 'PAYMENT-REF-BETA',
            'payer_id' => $tenant->id,
            'property_id' => $property->id,
            'transaction_type' => 'rent_payment',
            'provider' => 'stub',
            'status' => 'initiated',
            'gross_amount' => 850000,
            'platform_fee_percentage' => 0,
            'platform_fee_amount' => 0,
            'net_amount' => 850000,
            'currency' => 'NGN',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.search', ['q' => 'PAYMENT-REF']));

        $response->assertOk();
        $response->assertSee('Matching payment references');
        $response->assertSee($transaction->reference);
        $response->assertSeeInOrder([
            'PAYMENT-REF-ALPHA',
            'PAYMENT-REF-BETA',
        ]);
        $response->assertSee('href="'.route('admin.payments.index', ['reference' => $transaction->reference]).'"', false);
    }

    public function test_admin_search_can_find_payment_by_payer_name_and_property_title(): void
    {
        $admin = $this->createRoleUser('admin');
        $landlord = $this->createLandlord('Search Landlord Owner', 'owner-search@example.com');
        $tenant = $this->createTenant('Search Payer', 'payer-search@example.com');

        $property = $this->createProperty($landlord, 'Searchable Payment Property');

        $transaction = PaymentTransaction::create([
            'reference' => 'SEARCH-PAYER-REF',
            'payer_id' => $tenant->id,
            'property_id' => $property->id,
            'transaction_type' => 'rent_payment',
            'provider' => 'stub',
            'status' => 'initiated',
            'gross_amount' => 850000,
            'platform_fee_percentage' => 0,
            'platform_fee_amount' => 0,
            'net_amount' => 850000,
            'currency' => 'NGN',
        ]);

        $payerResponse = $this->actingAs($admin)->get(route('admin.search', ['q' => 'Search Payer']));

        $payerResponse->assertOk();
        $payerResponse->assertSee($transaction->reference);

        $propertyResponse = $this->actingAs($admin)->get(route('admin.search', ['q' => 'Searchable Payment Property']));

        $propertyResponse->assertOk();
        $propertyResponse->assertSee($transaction->reference);
    }

    public function test_landlord_search_returns_own_listing(): void
    {
        $landlord = $this->createLandlord();
        $otherLandlord = $this->createLandlord('Other Landlord', 'other-landlord@example.com');

        $property = $this->createProperty($landlord, 'Landlord Search Listing');
        $otherProperty = $this->createProperty($otherLandlord, 'Other Listing');

        $response = $this->actingAs($landlord)->get(route('landlord.search', ['q' => 'Listing']));

        $response->assertOk();
        $response->assertSee('Matching listings');
        $response->assertSee($property->title);
        $response->assertDontSee($otherProperty->title);
        $response->assertSee('href="'.route('landlord.properties.edit', $property).'"', false);
    }

    public function test_landlord_search_returns_related_tenant_names_and_links(): void
    {
        $landlord = $this->createLandlord();
        $tenant = $this->createTenant('Requested Tenant', 'tenant-requested@example.com');

        $property = $this->createProperty($landlord, 'Inspection Listing');

        InspectionRequest::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'requested',
            'preferred_date' => now()->addDays(2)->toDateString(),
            'preferred_time_note' => 'Morning works best',
            'message' => 'Please confirm access.',
        ]);

        $response = $this->actingAs($landlord)->get(route('landlord.search', ['q' => 'Requested Tenant']));

        $response->assertOk();
        $response->assertSee('Matching tenant names');
        $response->assertSee($tenant->name);
        $response->assertSee('href="'.route('landlord.occupancy.index', ['tenant' => $tenant->id]).'"', false);
    }

    public function test_landlord_search_returns_paid_property_payment_references_only(): void
    {
        $landlord = $this->createLandlord();
        $tenant = $this->createTenant();
        $property = $this->createProperty($landlord, 'Paid Listing');

        $paidTransaction = PaymentTransaction::create([
            'reference' => 'PAID-LANDLORD-REF',
            'payer_id' => $tenant->id,
            'property_id' => $property->id,
            'transaction_type' => 'rent_payment',
            'provider' => 'stub',
            'status' => 'paid',
            'gross_amount' => 850000,
            'platform_fee_percentage' => 0,
            'platform_fee_amount' => 0,
            'net_amount' => 850000,
            'currency' => 'NGN',
        ]);

        PaymentTransaction::create([
            'reference' => 'PENDING-LANDLORD-REF',
            'payer_id' => $tenant->id,
            'property_id' => $property->id,
            'transaction_type' => 'rent_payment',
            'provider' => 'stub',
            'status' => 'initiated',
            'gross_amount' => 850000,
            'platform_fee_percentage' => 0,
            'platform_fee_amount' => 0,
            'net_amount' => 850000,
            'currency' => 'NGN',
        ]);

        PaymentTransaction::create([
            'reference' => 'INSPECTION-LANDLORD-REF',
            'payer_id' => $tenant->id,
            'property_id' => $property->id,
            'transaction_type' => 'inspection_booking_fee',
            'provider' => 'stub',
            'status' => 'paid',
            'gross_amount' => 5000,
            'platform_fee_percentage' => 0,
            'platform_fee_amount' => 0,
            'net_amount' => 5000,
            'currency' => 'NGN',
        ]);

        $response = $this->actingAs($landlord)->get(route('landlord.search', ['q' => 'LANDLORD-REF']));

        $response->assertOk();
        $response->assertSee($paidTransaction->reference);
        $response->assertDontSee('PENDING-LANDLORD-REF');
        $response->assertDontSee('INSPECTION-LANDLORD-REF');
        $response->assertSee('href="'.route('landlord.payments.index', ['reference' => $paidTransaction->reference]).'"', false);
    }

    protected function createRoleUser(string $role): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role);

        return $user;
    }

    protected function createTenant(string $name = 'Tenant Searcher', string $email = 'tenant@example.com'): User
    {
        $tenant = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
        ]);

        $tenant->assignRole('tenant');

        TenantProfile::create([
            'user_id' => $tenant->id,
        ]);

        return $tenant;
    }

    protected function createLandlord(string $name = 'Landlord Searcher', string $email = 'landlord@example.com'): User
    {
        $landlord = User::factory()->create([
            'name' => $name,
            'email' => $email,
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

    protected function createProperty(User $landlord, string $title): Property
    {
        return Property::create([
            'landlord_id' => $landlord->id,
            'title' => $title,
            'property_type' => 'flat',
            'listing_intent' => 'for_rent',
            'rent_amount' => 750000,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'status' => PublicPropertyVisibility::APPROVED_STATUS,
            'is_verified' => true,
            'is_published' => true,
        ]);
    }
}
