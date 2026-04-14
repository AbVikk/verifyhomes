<?php

namespace App\Support\Payments;

use App\Contracts\Payments\PaymentGatewayAdapter;
use App\Models\InspectionRequest;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackPaymentGatewayAdapter implements PaymentGatewayAdapter
{
    public function key(): string
    {
        return 'paystack';
    }

    public function label(): string
    {
        return (string) config('payments.providers.paystack.label', 'Paystack');
    }

    public function initiateInspectionRequestCheckout(InspectionRequest $inspectionRequest, User $payer, float $grossAmount, string $reference): array
    {
        $response = $this->initializeCheckout($payer, $grossAmount, $reference, [
            'vh_context' => 'inspection_request',
            'inspection_request_id' => $inspectionRequest->getKey(),
            'payer_id' => $payer->getKey(),
            'local_reference' => $reference,
        ]);

        return [
            'provider' => $this->key(),
            'status' => 'initiated',
            'provider_reference' => Arr::get($response, 'data.access_code'),
            'metadata' => [
                'gateway_label' => $this->label(),
                'checkout_context' => 'inspection_request',
                'checkout_state' => 'awaiting_customer_action',
                'checkout_url' => Arr::get($response, 'data.authorization_url'),
                'access_code' => Arr::get($response, 'data.access_code'),
                'provider_reference' => Arr::get($response, 'data.reference', $reference),
                'inspection_request_status' => $inspectionRequest->status,
                'next_step' => 'Complete the Paystack checkout to finish payment confirmation.',
                'payer_id' => $payer->getKey(),
                'quoted_amount' => number_format($grossAmount, 2, '.', ''),
            ],
        ];
    }

    public function initiateRentPaymentCheckout(Property $property, User $payer, float $grossAmount, string $reference): array
    {
        $response = $this->initializeCheckout($payer, $grossAmount, $reference, [
            'vh_context' => 'rent_payment',
            'property_id' => $property->getKey(),
            'property_title' => $property->title,
            'payer_id' => $payer->getKey(),
            'local_reference' => $reference,
        ]);

        return [
            'provider' => $this->key(),
            'status' => 'initiated',
            'provider_reference' => Arr::get($response, 'data.access_code'),
            'metadata' => [
                'gateway_label' => $this->label(),
                'checkout_context' => 'rent_payment',
                'checkout_state' => 'awaiting_customer_action',
                'checkout_url' => Arr::get($response, 'data.authorization_url'),
                'access_code' => Arr::get($response, 'data.access_code'),
                'provider_reference' => Arr::get($response, 'data.reference', $reference),
                'property_title' => $property->title,
                'listing_intent' => $property->listing_intent,
                'next_step' => 'Complete the Paystack checkout to finish your rent payment confirmation.',
                'payer_id' => $payer->getKey(),
                'quoted_amount' => number_format($grossAmount, 2, '.', ''),
                'units_reserved' => 1,
            ],
        ];
    }

    public function initiatePurchasePaymentCheckout(Property $property, User $payer, float $grossAmount, string $reference, string $purchaseType, int $units): array
    {
        $response = $this->initializeCheckout($payer, $grossAmount, $reference, [
            'vh_context' => 'purchase_payment',
            'property_id' => $property->getKey(),
            'property_title' => $property->title,
            'purchase_type' => $purchaseType,
            'units_reserved' => $units,
            'payer_id' => $payer->getKey(),
            'local_reference' => $reference,
        ]);

        return [
            'provider' => $this->key(),
            'status' => 'initiated',
            'provider_reference' => Arr::get($response, 'data.access_code'),
            'metadata' => [
                'gateway_label' => $this->label(),
                'checkout_context' => 'purchase_payment',
                'checkout_state' => 'awaiting_customer_action',
                'checkout_url' => Arr::get($response, 'data.authorization_url'),
                'access_code' => Arr::get($response, 'data.access_code'),
                'provider_reference' => Arr::get($response, 'data.reference', $reference),
                'property_title' => $property->title,
                'listing_intent' => $property->listing_intent,
                'purchase_type' => $purchaseType,
                'next_step' => 'Complete the Paystack checkout to finish your purchase payment confirmation.',
                'payer_id' => $payer->getKey(),
                'quoted_amount' => number_format($grossAmount, 2, '.', ''),
                'units_reserved' => $units,
            ],
        ];
    }

    public function verifyTransaction(PaymentTransaction $transaction): array
    {
        $response = $this->client()
            ->get('/transaction/verify/'.$transaction->reference)
            ->throw()
            ->json();

        $data = (array) Arr::get($response, 'data', []);
        $gatewayStatus = strtolower((string) Arr::get($data, 'status', ''));

        return [
            'status' => match ($gatewayStatus) {
                'success' => 'paid',
                'failed', 'abandoned' => 'failed',
                'ongoing', 'pending', 'processing', 'queued' => 'pending',
                default => $gatewayStatus !== '' ? $gatewayStatus : 'pending',
            },
            'provider_reference' => (string) (Arr::get($data, 'id') ?: Arr::get($data, 'reference', $transaction->reference)),
            'metadata' => [
                'gateway_label' => $this->label(),
                'gateway_status' => $gatewayStatus,
                'verified_via' => 'callback_verification',
                'paid_at' => Arr::get($data, 'paid_at'),
                'channel' => Arr::get($data, 'channel'),
                'provider_payload' => $response,
            ],
        ];
    }

    public function hasValidWebhookSignature(Request $request): bool
    {
        $secret = $this->secretKey(optional: true);
        $header = (string) config('payments.providers.paystack.webhook_signature_header', 'X-Paystack-Signature');
        $providedSignature = (string) $request->header($header, '');

        if ($secret === '' || $providedSignature === '') {
            return false;
        }

        $expectedSignature = hash_hmac('sha512', $request->getContent(), $secret);

        return hash_equals($expectedSignature, $providedSignature);
    }

    public function normalizeWebhookPayload(Request $request): array
    {
        $payload = $request->all();
        $data = (array) Arr::get($payload, 'data', []);
        $gatewayStatus = strtolower((string) Arr::get($data, 'status', ''));
        $event = (string) Arr::get($payload, 'event', '');

        if ($gatewayStatus === '' && $event === 'charge.success') {
            $gatewayStatus = 'success';
        }

        return [
            'reference' => (string) Arr::get($data, 'reference', ''),
            'provider_reference' => (string) (Arr::get($data, 'id') ?: Arr::get($data, 'reference', '')),
            'status' => match ($gatewayStatus) {
                'success' => 'paid',
                'failed', 'abandoned' => 'failed',
                default => $gatewayStatus,
            },
            'metadata' => [
                'gateway_label' => $this->label(),
                'gateway_status' => $gatewayStatus,
                'verified_via' => 'webhook',
                'event' => $event,
                'provider_payload' => $payload,
            ],
        ];
    }

    protected function client(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->baseUrl(rtrim((string) config('payments.providers.paystack.base_url', 'https://api.paystack.co'), '/'))
            ->withOptions([
                'verify' => (bool) config('payments.providers.paystack.verify_ssl', true),
            ])
            ->withToken($this->secretKey());
    }

    protected function initializeCheckout(User $payer, float $grossAmount, string $reference, array $metadata): array
    {
        $response = $this->client()->post('/transaction/initialize', [
            'email' => $payer->email,
            'amount' => (int) round($grossAmount * 100),
            'currency' => 'NGN',
            'reference' => $reference,
            'callback_url' => route('tenant.payments.callback', ['reference' => $reference]),
            'metadata' => $metadata,
        ])->throw()->json();

        if (! Arr::get($response, 'status')) {
            throw new RuntimeException('Paystack did not return a valid checkout response.');
        }

        return $response;
    }

    protected function secretKey(bool $optional = false): string
    {
        $secretKey = (string) config('payments.providers.paystack.secret_key', '');

        if ($secretKey === '' && ! $optional) {
            throw new RuntimeException('Paystack test mode is not configured yet. Add PAYSTACK_SECRET_KEY before using the Paystack provider.');
        }

        return $secretKey;
    }
}
