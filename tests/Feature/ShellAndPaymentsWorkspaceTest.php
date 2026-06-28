<?php

namespace Tests\Feature;

use App\Models\InspectionRequest;
use App\Models\LandlordProfile;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\TenantProfile;
use App\Models\User;
use App\Support\PaymentTransactionRecorder;
use App\Support\PublicPropertyVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShellAndPaymentsWorkspaceTest extends TestCase
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

    public function test_authenticated_landlord_browse_and_public_listing_pages_stay_inside_landlord_shell(): void
    {
        $landlord = $this->createLandlord();
        $property = $this->createProperty($landlord, [
            'title' => 'Shell Audit Listing',
        ]);

        $this->actingAs($landlord)->get(route('properties.index'))
            ->assertOk()
            ->assertSee('data-admin-shell-key="landlord"', false)
            ->assertDontSee('<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">', false);

        $this->actingAs($landlord)->get(route('properties.show', $property))
            ->assertOk()
            ->assertSee('data-admin-shell-key="landlord"', false)
            ->assertSee('Inspection requests stay on tenant accounts.')
            ->assertDontSee('<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">', false);
    }

    public function test_authenticated_landlord_literal_slug_routes_stay_inside_landlord_shell(): void
    {
        $landlord = $this->createLandlord();
        $property = $this->createProperty($landlord, [
            'title' => 'Literal Slug Shell Listing',
        ]);

        $this->actingAs($landlord)->get('/properties')
            ->assertOk()
            ->assertSee('data-admin-shell-key="landlord"', false)
            ->assertDontSee('<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">', false);

        $this->actingAs($landlord)->get('/properties/'.$property->slug)
            ->assertOk()
            ->assertSee('data-admin-shell-key="landlord"', false)
            ->assertSee('Inspection requests stay on tenant accounts.')
            ->assertDontSee('<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">', false);
    }

    public function test_authenticated_admin_browse_and_public_listing_pages_stay_inside_admin_shell(): void
    {
        $admin = $this->createRoleUser('admin');
        $property = $this->createProperty($this->createLandlord(), [
            'title' => 'Admin Shell Listing',
        ]);

        $this->actingAs($admin)->get(route('properties.index'))
            ->assertOk()
            ->assertSee('data-admin-shell-key="admin"', false)
            ->assertDontSee('<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">', false);

        $this->actingAs($admin)->get(route('properties.show', $property))
            ->assertOk()
            ->assertSee('data-admin-shell-key="admin"', false)
            ->assertDontSee('<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">', false);
    }

    public function test_authenticated_tenant_literal_slug_routes_stay_inside_tenant_shell(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createProperty($this->createLandlord('tenant-shell-landlord@example.com'), [
            'title' => 'Tenant Shell Listing',
        ]);

        $this->actingAs($tenant)->get('/properties')
            ->assertOk()
            ->assertSee('data-admin-shell-key="tenant"', false)
            ->assertDontSee('<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">', false);

        $this->actingAs($tenant)->get('/properties/'.$property->slug)
            ->assertOk()
            ->assertSee('data-admin-shell-key="tenant"', false)
            ->assertSee('Payment updates show here after checkout starts.')
            ->assertDontSee('<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">', false);
    }

    public function test_admin_payments_workspace_renders_and_is_protected(): void
    {
        $admin = $this->createRoleUser('admin');
        $tenant = $this->createTenant();
        $landlord = $this->createLandlord('payments-landlord@example.com');
        $property = $this->createProperty($landlord, [
            'title' => 'Admin Payments Property',
        ]);
        $inspectionRequest = $this->createInspectionRequest($tenant, $property);
        $transaction = PaymentTransactionRecorder::createPending([
            'payer_id' => $tenant->id,
            'property_id' => $property->id,
            'inspection_request_id' => $inspectionRequest->id,
            'transaction_type' => 'inspection_booking_fee',
            'gross_amount' => 5000,
            'status' => 'pending',
            'provider' => 'stub',
        ]);

        $this->actingAs($admin)->get(route('admin.payments.index', ['reference' => $transaction->reference]))
            ->assertOk()
            ->assertSee('Platform payment transactions')
            ->assertSee($transaction->reference)
            ->assertSee('Provider checkout finished, but VerifyHomes is still waiting for final confirmation.');

        $this->actingAs($tenant)->get(route('admin.payments.index'))->assertForbidden();
        $this->actingAs($landlord)->get(route('admin.payments.index'))->assertForbidden();
    }

    public function test_admin_payments_workspace_distinguishes_rent_payment_lifecycle(): void
    {
        $admin = $this->createRoleUser('admin', 'rent-admin@example.com');
        $tenant = $this->createTenant('rent-admin-tenant@example.com');
        $landlord = $this->createLandlord('rent-admin-landlord@example.com');
        $property = $this->createProperty($landlord, [
            'title' => 'Admin Rent Visibility Property',
            'total_units' => 2,
            'occupied_units' => 0,
        ]);

        $transaction = PaymentTransactionRecorder::createPending([
            'payer_id' => $tenant->id,
            'property_id' => $property->id,
            'transaction_type' => 'rent_payment',
            'gross_amount' => $property->rent_amount,
            'status' => 'initiated',
            'provider' => 'paystack',
            'metadata' => [
                'checkout_context' => 'rent_payment',
                'gateway_label' => 'Paystack',
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.payments.index', ['reference' => $transaction->reference]));

        $response->assertOk();
        $response->assertSee('Rent payment');
        $response->assertSee('Rent checkout started. The tenant may still need to finish the provider step.');
        $response->assertSee($transaction->reference);
    }

    public function test_admin_payments_workspace_redirects_guests_to_login(): void
    {
        $this->get(route('admin.payments.index'))->assertRedirect(route('login'));
    }

    public function test_landlord_payments_workspace_is_scoped_to_their_properties(): void
    {
        $landlord = $this->createLandlord();
        $otherLandlord = $this->createLandlord('other-landlord@example.com');
        $tenant = $this->createTenant();
        $visibleProperty = $this->createProperty($landlord, [
            'title' => 'Visible Listing Payment',
        ]);
        $hiddenProperty = $this->createProperty($otherLandlord, [
            'title' => 'Hidden Listing Payment',
        ]);

        $visibleTransaction = PaymentTransactionRecorder::createPending([
            'payer_id' => $tenant->id,
            'property_id' => $visibleProperty->id,
            'transaction_type' => 'rent_payment',
            'gross_amount' => 650000,
            'status' => 'paid',
            'provider' => 'stub',
        ]);

        $visibleInitiatedTransaction = PaymentTransactionRecorder::createPending([
            'payer_id' => $tenant->id,
            'property_id' => $visibleProperty->id,
            'transaction_type' => 'rent_payment',
            'gross_amount' => 650000,
            'status' => 'initiated',
            'provider' => 'paystack',
        ]);

        $inspectionTransaction = PaymentTransactionRecorder::createPending([
            'payer_id' => $tenant->id,
            'property_id' => $visibleProperty->id,
            'transaction_type' => 'inspection_booking_fee',
            'gross_amount' => 5000,
            'status' => 'paid',
            'provider' => 'stub',
        ]);

        $hiddenTransaction = PaymentTransactionRecorder::createPending([
            'payer_id' => $tenant->id,
            'property_id' => $hiddenProperty->id,
            'transaction_type' => 'rent_payment',
            'gross_amount' => 650000,
            'status' => 'paid',
            'provider' => 'stub',
        ]);

        $response = $this->actingAs($landlord)->get(route('landlord.payments.index', ['reference' => $visibleTransaction->reference]));

        $response->assertOk();
        $response->assertSee('Paid money tied to your listings');
        $response->assertSee($visibleTransaction->reference);
        $response->assertSee($visibleProperty->title);
        $response->assertDontSee($visibleInitiatedTransaction->reference);
        $response->assertDontSee($inspectionTransaction->reference);
        $response->assertDontSee($hiddenTransaction->reference);
        $response->assertDontSee($hiddenProperty->title);
        $response->assertSee('Payment verified. This is landlord-relevant settled money tied to your property activity.');
        $response->assertDontSee('Initiated');
        $response->assertDontSee('Awaiting verification');
        $response->assertDontSee('Failed');

        $this->actingAs($tenant)->get(route('landlord.payments.index'))->assertForbidden();
    }

    public function test_landlord_payments_workspace_shows_only_paid_rent_money_and_hides_checkout_noise(): void
    {
        $landlord = $this->createLandlord('rent-landlord@example.com');
        $tenant = $this->createTenant('rent-tenant@example.com');
        $property = $this->createProperty($landlord, [
            'title' => 'Landlord Rent Visibility Property',
            'total_units' => 2,
            'occupied_units' => 0,
        ]);

        $paidTransaction = PaymentTransactionRecorder::createPending([
            'payer_id' => $tenant->id,
            'property_id' => $property->id,
            'transaction_type' => 'rent_payment',
            'gross_amount' => $property->rent_amount,
            'status' => 'initiated',
            'provider' => 'stub',
            'metadata' => [
                'units_reserved' => 1,
            ],
        ]);

        PaymentTransactionRecorder::markPaid($paidTransaction, 'rent-landlord-paid-001');

        $initiatedTransaction = PaymentTransactionRecorder::createPending([
            'payer_id' => $tenant->id,
            'property_id' => $property->id,
            'transaction_type' => 'rent_payment',
            'gross_amount' => $property->rent_amount,
            'status' => 'initiated',
            'provider' => 'paystack',
        ]);

        $response = $this->actingAs($landlord)->get(route('landlord.payments.index', ['reference' => $paidTransaction->reference]));

        $response->assertOk();
        $response->assertSee('Paid money tied to your listings');
        $response->assertSee($paidTransaction->reference);
        $response->assertDontSee($initiatedTransaction->reference);
        $response->assertSee('Payment verified. This is landlord-relevant settled money tied to your property activity.');
        $response->assertDontSee('Initiated');
        $response->assertDontSee('Awaiting verification');
        $response->assertDontSee('Failed');
    }

    public function test_landlord_payments_workspace_shows_paid_purchase_money_only(): void
    {
        $landlord = $this->createLandlord('purchase-landlord@example.com');
        $tenant = $this->createTenant('purchase-tenant@example.com');
        $property = $this->createProperty($landlord, [
            'title' => 'Landlord Purchase Visibility Property',
            'listing_intent' => 'for_sale',
            'property_type' => 'land',
            'total_units' => 3,
            'occupied_units' => 0,
        ]);

        $paidPurchase = PaymentTransactionRecorder::createPending([
            'payer_id' => $tenant->id,
            'property_id' => $property->id,
            'transaction_type' => 'land_purchase_payment',
            'gross_amount' => 1200000,
            'status' => 'initiated',
            'provider' => 'stub',
            'metadata' => [
                'units_reserved' => 1,
            ],
        ]);

        PaymentTransactionRecorder::markPaid($paidPurchase, 'purchase-paid-002');

        $inspectionTransaction = PaymentTransactionRecorder::createPending([
            'payer_id' => $tenant->id,
            'property_id' => $property->id,
            'transaction_type' => 'inspection_booking_fee',
            'gross_amount' => 5000,
            'status' => 'paid',
            'provider' => 'stub',
        ]);

        $response = $this->actingAs($landlord)->get(route('landlord.payments.index', ['reference' => $paidPurchase->reference]));

        $response->assertOk();
        $response->assertSee('Paid money tied to your listings');
        $response->assertSee($paidPurchase->reference);
        $response->assertDontSee($inspectionTransaction->reference);
        $response->assertSee('Land purchase payment');
    }

    public function test_landlord_payments_workspace_redirects_guests_to_login(): void
    {
        $this->get(route('landlord.payments.index'))->assertRedirect(route('login'));
    }

    public function test_payment_initiation_visibility_is_connected_for_tenant_landlord_and_admin_workspaces(): void
    {
        $tenant = $this->createTenant();
        $landlord = $this->createLandlord();
        $admin = $this->createRoleUser('admin');
        $property = $this->createProperty($landlord, [
            'title' => 'Connected Checkout Listing',
        ]);
        $inspectionRequest = $this->createInspectionRequest($tenant, $property);

        $transaction = PaymentTransactionRecorder::createPending([
            'payer_id' => $tenant->id,
            'property_id' => $property->id,
            'inspection_request_id' => $inspectionRequest->id,
            'transaction_type' => 'inspection_booking_fee',
            'gross_amount' => 5000,
            'status' => 'initiated',
            'provider' => 'paystack',
            'metadata' => [
                'gateway_label' => 'Paystack',
                'checkout_url' => 'https://checkout.paystack.test/continue/123',
                'checkout_context' => 'inspection_request',
            ],
        ]);

        $this->actingAs($tenant)->get(route('tenant.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()]))
            ->assertOk()
            ->assertSee('Checkout started')
            ->assertSee('Continue checkout')
            ->assertSee('Checkout started. Finish the provider step to move this request forward.');

        $this->actingAs($tenant)->get(route('tenant.payments.index', ['reference' => $transaction->reference]))
            ->assertOk()
            ->assertSee('Continue checkout')
            ->assertSee('Checkout started. Finish the provider step to move this payment forward.');

        $this->actingAs($landlord)->get(route('landlord.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()]))
            ->assertOk()
            ->assertSee('Share access details or readiness notes with admin.')
            ->assertDontSee('Payment status')
            ->assertDontSee('Booking fee');

        $this->actingAs($admin)->get(route('admin.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()]))
            ->assertOk()
            ->assertSee('Checkout started; tenant still needs to finish the provider step')
            ->assertSee('Wait for the tenant to complete checkout before scheduling this visit.')
            ->assertSee(route('admin.payments.index', ['reference' => $transaction->reference]), false);
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

    protected function createLandlord(?string $email = null): User
    {
        $landlord = $this->createRoleUser('landlord', $email);

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
            'title' => 'Shell And Payments Property',
            'property_type' => 'flat',
            'listing_intent' => 'for_rent',
            'rent_amount' => 780000,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'status' => PublicPropertyVisibility::APPROVED_STATUS,
            'is_verified' => true,
            'is_published' => true,
        ], $overrides));
    }

    protected function createInspectionRequest(User $tenant, Property $property): InspectionRequest
    {
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
