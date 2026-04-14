<?php

namespace App\Support\Payments;

use App\Contracts\Payments\PaymentGatewayAdapter;
use App\Models\InspectionRequest;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class StubPaymentGatewayAdapter implements PaymentGatewayAdapter
{
    public function key(): string
    {
        return 'stub';
    }

    public function label(): string
    {
        return (string) config('payments.providers.stub.label', 'Stub Gateway');
    }

    public function initiateInspectionRequestCheckout(InspectionRequest $inspectionRequest, User $payer, float $grossAmount, string $reference): array
    {
        return [
            'provider' => $this->key(),
            'status' => 'initiated',
            'provider_reference' => $reference,
            'metadata' => [
                'gateway_label' => $this->label(),
                'checkout_context' => 'inspection_request',
                'checkout_state' => 'awaiting_provider_confirmation',
                'inspection_request_status' => $inspectionRequest->status,
                'next_step' => 'Complete the checkout on the provider and wait for verified payment confirmation.',
                'payer_id' => $payer->getKey(),
                'quoted_amount' => number_format($grossAmount, 2, '.', ''),
            ],
        ];
    }

    public function initiateRentPaymentCheckout(Property $property, User $payer, float $grossAmount, string $reference): array
    {
        return [
            'provider' => $this->key(),
            'status' => 'initiated',
            'provider_reference' => $reference,
            'metadata' => [
                'gateway_label' => $this->label(),
                'checkout_context' => 'rent_payment',
                'checkout_state' => 'awaiting_provider_confirmation',
                'property_title' => $property->title,
                'listing_intent' => $property->listing_intent,
                'next_step' => 'Complete the checkout on the provider to finish your rent payment confirmation.',
                'payer_id' => $payer->getKey(),
                'quoted_amount' => number_format($grossAmount, 2, '.', ''),
                'units_reserved' => 1,
            ],
        ];
    }

    public function initiatePurchasePaymentCheckout(Property $property, User $payer, float $grossAmount, string $reference, string $purchaseType, int $units): array
    {
        return [
            'provider' => $this->key(),
            'status' => 'initiated',
            'provider_reference' => $reference,
            'metadata' => [
                'gateway_label' => $this->label(),
                'checkout_context' => 'purchase_payment',
                'checkout_state' => 'awaiting_provider_confirmation',
                'property_title' => $property->title,
                'listing_intent' => $property->listing_intent,
                'purchase_type' => $purchaseType,
                'next_step' => 'Complete the checkout on the provider to finish your purchase payment confirmation.',
                'payer_id' => $payer->getKey(),
                'quoted_amount' => number_format($grossAmount, 2, '.', ''),
                'units_reserved' => $units,
            ],
        ];
    }

    public function verifyTransaction(PaymentTransaction $transaction): array
    {
        return [
            'status' => $transaction->status,
            'provider_reference' => $transaction->provider_reference,
            'metadata' => array_replace($transaction->metadata ?? [], [
                'gateway_label' => $this->label(),
                'verified_via' => 'manual_check',
            ]),
        ];
    }

    public function hasValidWebhookSignature(Request $request): bool
    {
        $secret = (string) config('payments.webhook_secret', '');
        $signatureHeader = (string) config('payments.providers.stub.webhook_signature_header', 'X-Verifyhomes-Signature');
        $providedSignature = (string) $request->header($signatureHeader, '');

        if ($secret === '' || $providedSignature === '') {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expectedSignature, $providedSignature);
    }

    public function normalizeWebhookPayload(Request $request): array
    {
        $payload = $request->all();
        $status = strtolower((string) Arr::get($payload, 'status', ''));

        return [
            'reference' => (string) Arr::get($payload, 'reference', ''),
            'provider_reference' => Arr::get($payload, 'provider_reference'),
            'status' => $status,
            'metadata' => [
                'gateway_label' => $this->label(),
                'gateway_status' => $status,
                'verified_via' => 'webhook',
                'provider_payload' => $payload,
            ],
        ];
    }
}
