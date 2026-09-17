<?php

namespace Tests\Feature;

use App\Mail\WorkflowNotificationMail;
use App\Models\InspectionRequest;
use App\Models\Occupancy;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\TenantProfile;
use App\Models\User;
use App\Support\PaymentTransactionRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkflowEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'staff', 'tenant', 'landlord'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_new_inspection_request_emails_admins_but_not_the_landlord(): void
    {
        Mail::fake();
        $tenant = $this->tenant();
        $admin = $this->user('admin', 'admin@example.test');
        $staff = $this->user('staff', 'staff@example.test');
        $landlord = $this->user('landlord', 'landlord@example.test');
        $property = $this->property($landlord);

        $completedAt = now();
        $this->actingAs($tenant)
            ->withSession(['terms_gates.'.md5('inspection-request:property:'.$property->id) => [
                'opened_at' => $completedAt->copy()->subSeconds(11)->toIso8601String(),
                'opened_at_ms' => $completedAt->copy()->subSeconds(11)->getTimestampMs(),
                'completed_at' => $completedAt->toIso8601String(),
            ]])
            ->post(route('inspection-requests.store', $property), ['accepted_inspection_terms' => '1'])
            ->assertRedirect(route('properties.show', $property));

        Mail::assertSent(WorkflowNotificationMail::class, fn (WorkflowNotificationMail $mail) => $mail->hasTo($admin->email) && $mail->actionUrl === route('admin.inspection-requests.show', ['inspectionRequestId' => 1]));
        Mail::assertSent(WorkflowNotificationMail::class, fn (WorkflowNotificationMail $mail) => $mail->hasTo($staff->email));
        Mail::assertSent(WorkflowNotificationMail::class, fn (WorkflowNotificationMail $mail) => $mail->hasTo($tenant->email)
            && $mail->subjectLine === 'Your inspection request has been received'
            && str_contains($mail->messageText, 'do not need to make a booking payment yet')
            && $mail->actionUrl === route('tenant.inspection-requests.show', ['inspectionRequestId' => 1]));
        Mail::assertNotSent(WorkflowNotificationMail::class, fn (WorkflowNotificationMail $mail) => $mail->hasTo($landlord->email));
    }

    public function test_rent_payment_email_is_deduplicated_and_keeps_booking_data_private(): void
    {
        Mail::fake();
        $tenant = $this->tenant();
        $landlord = $this->user('landlord', 'rent-landlord@example.test');
        $admin = $this->user('admin', 'rent-admin@example.test');
        $property = $this->property($landlord);
        $transaction = $this->rentTransaction($tenant, $property);

        PaymentTransactionRecorder::markPaid($transaction, 'rent-email-001');
        PaymentTransactionRecorder::markPaid($transaction->fresh(), 'rent-email-001-replay');

        Mail::assertSent(WorkflowNotificationMail::class, 4);
        Mail::assertSent(WorkflowNotificationMail::class, fn (WorkflowNotificationMail $mail) => $mail->hasTo($tenant->email) && $mail->subjectLine === 'Rent payment confirmed' && str_contains($mail->messageText, 'Rental period: 365 days (1 year)') && $mail->actionUrl === route('tenant.occupancy.index'));
        Mail::assertSent(WorkflowNotificationMail::class, fn (WorkflowNotificationMail $mail) => $mail->hasTo($landlord->email) && ! str_contains($mail->messageText, 'booking fee') && ! str_contains($mail->messageText, $transaction->reference));
        Mail::assertSent(WorkflowNotificationMail::class, fn (WorkflowNotificationMail $mail) => $mail->hasTo($admin->email));
        Mail::assertSent(WorkflowNotificationMail::class, fn (WorkflowNotificationMail $mail) => $mail->hasTo($tenant->email)
            && $mail->subjectLine === 'Your tenancy agreement is ready to review'
            && ! str_contains($mail->messageText, $transaction->reference)
            && str_contains($mail->actionUrl, '/tenant/agreements/'));
    }

    public function test_mail_transport_failure_does_not_break_paid_rent_effects(): void
    {
        $tenant = $this->tenant();
        $landlord = $this->user('landlord', 'failure-landlord@example.test');
        $property = $this->property($landlord);
        $transaction = $this->rentTransaction($tenant, $property);

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Mail transport unavailable'));

        PaymentTransactionRecorder::markPaid($transaction, 'rent-email-failure');

        $this->assertDatabaseHas('payment_transactions', ['id' => $transaction->id, 'status' => 'paid']);
        $this->assertDatabaseHas('occupancies', ['tenant_id' => $tenant->id, 'property_id' => $property->id, 'status' => 'active']);
    }

    public function test_upcoming_rental_email_uses_upcoming_wording_not_active_wording(): void
    {
        Mail::fake();
        $tenant = $this->tenant();
        $landlord = $this->user('landlord', 'upcoming-landlord@example.test');
        $currentProperty = $this->property($landlord);
        $nextProperty = $this->property($landlord);
        Occupancy::create([
            'property_id' => $currentProperty->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'units' => 1,
            'payment_cycle_months' => 12,
            'started_at' => now(),
            'last_payment_at' => now(),
            'next_payment_due_at' => now()->addDays(30),
        ]);

        PaymentTransactionRecorder::markPaid($this->rentTransaction($tenant, $nextProperty), 'upcoming-email-001');

        Mail::assertSent(WorkflowNotificationMail::class, fn (WorkflowNotificationMail $mail) => $mail->hasTo($tenant->email)
            && $mail->subjectLine === 'Your next rental is secured'
            && str_contains($mail->messageText, 'will become active after your current stay ends')
            && ! str_contains($mail->messageText, 'Your occupancy is active.'));
    }

    public function test_land_purchase_confirmation_email_links_the_tenant_to_the_receipt_once(): void
    {
        Mail::fake();
        $tenant = $this->tenant();
        $landlord = $this->user('landlord', 'purchase-landlord@example.test');
        $property = $this->property($landlord);
        $property->update(['listing_intent' => 'for_sale', 'property_type' => 'land', 'total_units' => 3]);
        $transaction = PaymentTransactionRecorder::createPending([
            'payer_id' => $tenant->id,
            'property_id' => $property->id,
            'transaction_type' => 'land_purchase_payment',
            'gross_amount' => 500000,
            'status' => 'initiated',
            'provider' => 'stub',
            'metadata' => ['units_reserved' => 2],
        ]);

        PaymentTransactionRecorder::markPaid($transaction, 'purchase-email-001');
        PaymentTransactionRecorder::markPaid($transaction->fresh(), 'purchase-email-001-replay');

        Mail::assertSent(WorkflowNotificationMail::class, 2);
        Mail::assertSent(WorkflowNotificationMail::class, fn (WorkflowNotificationMail $mail) => $mail->hasTo($tenant->email)
            && $mail->subjectLine === 'Land purchase confirmed'
            && $mail->actionLabel === 'View receipt'
            && str_contains($mail->actionUrl, '/tenant/purchases/'));
    }

    public function test_rental_period_labels_use_the_transaction_snapshot_only_for_rent(): void
    {
        $annual = new PaymentTransaction(['transaction_type' => 'rent_payment', 'metadata' => ['rental_period_days' => 365, 'rental_period_months' => 12]]);
        $halfYear = new PaymentTransaction(['transaction_type' => 'rent_payment', 'metadata' => ['rental_period_days' => 180, 'rental_period_months' => 6]]);

        $this->assertSame('365 days (1 year)', $annual->rentalPeriodLabel());
        $this->assertSame('180 days (6 months)', $halfYear->rentalPeriodLabel());
        $this->assertSame('-', (new PaymentTransaction(['transaction_type' => 'inspection_booking_fee']))->rentalPeriodLabel());
        $this->assertSame('-', (new PaymentTransaction(['transaction_type' => 'house_purchase_payment']))->rentalPeriodLabel());
    }

    protected function user(string $role, string $email): User
    {
        $user = User::factory()->create(['email' => $email, 'email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    protected function tenant(): User
    {
        $tenant = $this->user('tenant', 'tenant@example.test');
        TenantProfile::create(['user_id' => $tenant->id]);

        return $tenant;
    }

    protected function property(User $landlord): Property
    {
        return Property::create([
            'landlord_id' => $landlord->id,
            'title' => 'Workflow Email Property',
            'property_type' => 'flat',
            'listing_intent' => 'for_rent',
            'rent_amount' => 250000,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'status' => 'approved',
            'is_verified' => true,
            'is_published' => true,
        ]);
    }

    protected function rentTransaction(User $tenant, Property $property): PaymentTransaction
    {
        return PaymentTransactionRecorder::createPending([
            'payer_id' => $tenant->id,
            'property_id' => $property->id,
            'transaction_type' => 'rent_payment',
            'gross_amount' => $property->rent_amount,
            'status' => 'initiated',
            'provider' => 'stub',
            'metadata' => ['units_reserved' => 1],
        ]);
    }
}
