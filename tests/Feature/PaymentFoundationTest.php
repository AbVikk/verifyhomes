<?php

namespace Tests\Feature;

use App\Support\Payments\PaymentGatewayManager;
use App\Models\InspectionRequest;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\TenantProfile;
use App\Models\User;
use App\Support\PaymentTransactionRecorder;
use App\Support\PublicPropertyVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_transaction_recorder_creates_a_pending_transaction_with_platform_fee_breakdown(): void
    {
        $transaction = PaymentTransactionRecorder::createPending([
            'transaction_type' => 'inspection_booking_fee',
            'gross_amount' => 25000,
            'platform_fee_percentage' => 12.5,
            'currency' => 'NGN',
            'metadata' => [
                'source' => 'test',
            ],
        ]);

        $this->assertSame('pending', $transaction->status);
        $this->assertSame('inspection_booking_fee', $transaction->transaction_type);
        $this->assertSame('25000.00', $transaction->gross_amount);
        $this->assertSame('12.50', $transaction->platform_fee_percentage);
        $this->assertSame('3125.00', $transaction->platform_fee_amount);
        $this->assertSame('21875.00', $transaction->net_amount);
        $this->assertNotEmpty($transaction->reference);
    }

    public function test_payment_transaction_recorder_can_mark_transactions_paid_and_failed(): void
    {
        $transaction = PaymentTransactionRecorder::createPending([
            'transaction_type' => 'property_listing_fee',
            'gross_amount' => 100000,
        ]);

        $paidTransaction = PaymentTransactionRecorder::markPaid($transaction, 'paystack-ref-001', [
            'gateway_status' => 'success',
        ]);

        $this->assertSame('paid', $paidTransaction->status);
        $this->assertSame('paystack-ref-001', $paidTransaction->provider_reference);
        $this->assertNotNull($paidTransaction->paid_at);
        $this->assertSame('success', $paidTransaction->metadata['gateway_status']);

        $failedTransaction = PaymentTransactionRecorder::createPending([
            'transaction_type' => 'inspection_booking_fee',
            'gross_amount' => 5000,
        ]);

        $failedTransaction = PaymentTransactionRecorder::markFailed($failedTransaction, 'paystack-ref-002', [
            'gateway_status' => 'failed',
        ]);

        $this->assertSame('failed', $failedTransaction->status);
        $this->assertSame('paystack-ref-002', $failedTransaction->provider_reference);
        $this->assertSame('failed', $failedTransaction->metadata['gateway_status']);

        $this->assertSame(2, PaymentTransaction::count());
    }

    public function test_tenant_can_initiate_an_inspection_request_payment_transaction(): void
    {
        config()->set('payments.default_provider', 'stub');

        $tenant = $this->createTenant();
        $inspectionRequest = $this->createInspectionRequest($tenant);
        $gate = 'inspection-payment:request:'.$inspectionRequest->getKey();

        $response = $this->actingAs($tenant)
            ->withSession([
                $this->termsGateSessionKey($gate) => [
                    'opened_at' => now()->subSeconds(31)->toIso8601String(),
                    'completed_at' => now()->toIso8601String(),
                ],
            ])
            ->post(route('tenant.inspection-requests.payments.store', $inspectionRequest), [
                'accepted_inspection_terms' => '1',
            ]);

        $transaction = PaymentTransaction::first();

        $response->assertRedirect(route('tenant.payments.index', ['reference' => $transaction->reference]));
        $this->assertNotNull($transaction);
        $this->assertSame('initiated', $transaction->status);
        $this->assertSame('inspection_booking_fee', $transaction->transaction_type);
        $this->assertSame('stub', $transaction->provider);
        $this->assertSame('Stub Gateway', $transaction->metadata['gateway_label']);
        $this->assertSame('awaiting_provider_confirmation', $transaction->metadata['checkout_state']);
        $this->assertSame($tenant->id, $transaction->payer_id);
        $this->assertSame($inspectionRequest->id, $transaction->inspection_request_id);
        $this->assertSame((string) number_format((float) config('payments.transaction_amounts.inspection_booking_fee', 0), 2, '.', ''), $transaction->gross_amount);
    }

    public function test_tenant_can_initiate_a_rent_payment_transaction(): void
    {
        config()->set('payments.default_provider', 'stub');

        $tenant = $this->createTenant();
        $property = $this->createProperty([
            'title' => 'Rent Checkout Property',
            'total_units' => 2,
            'occupied_units' => 0,
        ]);
        $this->createInspectionRequest($tenant, $property, [
            'status' => 'completed',
            'outcome_type' => 'inspected',
            'outcome_notes' => 'Tenant completed the visit successfully.',
        ]);

        $response = $this->actingAs($tenant)
            ->post(route('tenant.properties.rent-payments.store', $property));

        $transaction = PaymentTransaction::first();

        $response->assertRedirect(route('tenant.payments.index', ['reference' => $transaction->reference]));
        $this->assertNotNull($transaction);
        $this->assertSame('initiated', $transaction->status);
        $this->assertSame('rent_payment', $transaction->transaction_type);
        $this->assertSame('stub', $transaction->provider);
        $this->assertSame('Stub Gateway', $transaction->metadata['gateway_label']);
        $this->assertSame('rent_payment', $transaction->metadata['checkout_context']);
        $this->assertSame('awaiting_provider_confirmation', $transaction->metadata['checkout_state']);
        $this->assertSame($tenant->id, $transaction->payer_id);
        $this->assertSame($property->id, $transaction->property_id);
        $this->assertSame((string) number_format((float) $property->rent_amount, 2, '.', ''), $transaction->gross_amount);
    }

    public function test_default_payment_provider_prefers_paystack_when_workspace_keys_are_available(): void
    {
        $this->assertSame('paystack', config('payments.default_provider'));
        $this->assertSame('paystack', app(PaymentGatewayManager::class)->default()->key());
    }

    public function test_verified_payment_webhook_marks_transaction_paid(): void
    {
        $transaction = PaymentTransactionRecorder::createPending([
            'transaction_type' => 'inspection_booking_fee',
            'gross_amount' => 5000,
            'status' => 'initiated',
            'provider' => 'stub',
        ]);

        $payload = [
            'reference' => $transaction->reference,
            'provider_reference' => 'provider-paid-001',
            'status' => 'success',
        ];

        $signature = hash_hmac('sha256', json_encode($payload), config('payments.webhook_secret'));

        $response = $this->postJson(route('payments.webhooks.provider'), $payload, [
            'X-Verifyhomes-Signature' => $signature,
        ]);

        $response->assertOk();
        $response->assertJson([
            'reference' => $transaction->reference,
            'status' => 'paid',
        ]);

        $transaction->refresh();

        $this->assertSame('paid', $transaction->status);
        $this->assertSame('provider-paid-001', $transaction->provider_reference);
        $this->assertNotNull($transaction->paid_at);
        $this->assertSame('success', $transaction->metadata['gateway_status']);
        $this->assertSame('webhook', $transaction->metadata['verified_via']);
        $this->assertSame('Stub Gateway', $transaction->metadata['gateway_label']);
    }

    public function test_invalid_payment_webhook_signature_is_rejected_safely(): void
    {
        $transaction = PaymentTransactionRecorder::createPending([
            'transaction_type' => 'inspection_booking_fee',
            'gross_amount' => 5000,
            'status' => 'initiated',
            'provider' => 'stub',
        ]);

        $payload = [
            'reference' => $transaction->reference,
            'provider_reference' => 'provider-failed-001',
            'status' => 'failed',
        ];

        $response = $this->postJson(route('payments.webhooks.provider'), $payload, [
            'X-Verifyhomes-Signature' => 'invalid-signature',
        ]);

        $response->assertStatus(401);
        $transaction->refresh();

        $this->assertSame('initiated', $transaction->status);
        $this->assertNull($transaction->provider_reference);
    }

    public function test_tenant_can_view_payment_history_and_entry_from_inspection_request(): void
    {
        config()->set('payments.default_provider', 'stub');

        $tenant = $this->createTenant();
        $inspectionRequest = $this->createInspectionRequest($tenant);
        $transaction = PaymentTransactionRecorder::createPending([
            'payer_id' => $tenant->id,
            'property_id' => $inspectionRequest->property_id,
            'inspection_request_id' => $inspectionRequest->id,
            'transaction_type' => 'inspection_booking_fee',
            'gross_amount' => 5000,
            'status' => 'initiated',
            'provider' => 'stub',
            'metadata' => [
                'gateway_label' => 'Stub Gateway',
                'checkout_context' => 'inspection_request',
            ],
        ]);

        $detailResponse = $this->actingAs($tenant)->get(route('tenant.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()]));

        $detailResponse->assertOk();
        $detailResponse->assertSee('Booking fee');
        $detailResponse->assertSee('Pay booking fee');
        $detailResponse->assertSee('Payment history');
        $detailResponse->assertSee('Stub Gateway');
        $detailResponse->assertSee('Checkout started. Finish the provider step to move this request forward.');
        $detailResponse->assertSee('5,000.00');
        $detailResponse->assertSee($transaction->reference);

        $paymentsResponse = $this->actingAs($tenant)->get(route('tenant.payments.index', ['reference' => $transaction->reference]));

        $paymentsResponse->assertOk();
        $paymentsResponse->assertSee('Your payment transactions');
        $paymentsResponse->assertSee($transaction->reference);
        $paymentsResponse->assertSee('Inspection Booking Fee');
        $paymentsResponse->assertSee('Stub Gateway');
        $paymentsResponse->assertSee('Checkout started. Finish the provider step to move this payment forward.');
        $paymentsResponse->assertSee('5,000.00');
        $paymentsResponse->assertSee('Open request');
    }

    public function test_paystack_test_mode_checkout_and_callback_verification_flow_work_cleanly(): void
    {
        config()->set('payments.default_provider', 'paystack');
        config()->set('payments.providers.paystack.secret_key', 'sk_test_verifyhomes');
        config()->set('payments.providers.paystack.base_url', 'https://api.paystack.co');

        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.test/authorize/abc123',
                    'access_code' => 'access_123',
                    'reference' => 'paystack-local-reference',
                ],
            ]),
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'id' => 912341,
                    'reference' => 'paystack-local-reference',
                    'status' => 'success',
                    'paid_at' => now()->toIso8601String(),
                    'channel' => 'card',
                ],
            ]),
        ]);

        $tenant = $this->createTenant();
        $inspectionRequest = $this->createInspectionRequest($tenant);
        $gate = 'inspection-payment:request:'.$inspectionRequest->getKey();

        $initiationResponse = $this->actingAs($tenant)
            ->withSession([
                $this->termsGateSessionKey($gate) => [
                    'opened_at' => now()->subSeconds(31)->toIso8601String(),
                    'completed_at' => now()->toIso8601String(),
                ],
            ])
            ->post(route('tenant.inspection-requests.payments.store', $inspectionRequest), [
                'accepted_inspection_terms' => '1',
            ]);

        $transaction = PaymentTransaction::first();

        $initiationResponse->assertRedirect('https://checkout.paystack.test/authorize/abc123');
        $this->assertSame('paystack', $transaction->provider);
        $this->assertSame('initiated', $transaction->status);
        $this->assertSame('https://checkout.paystack.test/authorize/abc123', $transaction->metadata['checkout_url']);

        $callbackResponse = $this->actingAs($tenant)->get(route('tenant.payments.callback', ['reference' => $transaction->reference]));

        $callbackResponse->assertRedirect(route('tenant.payments.index', ['reference' => $transaction->reference]));

        $transaction->refresh();

        $this->assertSame('paid', $transaction->status);
        $this->assertSame('912341', $transaction->provider_reference);
        $this->assertSame('success', $transaction->metadata['gateway_status']);
        $this->assertSame('callback_verification', $transaction->metadata['verified_via']);
    }

    public function test_paystack_test_mode_rent_checkout_and_callback_verification_flow_work_cleanly(): void
    {
        config()->set('payments.default_provider', 'paystack');
        config()->set('payments.providers.paystack.secret_key', 'sk_test_verifyhomes');
        config()->set('payments.providers.paystack.base_url', 'https://api.paystack.co');

        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.test/authorize/rent123',
                    'access_code' => 'access_rent_123',
                    'reference' => 'paystack-rent-reference',
                ],
            ]),
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'id' => 778899,
                    'reference' => 'paystack-rent-reference',
                    'status' => 'success',
                    'paid_at' => now()->toIso8601String(),
                    'channel' => 'card',
                ],
            ]),
        ]);

        $tenant = $this->createTenant();
        $property = $this->createProperty([
            'title' => 'Paystack Rent Property',
            'total_units' => 2,
            'occupied_units' => 0,
        ]);
        $this->createInspectionRequest($tenant, $property, [
            'status' => 'completed',
            'outcome_type' => 'inspected',
            'outcome_notes' => 'Tenant inspected the property before paying rent.',
        ]);

        $initiationResponse = $this->actingAs($tenant)
            ->post(route('tenant.properties.rent-payments.store', $property));

        $transaction = PaymentTransaction::first();

        $initiationResponse->assertRedirect('https://checkout.paystack.test/authorize/rent123');
        $this->assertSame('paystack', $transaction->provider);
        $this->assertSame('rent_payment', $transaction->transaction_type);
        $this->assertSame('initiated', $transaction->status);
        $this->assertSame('https://checkout.paystack.test/authorize/rent123', $transaction->metadata['checkout_url']);

        $callbackResponse = $this->actingAs($tenant)->get(route('tenant.payments.callback', ['reference' => $transaction->reference]));

        $callbackResponse->assertRedirect(route('tenant.payments.index', ['reference' => $transaction->reference]));

        $transaction->refresh();
        $property->refresh();

        $this->assertSame('paid', $transaction->status);
        $this->assertSame('778899', $transaction->provider_reference);
        $this->assertSame('success', $transaction->metadata['gateway_status']);
        $this->assertSame('callback_verification', $transaction->metadata['verified_via']);
        $this->assertSame(1, $property->occupied_units);
        $this->assertSame(1, $property->available_units);
    }

    public function test_provider_connection_failure_is_handled_with_friendly_message(): void
    {
        config()->set('payments.default_provider', 'paystack');
        config()->set('payments.providers.paystack.secret_key', 'sk_test_verifyhomes');

        Http::fake(function () {
            throw new ConnectionException('SSL certificate problem');
        });

        $tenant = $this->createTenant();
        $property = $this->createProperty([
            'title' => 'Rent Failure Property',
            'total_units' => 2,
            'occupied_units' => 0,
        ]);
        $this->createInspectionRequest($tenant, $property, [
            'status' => 'completed',
            'outcome_type' => 'inspected',
        ]);

        $response = $this->actingAs($tenant)
            ->from(route('properties.show', $property))
            ->post(route('tenant.properties.rent-payments.store', $property));

        $response->assertRedirect(route('properties.show', $property));
        $response->assertSessionHas('status', 'We could not connect to the payment provider right now. Please try again.');
    }

    public function test_paystack_ssl_verification_is_enabled_by_default(): void
    {
        config()->set('payments.providers.paystack.verify_ssl', true);
        $this->assertTrue((bool) config('payments.providers.paystack.verify_ssl', true));
    }

    public function test_invalid_payer_email_blocks_inspection_payment_before_provider_call(): void
    {
        config()->set('payments.default_provider', 'paystack');
        config()->set('payments.providers.paystack.secret_key', 'sk_test_verifyhomes');

        Http::fake();

        $tenant = $this->createTenant('not-an-email');
        $inspectionRequest = $this->createInspectionRequest($tenant);
        $gate = 'inspection-payment:request:'.$inspectionRequest->getKey();

        $response = $this->actingAs($tenant)
            ->withSession([
                $this->termsGateSessionKey($gate) => [
                    'opened_at' => now()->subSeconds(31)->toIso8601String(),
                    'completed_at' => now()->toIso8601String(),
                ],
            ])
            ->post(route('tenant.inspection-requests.payments.store', $inspectionRequest), [
                'accepted_inspection_terms' => '1',
            ]);

        $response->assertRedirect(route('tenant.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()]));
        $response->assertSessionHas('status', 'Add a valid email address to your account before starting payment.');
        Http::assertNothingSent();
    }

    public function test_invalid_payer_email_blocks_rent_payment_before_provider_call(): void
    {
        config()->set('payments.default_provider', 'paystack');
        config()->set('payments.providers.paystack.secret_key', 'sk_test_verifyhomes');

        Http::fake();

        $tenant = $this->createTenant('invalid-email');
        $property = $this->createProperty([
            'title' => 'Invalid Email Rent Property',
            'total_units' => 2,
            'occupied_units' => 0,
        ]);
        $this->createInspectionRequest($tenant, $property, [
            'status' => 'completed',
            'outcome_type' => 'inspected',
        ]);

        $response = $this->actingAs($tenant)
            ->from(route('properties.show', $property))
            ->post(route('tenant.properties.rent-payments.store', $property));

        $response->assertRedirect(route('properties.show', $property));
        $response->assertSessionHas('status', 'Add a valid email address to your account before starting payment.');
        Http::assertNothingSent();
    }

    public function test_pay_rent_does_not_appear_before_inspection_completion(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createProperty([
            'title' => 'Rent Status Property',
            'total_units' => 2,
            'occupied_units' => 0,
        ]);
        $this->createInspectionRequest($tenant, $property, [
            'status' => 'scheduled',
            'outcome_type' => null,
            'outcome_notes' => null,
        ]);

        $propertyResponse = $this->actingAs($tenant)->get(route('properties.show', $property));

        $propertyResponse->assertOk();
        $propertyResponse->assertSee('Pay rent for this listing');
        $propertyResponse->assertSee('Rent payment becomes available after your inspection is completed.');
        $propertyResponse->assertSee('Complete the inspection workflow first. Rent payment comes later in the process.');
        $propertyResponse->assertDontSee('>Pay rent<', false);
    }

    public function test_pay_rent_appears_after_completed_inspection_when_eligible(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createProperty([
            'title' => 'Eligible Rent Property',
            'total_units' => 2,
            'occupied_units' => 0,
        ]);
        $this->createInspectionRequest($tenant, $property, [
            'status' => 'completed',
            'outcome_type' => 'inspected',
            'outcome_notes' => 'Inspection completed successfully.',
        ]);

        $propertyResponse = $this->actingAs($tenant)->get(route('properties.show', $property));

        $propertyResponse->assertOk();
        $propertyResponse->assertSee('Rent payment has not started yet.');
        $propertyResponse->assertSee('Your inspection is complete. You can now pay rent for this listing.');
        $propertyResponse->assertSee('>Pay rent<', false);
    }

    public function test_purchase_payment_copy_is_clear_for_sale_listings(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createProperty([
            'title' => 'Sale Listing',
            'listing_intent' => 'for_sale',
            'property_type' => 'land',
        ]);

        $this->createInspectionRequest($tenant, $property, [
            'status' => 'completed',
            'outcome_type' => 'inspected',
        ]);

        $propertyResponse = $this->actingAs($tenant)->get(route('properties.show', $property));

        $propertyResponse->assertOk();
        $propertyResponse->assertSee('Purchase payment');
        $propertyResponse->assertSee('Purchase payment has not started yet.');
        $propertyResponse->assertSee('Your inspection is complete. You can now pay the purchase price for this listing.');
        $propertyResponse->assertSee('>Pay purchase price<', false);
        $propertyResponse->assertDontSee('Pay rent');
    }

    public function test_continue_checkout_appears_when_rent_checkout_is_already_started(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createProperty([
            'title' => 'Started Rent Checkout Property',
            'total_units' => 2,
            'occupied_units' => 0,
        ]);
        $this->createInspectionRequest($tenant, $property, [
            'status' => 'completed',
            'outcome_type' => 'inspected',
            'outcome_notes' => 'Inspection completed successfully.',
        ]);

        $transaction = PaymentTransactionRecorder::createPending([
            'payer_id' => $tenant->id,
            'property_id' => $property->id,
            'transaction_type' => 'rent_payment',
            'gross_amount' => $property->rent_amount,
            'status' => 'initiated',
            'provider' => 'paystack',
            'metadata' => [
                'gateway_label' => 'Paystack',
                'checkout_context' => 'rent_payment',
                'checkout_url' => 'https://checkout.paystack.test/rent/continue',
                'units_reserved' => 1,
            ],
        ]);

        $updatedPropertyResponse = $this->actingAs($tenant)->get(route('properties.show', $property));

        $updatedPropertyResponse->assertOk();
        $updatedPropertyResponse->assertSee('Rent checkout started. Finish the provider step to complete payment.');
        $updatedPropertyResponse->assertSee('Continue checkout');
        $updatedPropertyResponse->assertDontSee('>Pay rent<', false);

        $paymentsResponse = $this->actingAs($tenant)->get(route('tenant.payments.index', ['reference' => $transaction->reference]));

        $paymentsResponse->assertOk();
        $paymentsResponse->assertSee('Rent Payment');
        $paymentsResponse->assertSee('Rent payment for this property listing.');
        $paymentsResponse->assertSee('Rent checkout started. Finish the provider step to complete payment.');
    }

    public function test_paid_state_appears_after_successful_rent_payment(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createProperty([
            'title' => 'Paid Rent State Property',
            'total_units' => 2,
            'occupied_units' => 0,
        ]);
        $this->createInspectionRequest($tenant, $property, [
            'status' => 'completed',
            'outcome_type' => 'inspected',
            'outcome_notes' => 'Inspection completed successfully.',
        ]);

        $transaction = PaymentTransactionRecorder::createPending([
            'payer_id' => $tenant->id,
            'property_id' => $property->id,
            'transaction_type' => 'rent_payment',
            'gross_amount' => $property->rent_amount,
            'status' => 'initiated',
            'provider' => 'paystack',
            'metadata' => [
                'gateway_label' => 'Paystack',
                'checkout_context' => 'rent_payment',
                'units_reserved' => 1,
            ],
        ]);

        PaymentTransactionRecorder::markPaid($transaction, 'paid-rent-001');

        $property->refresh();
        $propertyResponse = $this->actingAs($tenant)->get(route('properties.show', $property));

        $propertyResponse->assertOk();
        $propertyResponse->assertSee('Rent payment confirmed.');
        $propertyResponse->assertSee('Your rent payment is complete for this listing.');
        $propertyResponse->assertSee('Rent paid');
        $propertyResponse->assertDontSee('>Pay rent<', false);
    }

    public function test_paystack_test_mode_webhook_verification_marks_transaction_paid_cleanly(): void
    {
        config()->set('payments.default_provider', 'paystack');
        config()->set('payments.providers.paystack.secret_key', 'sk_test_verifyhomes');

        $transaction = PaymentTransactionRecorder::createPending([
            'transaction_type' => 'inspection_booking_fee',
            'gross_amount' => 5000,
            'status' => 'initiated',
            'provider' => 'paystack',
            'metadata' => [
                'gateway_label' => 'Paystack',
                'checkout_context' => 'inspection_request',
            ],
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'id' => 445566,
                'reference' => $transaction->reference,
                'status' => 'success',
                'channel' => 'card',
            ],
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha512', $body, 'sk_test_verifyhomes');

        $response = $this->call(
            'POST',
            route('payments.webhooks.provider'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Paystack-Signature' => $signature,
            ],
            $body,
        );

        $response->assertOk();
        $response->assertJson([
            'reference' => $transaction->reference,
            'status' => 'paid',
        ]);

        $transaction->refresh();

        $this->assertSame('paid', $transaction->status);
        $this->assertSame('445566', $transaction->provider_reference);
        $this->assertSame('success', $transaction->metadata['gateway_status']);
        $this->assertSame('charge.success', $transaction->metadata['event']);
        $this->assertSame('webhook', $transaction->metadata['verified_via']);
        $this->assertSame('Paystack', $transaction->metadata['gateway_label']);
    }

    public function test_tenant_must_accept_inspection_terms_before_starting_payment_checkout(): void
    {
        $tenant = $this->createTenant();
        $inspectionRequest = $this->createInspectionRequest($tenant);

        $response = $this->actingAs($tenant)
            ->from(route('tenant.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()]))
            ->post(route('tenant.inspection-requests.payments.store', $inspectionRequest));

        $response->assertRedirect(route('tenant.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()]));
        $response->assertSessionHasErrors('accepted_inspection_terms');
        $this->assertDatabaseCount('payment_transactions', 0);
    }

    protected function createTenant(?string $email = null): User
    {
        Role::findOrCreate('tenant', 'web');

        $tenant = User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
        ]);

        $tenant->assignRole('tenant');

        TenantProfile::create([
            'user_id' => $tenant->id,
        ]);

        return $tenant;
    }

    protected function createLandlord(): User
    {
        Role::findOrCreate('landlord', 'web');

        $landlord = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $landlord->assignRole('landlord');

        return $landlord;
    }

    protected function createProperty(array $overrides = []): Property
    {
        return Property::create(array_merge([
            'landlord_id' => $this->createLandlord()->id,
            'title' => 'Payment Foundation Property',
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
            'status' => PublicPropertyVisibility::APPROVED_STATUS,
            'is_verified' => true,
            'is_published' => true,
        ], $overrides));
    }

    protected function createInspectionRequest(User $tenant, ?Property $property = null, array $overrides = []): InspectionRequest
    {
        $inspectionRequest = InspectionRequest::create(array_merge([
            'property_id' => ($property ?? $this->createProperty())->id,
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

    protected function termsGateSessionKey(string $gate): string
    {
        return 'terms_gates.'.md5($gate);
    }
}

