<?php

namespace App\Support;

use App\Models\InspectionRequest;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\User;
use App\Support\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PaymentCheckoutService
{
    public function __construct(
        protected PaymentGatewayManager $paymentGatewayManager,
    ) {}

    public function initiateInspectionRequestPayment(InspectionRequest $inspectionRequest, User $payer): ?PaymentTransaction
    {
        if (! Schema::hasTable('payment_transactions')) {
            return null;
        }

        $existingTransaction = PaymentTransaction::query()
            ->where('payer_id', $payer->getKey())
            ->where('inspection_request_id', $inspectionRequest->getKey())
            ->where('transaction_type', 'inspection_booking_fee')
            ->whereIn('status', ['initiated', 'pending', 'paid'])
            ->latest('id')
            ->first();

        if ($existingTransaction) {
            return $existingTransaction;
        }

        $grossAmount = (float) config('payments.transaction_amounts.inspection_booking_fee', 0);
        $reference = 'txn_'.now()->format('YmdHis').'_'.Str::lower(Str::random(8));
        $gateway = $this->paymentGatewayManager->default();
        $checkout = $gateway->initiateInspectionRequestCheckout($inspectionRequest, $payer, $grossAmount, $reference);

        return PaymentTransactionRecorder::createPending([
            'reference' => $reference,
            'payer_id' => $payer->getKey(),
            'property_id' => $inspectionRequest->property_id,
            'inspection_request_id' => $inspectionRequest->getKey(),
            'transaction_type' => 'inspection_booking_fee',
            'provider' => $checkout['provider'] ?? $gateway->key(),
            'provider_reference' => $checkout['provider_reference'] ?? null,
            'status' => $checkout['status'] ?? 'initiated',
            'gross_amount' => $grossAmount,
            'currency' => 'NGN',
            'metadata' => $checkout['metadata'] ?? [
                'checkout_context' => 'inspection_request',
                'inspection_request_status' => $inspectionRequest->status,
            ],
        ]);
    }

    public function initiateRentPayment(Property $property, User $payer): ?PaymentTransaction
    {
        if (! Schema::hasTable('payment_transactions')) {
            return null;
        }

        $existingTransaction = PaymentTransaction::query()
            ->where('payer_id', $payer->getKey())
            ->where('property_id', $property->getKey())
            ->where('transaction_type', 'rent_payment')
            ->whereIn('status', ['initiated', 'pending', 'paid'])
            ->latest('id')
            ->first();

        if ($existingTransaction) {
            return $existingTransaction;
        }

        $grossAmount = (float) $property->rent_amount;
        $reference = 'txn_'.now()->format('YmdHis').'_'.Str::lower(Str::random(8));
        $gateway = $this->paymentGatewayManager->default();
        $checkout = $gateway->initiateRentPaymentCheckout($property, $payer, $grossAmount, $reference);

        return PaymentTransactionRecorder::createPending([
            'reference' => $reference,
            'payer_id' => $payer->getKey(),
            'property_id' => $property->getKey(),
            'transaction_type' => 'rent_payment',
            'provider' => $checkout['provider'] ?? $gateway->key(),
            'provider_reference' => $checkout['provider_reference'] ?? null,
            'status' => $checkout['status'] ?? 'initiated',
            'gross_amount' => $grossAmount,
            'currency' => 'NGN',
            'metadata' => $checkout['metadata'] ?? [
                'checkout_context' => 'rent_payment',
                'property_title' => $property->title,
                'listing_intent' => $property->listing_intent,
                'units_reserved' => 1,
            ],
        ]);
    }

    public function initiatePurchasePayment(Property $property, User $payer, int $unitsRequested = 1): ?PaymentTransaction
    {
        if (! Schema::hasTable('payment_transactions')) {
            return null;
        }

        $transactionType = $this->purchaseTransactionType($property);
        $unitsReserved = $this->purchaseUnitsReserved($property, $unitsRequested);

        $existingTransaction = PaymentTransaction::query()
            ->where('payer_id', $payer->getKey())
            ->where('property_id', $property->getKey())
            ->where('transaction_type', $transactionType)
            ->whereIn('status', ['initiated', 'pending', 'paid'])
            ->latest('id')
            ->first();

        if ($existingTransaction) {
            if ($existingTransaction->status === 'paid') {
                return $existingTransaction;
            }

            $existingUnits = (int) data_get($existingTransaction->metadata, 'units_reserved', 1);

            if ($existingUnits === $unitsReserved) {
                return $existingTransaction;
            }

            PaymentTransactionRecorder::markFailed(
                $existingTransaction,
                $existingTransaction->provider_reference,
                [
                    'checkout_reset_reason' => 'quantity_changed',
                    'checkout_reset_note' => 'Checkout was reset because the purchase quantity changed before payment.',
                ],
            );
        }

        $grossAmount = (float) $property->rent_amount * $unitsReserved;
        $reference = 'txn_'.now()->format('YmdHis').'_'.Str::lower(Str::random(8));
        $gateway = $this->paymentGatewayManager->default();
        $purchaseType = $this->purchaseType($property);
        $checkout = $gateway->initiatePurchasePaymentCheckout($property, $payer, $grossAmount, $reference, $purchaseType, $unitsReserved);

        return PaymentTransactionRecorder::createPending([
            'reference' => $reference,
            'payer_id' => $payer->getKey(),
            'property_id' => $property->getKey(),
            'transaction_type' => $transactionType,
            'provider' => $checkout['provider'] ?? $gateway->key(),
            'provider_reference' => $checkout['provider_reference'] ?? null,
            'status' => $checkout['status'] ?? 'initiated',
            'gross_amount' => $grossAmount,
            'currency' => 'NGN',
            'metadata' => $checkout['metadata'] ?? [
                'checkout_context' => 'purchase_payment',
                'property_title' => $property->title,
                'listing_intent' => $property->listing_intent,
                'purchase_type' => $purchaseType,
                'units_reserved' => $unitsReserved,
            ],
        ]);
    }

    public function verifyPaymentForPayer(string $reference, User $payer): ?PaymentTransaction
    {
        if (! Schema::hasTable('payment_transactions')) {
            return null;
        }

        $transaction = PaymentTransaction::query()
            ->where('reference', $reference)
            ->where('payer_id', $payer->getKey())
            ->latest('id')
            ->first();

        if (! $transaction) {
            return null;
        }

        $gateway = $this->paymentGatewayManager->for($transaction->provider);
        $verification = $gateway->verifyTransaction($transaction);
        $status = strtolower((string) ($verification['status'] ?? $transaction->status));
        $providerReference = $verification['provider_reference'] ?? null;
        $metadata = (array) ($verification['metadata'] ?? []);

        return match ($status) {
            'success', 'paid' => PaymentTransactionRecorder::markPaid($transaction, $providerReference, $metadata),
            'failed', 'error' => PaymentTransactionRecorder::markFailed($transaction, $providerReference, $metadata),
            'pending', 'initiated' => PaymentTransactionRecorder::markPending($transaction, $providerReference, $metadata),
            default => $transaction->fresh(),
        };
    }

    protected function purchaseType(Property $property): string
    {
        return $property->property_type === 'land' ? 'land' : 'house';
    }

    protected function purchaseTransactionType(Property $property): string
    {
        return $property->property_type === 'land'
            ? 'land_purchase_payment'
            : 'house_purchase_payment';
    }

    protected function purchaseUnitsReserved(Property $property, int $unitsRequested): int
    {
        if ($property->property_type !== 'land') {
            return 1;
        }

        $unitsRequested = max(1, $unitsRequested);
        $availableUnits = max(1, (int) $property->available_units);

        return min($unitsRequested, $availableUnits);
    }
}
