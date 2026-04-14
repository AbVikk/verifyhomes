<?php

namespace Tests\Feature;

use App\Models\InspectionRequest;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\PropertyPurchase;
use App\Models\TenantProfile;
use App\Models\User;
use App\Support\PaymentTransactionRecorder;
use App\Support\PublicPropertyVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseWorkflowTest extends TestCase
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

    public function test_buyer_can_initiate_house_purchase_payment(): void
    {
        config()->set('payments.default_provider', 'stub');

        $tenant = $this->createTenant();
        $property = $this->createProperty([
            'title' => 'House Purchase Listing',
            'listing_intent' => 'for_sale',
            'property_type' => 'flat',
            'total_units' => 1,
            'occupied_units' => 0,
        ]);

        $this->createInspectionRequest($tenant, $property, [
            'status' => 'completed',
            'outcome_type' => 'inspected',
        ]);

        $response = $this->actingAs($tenant)
            ->post(route('tenant.properties.purchase-payments.store', $property));

        $transaction = PaymentTransaction::first();

        $response->assertRedirect(route('tenant.payments.index', ['reference' => $transaction->reference]));
        $this->assertSame('house_purchase_payment', $transaction->transaction_type);
        $this->assertSame('initiated', $transaction->status);
        $this->assertSame('purchase_payment', $transaction->metadata['checkout_context']);
    }

    public function test_land_purchase_quantity_selector_shows_when_multiple_units_are_available(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createProperty([
            'title' => 'Land Quantity Listing',
            'listing_intent' => 'for_sale',
            'property_type' => 'land',
            'total_units' => 3,
            'occupied_units' => 0,
        ]);

        $this->createInspectionRequest($tenant, $property, [
            'status' => 'completed',
            'outcome_type' => 'inspected',
        ]);

        $response = $this->actingAs($tenant)->get(route('properties.show', $property));

        $response->assertOk()
            ->assertSee('Purchase quantity')
            ->assertSee('3 units available');
    }

    public function test_buyer_can_initiate_land_purchase_payment(): void
    {
        config()->set('payments.default_provider', 'stub');

        $tenant = $this->createTenant();
        $property = $this->createProperty([
            'title' => 'Land Purchase Listing',
            'listing_intent' => 'for_sale',
            'property_type' => 'land',
            'total_units' => 3,
            'occupied_units' => 0,
        ]);

        $this->createInspectionRequest($tenant, $property, [
            'status' => 'completed',
            'outcome_type' => 'inspected',
        ]);

        $response = $this->actingAs($tenant)
            ->post(route('tenant.properties.purchase-payments.store', $property), [
                'purchase_units' => 2,
            ]);

        $transaction = PaymentTransaction::first();

        $response->assertRedirect(route('tenant.payments.index', ['reference' => $transaction->reference]));
        $this->assertSame('land_purchase_payment', $transaction->transaction_type);
        $this->assertSame('initiated', $transaction->status);
        $this->assertSame('purchase_payment', $transaction->metadata['checkout_context']);
        $this->assertSame(2, $transaction->metadata['units_reserved']);
        $this->assertSame((float) $property->rent_amount * 2, (float) $transaction->gross_amount);
    }

    public function test_land_purchase_recheckout_resets_when_quantity_changes(): void
    {
        config()->set('payments.default_provider', 'stub');

        $tenant = $this->createTenant();
        $property = $this->createProperty([
            'title' => 'Land Recheckout Listing',
            'listing_intent' => 'for_sale',
            'property_type' => 'land',
            'total_units' => 4,
            'occupied_units' => 0,
        ]);

        $this->createInspectionRequest($tenant, $property, [
            'status' => 'completed',
            'outcome_type' => 'inspected',
        ]);

        $existing = PaymentTransactionRecorder::createPending([
            'payer_id' => $tenant->id,
            'property_id' => $property->id,
            'transaction_type' => 'land_purchase_payment',
            'gross_amount' => (float) $property->rent_amount,
            'status' => 'initiated',
            'provider' => 'stub',
            'metadata' => [
                'checkout_context' => 'purchase_payment',
                'units_reserved' => 1,
            ],
        ]);

        $response = $this->actingAs($tenant)
            ->post(route('tenant.properties.purchase-payments.store', $property), [
                'purchase_units' => 2,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseCount('payment_transactions', 2);

        $existing->refresh();
        $this->assertSame('failed', $existing->status);
        $this->assertSame('quantity_changed', $existing->metadata['checkout_reset_reason'] ?? null);

        $latest = PaymentTransaction::query()->latest('id')->first();

        $this->assertSame(2, $latest->metadata['units_reserved']);
        $this->assertSame((float) $property->rent_amount * 2, (float) $latest->gross_amount);
    }

    public function test_lease_listing_does_not_start_purchase_payment(): void
    {
        config()->set('payments.default_provider', 'stub');

        $tenant = $this->createTenant();
        $property = $this->createProperty([
            'title' => 'Lease Listing',
            'listing_intent' => 'for_lease',
            'property_type' => 'flat',
            'total_units' => 1,
            'occupied_units' => 0,
        ]);

        $this->createInspectionRequest($tenant, $property, [
            'status' => 'completed',
            'outcome_type' => 'inspected',
        ]);

        $response = $this->actingAs($tenant)
            ->post(route('tenant.properties.purchase-payments.store', $property));

        $response->assertRedirect(route('properties.show', $property));
        $response->assertSessionHasErrors(['property']);
        $this->assertDatabaseCount('payment_transactions', 0);
    }

    public function test_lease_listing_shows_lease_coordination_copy(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createProperty([
            'title' => 'Lease Coordination Listing',
            'listing_intent' => 'for_lease',
            'property_type' => 'flat',
            'total_units' => 1,
            'occupied_units' => 0,
        ]);

        $response = $this->actingAs($tenant)->get(route('properties.show', $property));

        $response->assertOk()
            ->assertSee('Lease coordination')
            ->assertSee('Lease amount');
    }

    public function test_paystack_test_mode_purchase_checkout_and_callback_flow_work_cleanly(): void
    {
        config()->set('payments.default_provider', 'paystack');
        config()->set('payments.providers.paystack.secret_key', 'sk_test_verifyhomes');
        config()->set('payments.providers.paystack.base_url', 'https://api.paystack.co');

        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.test/authorize/purchase123',
                    'access_code' => 'access_purchase_123',
                    'reference' => 'paystack-purchase-reference',
                ],
            ]),
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'id' => 554433,
                    'reference' => 'paystack-purchase-reference',
                    'status' => 'success',
                    'paid_at' => now()->toIso8601String(),
                    'channel' => 'card',
                ],
            ]),
        ]);

        $tenant = $this->createTenant();
        $property = $this->createProperty([
            'title' => 'Paystack Purchase Listing',
            'listing_intent' => 'for_sale',
            'property_type' => 'flat',
            'total_units' => 1,
            'occupied_units' => 0,
        ]);
        $this->createInspectionRequest($tenant, $property, [
            'status' => 'completed',
            'outcome_type' => 'inspected',
        ]);

        $initiationResponse = $this->actingAs($tenant)
            ->post(route('tenant.properties.purchase-payments.store', $property));

        $transaction = PaymentTransaction::first();

        $initiationResponse->assertRedirect('https://checkout.paystack.test/authorize/purchase123');
        $this->assertSame('paystack', $transaction->provider);
        $this->assertSame('house_purchase_payment', $transaction->transaction_type);
        $this->assertSame('initiated', $transaction->status);
        $this->assertSame('https://checkout.paystack.test/authorize/purchase123', $transaction->metadata['checkout_url']);

        $callbackResponse = $this->actingAs($tenant)->get(route('tenant.payments.callback', ['reference' => $transaction->reference]));

        $transaction->refresh();
        $property->refresh();

        $purchase = PropertyPurchase::query()
            ->where('payment_transaction_id', $transaction->getKey())
            ->where('buyer_id', $tenant->id)
            ->latest('purchased_at')
            ->first();

        $this->assertNotNull($purchase);
        $callbackResponse->assertRedirect(route('tenant.purchases.show', $purchase));

        $this->assertSame('paid', $transaction->status);
        $this->assertSame('554433', $transaction->provider_reference);
        $this->assertSame('success', $transaction->metadata['gateway_status']);
        $this->assertSame('callback_verification', $transaction->metadata['verified_via']);
        $this->assertSame(1, $property->occupied_units);
        $this->assertSame(0, $property->available_units);
        $this->assertDatabaseHas('property_purchases', [
            'property_id' => $property->id,
            'buyer_id' => $tenant->id,
        ]);
    }

    public function test_successful_purchase_creates_record_and_updates_availability(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createProperty([
            'title' => 'Confirmed Purchase Listing',
            'listing_intent' => 'for_sale',
            'property_type' => 'land',
            'total_units' => 4,
            'occupied_units' => 1,
        ]);

        $transaction = PaymentTransactionRecorder::createPending([
            'payer_id' => $tenant->id,
            'property_id' => $property->id,
            'transaction_type' => 'land_purchase_payment',
            'gross_amount' => $property->rent_amount * 2,
            'status' => 'initiated',
            'provider' => 'stub',
            'metadata' => [
                'units_reserved' => 2,
            ],
        ]);

        PaymentTransactionRecorder::markPaid($transaction, 'purchase-paid-001');

        $property->refresh();

        $this->assertSame(3, $property->occupied_units);
        $this->assertSame(1, $property->available_units);
        $this->assertDatabaseHas('property_purchases', [
            'property_id' => $property->id,
            'buyer_id' => $tenant->id,
            'purchase_type' => 'land',
            'units' => 2,
        ]);
    }

    public function test_purchase_effects_are_skipped_for_lease_listings(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createProperty([
            'title' => 'Lease Effects Listing',
            'listing_intent' => 'for_lease',
            'property_type' => 'flat',
            'total_units' => 2,
            'occupied_units' => 0,
        ]);

        $transaction = PaymentTransactionRecorder::createPending([
            'payer_id' => $tenant->id,
            'property_id' => $property->id,
            'transaction_type' => 'house_purchase_payment',
            'gross_amount' => $property->rent_amount,
            'status' => 'initiated',
            'provider' => 'stub',
            'metadata' => [
                'units_reserved' => 1,
            ],
        ]);

        PaymentTransactionRecorder::markPaid($transaction, 'lease-purchase-001');

        $property->refresh();

        $this->assertSame(0, $property->occupied_units);
        $this->assertSame(2, $property->available_units);
        $this->assertDatabaseCount('property_purchases', 0);
    }

    public function test_purchased_properties_show_in_tenant_workspace(): void
    {
        $tenant = $this->createTenant('buyer@example.com');
        $property = $this->createProperty([
            'title' => 'Tenant Purchase Property',
            'listing_intent' => 'for_sale',
            'property_type' => 'flat',
        ]);

        PropertyPurchase::create([
            'property_id' => $property->id,
            'buyer_id' => $tenant->id,
            'purchase_type' => 'house',
            'status' => 'confirmed',
            'units' => 1,
            'gross_amount' => $property->rent_amount,
            'currency' => 'NGN',
            'purchased_at' => now(),
        ]);

        $response = $this->actingAs($tenant)->get(route('tenant.occupancy.index'));

        $response->assertOk()
            ->assertSee('Purchased properties')
            ->assertSee('Tenant Purchase Property')
            ->assertSee('Purchased');
    }

    public function test_purchase_receipt_page_renders_for_confirmed_purchase(): void
    {
        $tenant = $this->createTenant('receipt-buyer@example.com');
        $property = $this->createProperty([
            'title' => 'Receipt Listing',
            'listing_intent' => 'for_sale',
            'property_type' => 'flat',
        ]);

        $purchase = PropertyPurchase::create([
            'property_id' => $property->id,
            'buyer_id' => $tenant->id,
            'purchase_type' => 'house',
            'status' => 'confirmed',
            'units' => 1,
            'gross_amount' => $property->rent_amount,
            'currency' => 'NGN',
            'purchased_at' => now(),
        ]);

        $response = $this->actingAs($tenant)->get(route('tenant.purchases.show', $purchase));

        $response->assertOk();
        $response->assertSee('Purchase receipt');
        $response->assertSee('Purchase summary');
        $response->assertSee('Receipt Listing');
        $response->assertSee('Purchase confirmed. Your ownership record is now on file.');
    }

    public function test_admin_can_view_purchase_records(): void
    {
        $admin = $this->createRoleUser('admin', 'purchase-admin@example.com');
        $tenant = $this->createTenant('purchase-admin-tenant@example.com');
        $property = $this->createProperty([
            'title' => 'Admin Purchase Listing',
            'listing_intent' => 'for_sale',
            'property_type' => 'land',
        ]);

        $purchase = PropertyPurchase::create([
            'property_id' => $property->id,
            'buyer_id' => $tenant->id,
            'purchase_type' => 'land',
            'status' => 'confirmed',
            'units' => 1,
            'gross_amount' => $property->rent_amount,
            'currency' => 'NGN',
            'purchased_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.purchases.index'));

        $response->assertOk()
            ->assertSee('Confirmed property purchases')
            ->assertSee($purchase->property->title)
            ->assertSee($tenant->name)
            ->assertSee('Land purchase');
    }

    protected function createRoleUser(string $role, ?string $email = null): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role);

        return $user;
    }

    protected function createTenant(?string $email = null): User
    {
        $tenant = $this->createRoleUser('tenant', $email);

        TenantProfile::create([
            'user_id' => $tenant->id,
        ]);

        return $tenant;
    }

    protected function createLandlord(): User
    {
        return $this->createRoleUser('landlord');
    }

    protected function createProperty(array $overrides = []): Property
    {
        return Property::create(array_merge([
            'landlord_id' => $this->createLandlord()->id,
            'title' => 'Purchase Property',
            'property_type' => 'flat',
            'listing_intent' => 'for_rent',
            'pricing_model' => 'tenant_price',
            'pricing_input_amount' => 850000,
            'rent_amount' => 850000,
            'landlord_net_amount' => 680000,
            'platform_fee_percentage' => 20,
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

    protected function createInspectionRequest(User $tenant, Property $property, array $overrides = []): InspectionRequest
    {
        $inspectionRequest = InspectionRequest::create(array_merge([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'requested',
            'preferred_date' => now()->addDays(2)->toDateString(),
            'preferred_time_note' => 'Afternoon works best',
            'message' => 'Please confirm access.',
            'outcome_type' => null,
            'outcome_notes' => null,
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
