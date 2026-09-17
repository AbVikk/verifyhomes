<?php

namespace Tests\Feature;

use App\Models\LandlordProfile;
use App\Models\LandlordSettlement;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\User;
use App\Livewire\Admin\Settlements\Index as AdminSettlementIndex;
use App\Support\LandlordSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Livewire\Livewire;
use Tests\TestCase;

class LandlordSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'staff', 'landlord', 'tenant'] as $role) Role::findOrCreate($role, 'web');
    }

    public function test_partial_and_full_payouts_preserve_snapshots_and_audit_events(): void
    {
        [$admin, $landlord, $transaction] = $this->eligibleTransaction();
        $service = app(LandlordSettlementService::class);
        $first = $service->record($transaction->id, $admin, 200000, 'TRF-1', 'Bank transfer', 'First portion');

        $this->assertSame('200000.00', $first->payout_amount);
        $this->assertSame('312000.00', $first->landlord_entitlement_snapshot);
        $this->assertSame(112000.0, $service->outstanding($transaction->fresh()));
        $this->assertDatabaseHas('landlord_settlement_events', ['landlord_settlement_id' => $first->id, 'actor_id' => $admin->id, 'event_type' => 'payout_recorded']);

        $service->record($transaction->id, $admin, 112000, 'TRF-2');
        $this->assertSame(0.0, $service->outstanding($transaction->fresh()));
        $this->assertSame('recorded_paid', $transaction->fresh()->landlord_settlement_status);
        $this->assertSame(2, LandlordSettlement::where('transaction_id', $transaction->id)->count());
    }

    public function test_overpayment_is_rejected_and_reversal_returns_value_to_outstanding(): void
    {
        [$admin, , $transaction] = $this->eligibleTransaction();
        $service = app(LandlordSettlementService::class);
        $settlement = $service->record($transaction->id, $admin, 100000, 'TRF-3');
        try { $service->record($transaction->id, $admin, 300000, 'TRF-4'); $this->fail('Expected overpayment validation.'); } catch (ValidationException) { $this->assertSame(1, LandlordSettlement::count()); }
        $service->reverse($settlement->id, $admin, 'Incorrect amount recorded');
        $this->assertSame(312000.0, $service->outstanding($transaction->fresh()));
        $this->assertDatabaseHas('landlord_settlements', ['id' => $settlement->id, 'status' => 'reversed']);
        $this->assertDatabaseHas('landlord_settlement_events', ['landlord_settlement_id' => $settlement->id, 'event_type' => 'payout_reversed']);
    }

    public function test_inspection_booking_fee_cannot_create_landlord_settlement(): void
    {
        [$admin, , $transaction] = $this->eligibleTransaction('inspection_booking_fee', 0);
        $this->expectException(ValidationException::class);
        app(LandlordSettlementService::class)->record($transaction->id, $admin, 1, 'NOPE');
    }

    public function test_landlord_payment_workspace_only_exposes_its_own_settlement_records(): void
    {
        [$admin, $landlord, $transaction] = $this->eligibleTransaction();
        app(LandlordSettlementService::class)->record($transaction->id, $admin, 312000, 'PRIVATE-REF');
        [, $otherLandlord] = $this->eligibleTransaction();

        $this->actingAs($landlord)->get(route('landlord.payments.index'))
            ->assertOk()->assertSee('PRIVATE-REF');
        $this->actingAs($otherLandlord)->get(route('landlord.payments.index'))
            ->assertOk()->assertDontSee('PRIVATE-REF');
        $this->actingAs($landlord)->get(route('admin.settlements.index'))->assertForbidden();
    }

    public function test_exact_full_payment_blocks_any_additional_or_duplicate_payout(): void
    {
        [$admin, , $transaction] = $this->eligibleTransaction();
        $service = app(LandlordSettlementService::class);
        $service->record($transaction->id, $admin, '200000.00', 'FULL-1');
        $service->record($transaction->id, $admin, '112000.00', 'FULL-2');
        $this->assertSame(312000.0, $service->paidToDate($transaction->id));
        $this->assertSame(0.0, $service->outstanding($transaction->fresh()));
        $this->assertSame('paid', $service->status($transaction->fresh()));
        $this->expectException(ValidationException::class);
        $service->record($transaction->id, $admin, 1, 'FULL-3');
    }

    public function test_duplicate_reference_and_overpayment_create_no_extra_history(): void
    {
        [$admin, , $transaction] = $this->eligibleTransaction();
        $service = app(LandlordSettlementService::class);
        $service->record($transaction->id, $admin, 200000, 'DUPLICATE-REF');
        foreach ([[1000, 'DUPLICATE-REF'], [112001, 'OVER-LIMIT']] as [$amount, $reference]) {
            try { $service->record($transaction->id, $admin, $amount, $reference); $this->fail('Expected validation failure.'); } catch (ValidationException) { }
        }
        $this->assertSame(1, LandlordSettlement::count());
        $this->assertSame(1, \App\Models\LandlordSettlementEvent::count());
        $this->assertSame(112000.0, $service->outstanding($transaction->fresh()));
    }

    public function test_partial_reversal_and_double_reversal_keep_history_and_payment_paid(): void
    {
        [$admin, , $transaction] = $this->eligibleTransaction();
        $service = app(LandlordSettlementService::class);
        $first = $service->record($transaction->id, $admin, 200000, 'REV-A');
        $service->record($transaction->id, $admin, 112000, 'REV-B');
        $service->reverse($first->id, $admin, 'Bank entry was incorrect');
        $this->assertSame(112000.0, $service->paidToDate($transaction->id));
        $this->assertSame(200000.0, $service->outstanding($transaction->fresh()));
        $this->assertSame('paid', $transaction->fresh()->status);
        try { $service->reverse($first->id, $admin, 'Again'); $this->fail('Expected double reversal validation.'); } catch (ValidationException) { }
        $this->assertSame(1, \App\Models\LandlordSettlementEvent::where('event_type', 'payout_reversed')->count());
    }

    public function test_bank_and_financial_snapshots_are_immutable(): void
    {
        [$admin, $landlord, $transaction] = $this->eligibleTransaction();
        $settlement = app(LandlordSettlementService::class)->record($transaction->id, $admin, 100000, 'SNAPSHOT');
        $landlord->landlordProfile->update(['bank_name' => 'Bank B', 'account_number' => '9999000011']);
        $transaction->property->update(['rent_amount' => 999999, 'platform_fee_percentage' => 5]);
        $settlement->refresh();
        $this->assertSame('GTBank', $settlement->bank_name);
        $this->assertSame('****6789', $settlement->masked_account_number);
        $this->assertSame('390000.00', $settlement->gross_amount_snapshot);
        $this->assertSame('78000.00', $settlement->platform_fee_snapshot);
        $this->assertSame('312000.00', $settlement->landlord_entitlement_snapshot);
    }

    public function test_legacy_recorded_paid_transaction_is_not_payable_again_or_fabricated(): void
    {
        [$admin, , $transaction] = $this->eligibleTransaction();
        $transaction->update(['landlord_settlement_status' => 'recorded_paid', 'landlord_settled_at' => now(), 'landlord_settled_by' => $admin->id]);
        $service = app(LandlordSettlementService::class);
        $this->assertTrue($service->isLegacySettled($transaction->fresh()));
        $this->assertSame('legacy_paid', $service->status($transaction->fresh()));
        $this->assertSame(0.0, $service->outstanding($transaction->fresh()));
        try { $service->record($transaction->id, $admin, 312000, 'LEGACY-RETRY'); $this->fail('Expected legacy payout protection.'); } catch (ValidationException) { }
        $this->assertSame(0, LandlordSettlement::count());
        $this->assertSame(0, \App\Models\LandlordSettlementEvent::count());
    }

    public function test_house_and_land_purchase_settlements_are_eligible_but_inspection_is_not(): void
    {
        foreach (['house_purchase_payment', 'land_purchase_payment'] as $type) {
            [$admin, $landlord, $transaction] = $this->eligibleTransaction($type);
            app(LandlordSettlementService::class)->record($transaction->id, $admin, 312000, 'PUR-'.strtoupper($type));
            $this->actingAs($landlord)->get(route('landlord.payments.index'))->assertOk()->assertSee('PUR-'.strtoupper($type));
        }
        [$admin, $landlord, $inspection] = $this->eligibleTransaction('inspection_booking_fee', 0);
        $this->actingAs($landlord)->get(route('landlord.payments.index'))->assertOk()->assertDontSee($inspection->reference);
        try { app(LandlordSettlementService::class)->record($inspection->id, $admin, 1, 'BOOKING'); $this->fail('Expected inspection exclusion.'); } catch (ValidationException) { }
        $this->assertSame(2, LandlordSettlement::count());
    }

    public function test_staff_can_view_settlements_but_cannot_mutate_them(): void
    {
        [, , $transaction] = $this->eligibleTransaction();
        $staff = User::factory()->create(); $staff->assignRole('staff');
        $this->actingAs($staff)->get(route('admin.settlements.index'))->assertOk();
        Livewire::actingAs($staff)->test(AdminSettlementIndex::class)
            ->set('recordingTransactionId', $transaction->id)
            ->set('payoutAmount', '100.00')
            ->call('recordPayout')
            ->assertForbidden();
        $this->assertSame(0, LandlordSettlement::count());
    }

    private function eligibleTransaction(string $type = 'rent_payment', int $net = 312000): array
    {
        $admin = User::factory()->create(); $admin->assignRole('admin');
        $landlord = User::factory()->create(); $landlord->assignRole('landlord');
        LandlordProfile::create(['user_id' => $landlord->id, 'bank_name' => 'GTBank', 'account_name' => 'Safe Landlord', 'account_number' => '0123456789']);
        $property = Property::create(['landlord_id' => $landlord->id, 'title' => 'Settlement Home', 'property_type' => 'flat', 'rent_amount' => 390000, 'lga' => 'Akure South', 'area' => 'Alagbaka']);
        $transaction = PaymentTransaction::create(['reference' => 'settlement-'.uniqid(), 'property_id' => $property->id, 'transaction_type' => $type, 'currency' => 'NGN', 'status' => 'paid', 'gross_amount' => $type === 'inspection_booking_fee' ? 5000 : 390000, 'platform_fee_percentage' => $type === 'inspection_booking_fee' ? 0 : 20, 'platform_fee_amount' => $type === 'inspection_booking_fee' ? 5000 : 78000, 'net_amount' => $net, 'paid_at' => now()]);
        return [$admin, $landlord, $transaction];
    }
}
