<?php

namespace Tests\Feature;

use App\Livewire\Landlord\TenancyAgreements\Show as LandlordAgreementShow;
use App\Livewire\Tenant\TenancyAgreements\Show as TenantAgreementShow;
use App\Mail\WorkflowNotificationMail;
use App\Models\Occupancy;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\TenantProfile;
use App\Models\TenancyAgreement;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\PaymentTransactionRecorder;
use App\Support\PublicPropertyVisibility;
use App\Support\TenancyAgreementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenancyAgreementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'staff', 'tenant', 'landlord'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_paid_rent_creates_one_immutable_agreement_and_notifies_the_tenant(): void
    {
        Mail::fake();
        $tenant = $this->user('tenant');
        $landlord = $this->user('landlord');
        $property = $this->property($landlord, ['property_terms' => 'No subletting without landlord approval.']);
        $transaction = $this->rentTransaction($tenant, $property);

        PaymentTransactionRecorder::markPaid($transaction, 'rent-agreement-001');
        PaymentTransactionRecorder::markPaid($transaction->fresh(), 'rent-agreement-001-replay');

        $agreement = TenancyAgreement::query()->sole();
        $this->assertSame('awaiting_tenant', $agreement->status);
        $this->assertSame($property->title, $agreement->agreement_snapshot['property_title']);
        $this->assertSame('No subletting without landlord approval.', $agreement->agreement_snapshot['property_terms']);
        $this->assertDatabaseCount('occupancies', 1);
        $this->assertDatabaseCount('tenancy_agreements', 1);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $tenant->id,
            'event_key' => 'tenancy-agreement-ready:'.$agreement->id,
        ]);
        Mail::assertSent(WorkflowNotificationMail::class);

        $snapshot = $agreement->agreement_snapshot;
        $property->update(['rent_amount' => 990000, 'property_terms' => 'Changed after payment.']);
        $agreement->refresh();

        $this->assertSame($snapshot, $agreement->agreement_snapshot);
    }

    public function test_non_rent_payments_do_not_create_agreements_and_upcoming_rentals_do(): void
    {
        Mail::fake();
        $tenant = $this->user('tenant');
        $landlord = $this->user('landlord');
        $currentProperty = $this->property($landlord);
        Occupancy::create([
            'property_id' => $currentProperty->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'units' => 1,
            'payment_cycle_months' => 12,
            'started_at' => now(),
            'last_payment_at' => now(),
            'next_payment_due_at' => now()->addYear(),
        ]);

        $inspection = PaymentTransactionRecorder::createPending(['payer_id' => $tenant->id, 'transaction_type' => 'inspection_booking_fee', 'gross_amount' => 5000, 'provider' => 'stub']);
        PaymentTransactionRecorder::markPaid($inspection, 'inspection-agreement-none');
        $purchase = PaymentTransactionRecorder::createPending(['payer_id' => $tenant->id, 'property_id' => $this->property($landlord, ['listing_intent' => 'for_sale'])->id, 'transaction_type' => 'house_purchase_payment', 'gross_amount' => 1000000, 'provider' => 'stub']);
        PaymentTransactionRecorder::markPaid($purchase, 'purchase-agreement-none');
        $landPurchase = PaymentTransactionRecorder::createPending(['payer_id' => $tenant->id, 'property_id' => $this->property($landlord, ['listing_intent' => 'for_sale', 'property_type' => 'land'])->id, 'transaction_type' => 'land_purchase_payment', 'gross_amount' => 1000000, 'provider' => 'stub']);
        PaymentTransactionRecorder::markPaid($landPurchase, 'land-purchase-agreement-none');

        $upcomingProperty = $this->property($landlord, ['title' => 'Upcoming Agreement Property']);
        PaymentTransactionRecorder::markPaid($this->rentTransaction($tenant, $upcomingProperty), 'upcoming-agreement-001');

        $upcoming = Occupancy::query()->where('property_id', $upcomingProperty->id)->sole();
        $this->assertSame('upcoming', $upcoming->status);
        $this->assertDatabaseCount('tenancy_agreements', 1);
        $this->assertDatabaseHas('tenancy_agreements', ['occupancy_id' => $upcoming->id]);
    }

    public function test_roles_can_only_view_their_own_agreement_and_admin_can_view_read_only_record(): void
    {
        $tenant = $this->user('tenant');
        $landlord = $this->user('landlord');
        $agreement = $this->agreement($tenant, $this->property($landlord));
        $otherTenant = $this->user('tenant');
        $otherLandlord = $this->user('landlord');
        $admin = $this->user('admin');

        $this->actingAs($tenant)->get(route('tenant.agreements.show', $agreement))->assertOk()->assertSee('Your rental agreement')->assertDontSee('selfie_path');
        $this->actingAs($otherTenant)->get(route('tenant.agreements.show', $agreement))->assertNotFound();
        $this->actingAs($landlord)->get(route('landlord.agreements.show', $agreement))->assertOk()->assertSee('Rental agreement review');
        $this->actingAs($otherLandlord)->get(route('landlord.agreements.show', $agreement))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.tenancy-agreements.show', $agreement))->assertOk()->assertSee('Read-only agreement record')->assertDontSee('Accept agreement');
    }

    public function test_acceptance_is_idempotent_and_completion_requires_both_parties(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-09-11 10:00:00');
        $tenant = $this->user('tenant');
        $landlord = $this->user('landlord');
        $agreement = $this->agreement($tenant, $this->property($landlord));

        $this->actingAs($tenant);
        Livewire::test(TenantAgreementShow::class, ['agreement' => $agreement])->set('accepted', true)->call('acceptAgreement')->assertHasNoErrors();
        $agreement->refresh();
        $firstTenantAcceptance = $agreement->tenant_accepted_at;
        $this->assertSame('awaiting_landlord', $agreement->status);
        $this->assertNull($agreement->completed_at);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $landlord->id, 'event_key' => 'tenancy-agreement-tenant-accepted:'.$agreement->id]);

        Carbon::setTestNow(now()->addMinute());
        Livewire::test(TenantAgreementShow::class, ['agreement' => $agreement])->set('accepted', true)->call('acceptAgreement')->assertHasNoErrors();
        $this->assertTrue($firstTenantAcceptance->equalTo($agreement->fresh()->tenant_accepted_at));

        $this->actingAs($landlord);
        Livewire::test(LandlordAgreementShow::class, ['agreement' => $agreement])->set('accepted', true)->call('acceptAgreement')->assertHasNoErrors();
        $agreement->refresh();
        $completedAt = $agreement->completed_at;
        $this->assertSame('completed', $agreement->status);
        $this->assertNotNull($agreement->landlord_accepted_at);
        $this->assertNotNull($completedAt);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $tenant->id, 'event_key' => 'tenancy-agreement-completed:'.$agreement->id]);

        Carbon::setTestNow(now()->addMinute());
        Livewire::test(LandlordAgreementShow::class, ['agreement' => $agreement])->set('accepted', true)->call('acceptAgreement')->assertHasNoErrors();
        $this->assertTrue($completedAt->equalTo($agreement->fresh()->completed_at));

        $agreement->refresh();
        $agreement->agreement_snapshot = ['property_title' => 'Tampered'];
        try {
            $agreement->save();
            $this->fail('A completed agreement snapshot should be immutable.');
        } catch (\LogicException) {
            $this->assertSame('completed', $agreement->fresh()->status);
        }
        Carbon::setTestNow();
    }

    public function test_landlord_can_accept_first_without_accepting_for_the_tenant_and_email_failure_does_not_roll_back(): void
    {
        $tenant = $this->user('tenant');
        $landlord = $this->user('landlord');
        $agreement = $this->agreement($tenant, $this->property($landlord));

        $this->actingAs($landlord);
        Livewire::test(LandlordAgreementShow::class, ['agreement' => $agreement])->set('accepted', true)->call('acceptAgreement')->assertHasNoErrors();
        $agreement->refresh();
        $this->assertSame('awaiting_tenant', $agreement->status);
        $this->assertNotNull($agreement->landlord_accepted_at);
        $this->assertNull($agreement->tenant_accepted_at);

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Mail unavailable'));
        $this->actingAs($tenant);
        Livewire::test(TenantAgreementShow::class, ['agreement' => $agreement])->set('accepted', true)->call('acceptAgreement')->assertHasNoErrors();
        $this->assertSame('completed', $agreement->fresh()->status);
    }

    public function test_legacy_occupancy_remains_visible_without_a_fabricated_agreement(): void
    {
        $tenant = $this->user('tenant');
        $occupancy = Occupancy::create([
            'property_id' => $this->property($this->user('landlord'))->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'units' => 1,
            'payment_cycle_months' => 12,
            'started_at' => now(),
            'last_payment_at' => now(),
            'next_payment_due_at' => now()->addYear(),
        ]);

        $this->actingAs($tenant)->get(route('tenant.occupancy.index'))->assertOk()->assertSee('No agreement generated for this legacy stay.');
        $this->assertDatabaseMissing('tenancy_agreements', ['occupancy_id' => $occupancy->id]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($role);

        if ($role === 'tenant') {
            TenantProfile::create(['user_id' => $user->id]);
        }

        return $user;
    }

    private function property(User $landlord, array $overrides = []): Property
    {
        return Property::create(array_merge([
            'landlord_id' => $landlord->id,
            'title' => 'Tenancy Agreement Property',
            'property_type' => 'flat',
            'listing_intent' => 'for_rent',
            'pricing_model' => 'tenant_price',
            'pricing_input_amount' => 850000,
            'rent_amount' => 850000,
            'landlord_net_amount' => 680000,
            'platform_fee_percentage' => 20,
            'total_units' => 2,
            'occupied_units' => 0,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'address_text' => '12 Alagbaka Road, Akure',
            'status' => PublicPropertyVisibility::APPROVED_STATUS,
            'is_verified' => true,
            'is_published' => true,
        ], $overrides));
    }

    private function rentTransaction(User $tenant, Property $property): PaymentTransaction
    {
        return PaymentTransactionRecorder::createPending([
            'payer_id' => $tenant->id,
            'property_id' => $property->id,
            'transaction_type' => 'rent_payment',
            'gross_amount' => $property->rent_amount,
            'provider' => 'stub',
            'metadata' => ['units_reserved' => 1],
        ]);
    }

    private function agreement(User $tenant, Property $property): TenancyAgreement
    {
        $occupancy = Occupancy::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'units' => 1,
            'payment_cycle_months' => 12,
            'started_at' => now(),
            'last_payment_at' => now(),
            'next_payment_due_at' => now()->addYear(),
        ]);
        $occupancy->load(['property.landlord', 'tenant', 'paymentTransaction']);

        return app(TenancyAgreementService::class)->createForOccupancy($occupancy);
    }
}
