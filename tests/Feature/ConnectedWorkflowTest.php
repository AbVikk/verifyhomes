<?php

namespace Tests\Feature;

use App\Livewire\Landlord\Profile as LandlordProfilePage;
use App\Models\LandlordProfile;
use App\Models\Property;
use App\Models\TenantProfile;
use App\Models\User;
use App\Support\PaymentTransactionRecorder;
use App\Support\PublicPropertyVisibility;
use App\Support\Currency;
use App\Support\RentPricingCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConnectedWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_completed_rent_payment_deducts_units_once_and_updates_availability_display(): void
    {
        $tenant = $this->createTenant();
        $landlord = $this->createLandlord();
        $property = $this->createRentProperty($landlord, [
            'title' => 'Paid Occupancy Listing',
            'total_units' => 2,
            'occupied_units' => 1,
        ]);

        $transaction = PaymentTransactionRecorder::createPending([
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

        PaymentTransactionRecorder::markPaid($transaction, 'rent-paid-001');
        PaymentTransactionRecorder::markPaid($transaction->fresh(), 'rent-paid-001-repeat');

        $property->refresh();
        $transaction->refresh();

        $this->assertSame(2, $property->occupied_units);
        $this->assertSame(0, $property->available_units);
        $this->assertSame('already_applied', data_get($transaction->metadata, 'occupancy_update_status'));
        $this->assertSame(1, (int) data_get($transaction->metadata, 'occupancy_units_applied'));

        $this->get(route('properties.show', $property))
            ->assertOk()
            ->assertSee('Fully occupied')
            ->assertSee('0 available of 2 total units');
    }

    public function test_initiated_or_failed_rent_payment_does_not_deduct_units(): void
    {
        $tenant = $this->createTenant();
        $landlord = $this->createLandlord('failed-flow-landlord@example.com');
        $property = $this->createRentProperty($landlord, [
            'title' => 'No Deduction Listing',
            'total_units' => 3,
            'occupied_units' => 1,
        ]);

        $initiatedTransaction = PaymentTransactionRecorder::createPending([
            'payer_id' => $tenant->id,
            'property_id' => $property->id,
            'transaction_type' => 'rent_payment',
            'gross_amount' => $property->rent_amount,
            'status' => 'initiated',
            'provider' => 'stub',
        ]);

        $failedTransaction = PaymentTransactionRecorder::createPending([
            'payer_id' => $tenant->id,
            'property_id' => $property->id,
            'transaction_type' => 'rent_payment',
            'gross_amount' => $property->rent_amount,
            'status' => 'initiated',
            'provider' => 'stub',
        ]);

        PaymentTransactionRecorder::markFailed($failedTransaction, 'rent-failed-001');

        $property->refresh();
        $initiatedTransaction->refresh();
        $failedTransaction->refresh();

        $this->assertSame(1, $property->occupied_units);
        $this->assertSame(2, $property->available_units);
        $this->assertSame('initiated', $initiatedTransaction->status);
        $this->assertSame('failed', $failedTransaction->status);
    }

    public function test_landlord_rent_pricing_model_is_saved_with_clear_platform_fee_values(): void
    {
        $landlord = $this->createLandlord('pricing-landlord@example.com');
        $pricing = RentPricingCalculator::breakdown(800000, RentPricingCalculator::MODEL_LANDLORD_NET, 20);
        $property = $this->createRentProperty($landlord, [
            'title' => 'Pricing Model Listing',
            'pricing_model' => $pricing['pricing_model'],
            'pricing_input_amount' => $pricing['pricing_input_amount'],
            'rent_amount' => $pricing['rent_amount'],
            'landlord_net_amount' => $pricing['landlord_net_amount'],
            'platform_fee_percentage' => $pricing['platform_fee_percentage'],
            'total_units' => 2,
            'occupied_units' => 0,
            'status' => 'pending_review',
            'is_published' => false,
            'is_verified' => false,
        ]);

        $transaction = PaymentTransactionRecorder::createPending([
            'payer_id' => $this->createTenant('pricing-tenant@example.com')->id,
            'property_id' => $property->id,
            'transaction_type' => 'rent_payment',
            'gross_amount' => $property->rent_amount,
            'status' => 'initiated',
            'provider' => 'stub',
        ]);

        $this->assertSame(RentPricingCalculator::MODEL_LANDLORD_NET, $property->pricing_model);
        $this->assertSame('800000.00', $property->pricing_input_amount);
        $this->assertSame('1000000.00', $property->rent_amount);
        $this->assertSame('800000.00', $property->landlord_net_amount);
        $this->assertSame('20.00', $property->platform_fee_percentage);
        $this->assertSame('pending_review', $property->status);
        $this->assertFalse($property->is_published);
        $this->assertSame('20.00', $transaction->platform_fee_percentage);
        $this->assertSame('200000.00', $transaction->platform_fee_amount);
        $this->assertSame('800000.00', $transaction->net_amount);
        $this->assertSame(RentPricingCalculator::MODEL_LANDLORD_NET, data_get($transaction->metadata, 'pricing_model'));
        $this->assertSame('1000000.00', data_get($transaction->metadata, 'listed_rent_amount'));
        $this->assertSame('800000.00', data_get($transaction->metadata, 'landlord_net_amount'));
    }

    public function test_landlord_profile_stores_payout_ready_bank_details(): void
    {
        $landlord = $this->createLandlord('bank-landlord@example.com');

        $this->actingAs($landlord);

        Livewire::test(LandlordProfilePage::class)
            ->set('bankName', 'First Bank')
            ->set('accountName', 'Adewale Adebayo')
            ->set('accountNumber', '0123456789')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('landlord_profiles', [
            'user_id' => $landlord->id,
            'bank_name' => 'First Bank',
            'account_name' => 'Adewale Adebayo',
            'account_number' => '0123456789',
        ]);
    }

    public function test_tenant_inspection_terms_gate_requires_modal_open_and_ten_seconds(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createRentProperty($this->createLandlord('terms-landlord@example.com'), [
            'title' => 'Inspection Terms Listing',
        ]);
        $gate = 'inspection-request:property:'.$property->id;

        $this->actingAs($tenant);

        $this
            ->get(route('properties.show', $property))
            ->assertOk()
            ->assertSee('data-terms-gate-checkbox', false)
            ->assertSee('Open the terms to read and accept them in the modal.');

        $this
            ->postJson(route('terms-gates.complete'), ['gate' => $gate])
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Please read the terms before continuing.',
            ]);

        $this
            ->postJson(route('terms-gates.open'), ['gate' => $gate])
            ->assertOk()
            ->assertJson([
                'seconds_required' => 10,
                'seconds_remaining' => 10,
            ]);

        $this
            ->postJson(route('terms-gates.complete'), ['gate' => $gate])
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Please read the terms before continuing.',
            ]);
    }

    public function test_landlord_listing_terms_gate_requires_modal_open_and_ten_seconds(): void
    {
        $landlord = $this->createLandlord('listing-terms-landlord@example.com');
        $gate = 'listing-terms:create';

        $this->actingAs($landlord);

        $this
            ->get(route('landlord.properties.create'))
            ->assertOk()
            ->assertSee('data-terms-gate-checkbox', false)
            ->assertSee('Open the terms to read and accept them in the modal.');

        $this
            ->postJson(route('terms-gates.open'), ['gate' => $gate])
            ->assertOk()
            ->assertJson([
                'seconds_required' => 10,
                'seconds_remaining' => 10,
            ]);

        $this
            ->postJson(route('terms-gates.complete'), ['gate' => $gate])
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Please read the terms before continuing.',
            ]);
    }

    public function test_paid_rent_payment_state_connects_across_tenant_landlord_and_admin_workflows(): void
    {
        $brokenNaira = hex2bin('c3a2e2809ac2a6');
        $tenant = $this->createTenant();
        $landlord = $this->createLandlord('connected-landlord@example.com');
        $admin = $this->createRoleUser('admin', 'connected-admin@example.com');
        $property = $this->createRentProperty($landlord, [
            'title' => 'Connected Workflow Listing',
            'total_units' => 2,
            'occupied_units' => 1,
        ]);

        $transaction = PaymentTransactionRecorder::createPending([
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

        PaymentTransactionRecorder::markPaid($transaction, 'rent-paid-connected-001');
        $transaction->refresh();
        $property->refresh();

        $platformFee = Currency::format(200000, 'NGN');
        $landlordNet = Currency::format(800000, 'NGN');

        $this->actingAs($tenant)
            ->get(route('tenant.payments.index', ['reference' => $transaction->reference]))
            ->assertOk()
            ->assertSee('Your payment transactions')
            ->assertSee("Platform fee: {$platformFee} (20.00%). Landlord net snapshot: {$landlordNet}.")
            ->assertDontSee($brokenNaira)
            ->assertSee('Rent payment confirmed. Listing availability has been reduced by 1 unit.');

        $this->actingAs($landlord)
            ->get(route('landlord.payments.index', ['reference' => $transaction->reference]))
            ->assertOk()
            ->assertSee('Paid money tied to your listings')
            ->assertSee("Platform fee: {$platformFee} (20.00%). Landlord net snapshot: {$landlordNet}.")
            ->assertDontSee($brokenNaira)
            ->assertSee('Rent payment confirmed. Listing availability has been reduced by 1 unit.');

        $this->actingAs($admin)
            ->get(route('admin.payments.index', ['reference' => $transaction->reference]))
            ->assertOk()
            ->assertSee('Platform payment transactions')
            ->assertSee("Platform fee: {$platformFee} (20.00%). Net amount: {$landlordNet}.")
            ->assertDontSee($brokenNaira)
            ->assertSee('Rent payment confirmed. Listing availability has been reduced by 1 unit.');

        $this->actingAs($landlord)
            ->get(route('landlord.properties.edit', $property))
            ->assertOk()
            ->assertSee('0 available of 2 total units with 2 occupied.');
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

    protected function createRentProperty(User $landlord, array $overrides = []): Property
    {
        return Property::create(array_merge([
            'landlord_id' => $landlord->id,
            'title' => 'Connected Rent Property',
            'property_type' => 'flat',
            'listing_intent' => 'for_rent',
            'pricing_model' => RentPricingCalculator::MODEL_TENANT_PRICE,
            'pricing_input_amount' => 1000000,
            'rent_amount' => 1000000,
            'landlord_net_amount' => 800000,
            'platform_fee_percentage' => 20,
            'total_units' => 1,
            'occupied_units' => 0,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'description' => 'Connected workflow property.',
            'status' => PublicPropertyVisibility::APPROVED_STATUS,
            'is_verified' => true,
            'is_published' => true,
        ], $overrides));
    }
}
