<?php

namespace App\Support;

use App\Models\PaymentTransaction;
use App\Support\Payments\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

class PaymentWebhookService
{
    public function __construct(
        protected PaymentGatewayManager $paymentGatewayManager,
    ) {}

    public function handleVerifiedWebhook(Request $request): ?PaymentTransaction
    {
        if (! Schema::hasTable('payment_transactions')) {
            return null;
        }

        $gateway = $this->paymentGatewayManager->resolveWebhookAdapter($request);

        if (! $gateway) {
            return null;
        }

        $normalizedPayload = $gateway->normalizeWebhookPayload($request);
        $reference = (string) Arr::get($normalizedPayload, 'reference', '');

        if ($reference === '') {
            return null;
        }

        $transaction = PaymentTransaction::query()->where('reference', $reference)->first();

        if (! $transaction) {
            return null;
        }

        $status = strtolower((string) Arr::get($normalizedPayload, 'status', ''));
        $providerReference = Arr::get($normalizedPayload, 'provider_reference');
        $metadata = (array) Arr::get($normalizedPayload, 'metadata', []);

        return match ($status) {
            'success', 'paid' => PaymentTransactionRecorder::markPaid($transaction, $providerReference, $metadata),
            'pending', 'initiated' => PaymentTransactionRecorder::markPending($transaction, $providerReference, $metadata),
            'failed', 'error' => PaymentTransactionRecorder::markFailed($transaction, $providerReference, $metadata),
            default => $transaction,
        };
    }

    public function hasValidSignature(Request $request): bool
    {
        return $this->paymentGatewayManager->resolveWebhookAdapter($request) !== null;
    }
}
