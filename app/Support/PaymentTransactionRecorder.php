<?php

namespace App\Support;

use App\Models\Property;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PaymentTransactionRecorder
{
    public static function createPending(array $attributes): PaymentTransaction
    {
        $grossAmount = (float) Arr::get($attributes, 'gross_amount', 0);
        $percentage = Arr::has($attributes, 'platform_fee_percentage')
            ? (float) Arr::get($attributes, 'platform_fee_percentage')
            : static::resolvePlatformFeePercentage($attributes);
        $amounts = PaymentTransaction::buildAmounts(
            $grossAmount,
            $percentage,
        );
        $metadata = static::metadataWithPricingSnapshot($attributes, $amounts);

        return PaymentTransaction::create([
            'reference' => Arr::get($attributes, 'reference', static::generateReference()),
            'payer_id' => Arr::get($attributes, 'payer_id'),
            'property_id' => Arr::get($attributes, 'property_id'),
            'inspection_request_id' => Arr::get($attributes, 'inspection_request_id'),
            'transaction_type' => Arr::get($attributes, 'transaction_type', 'platform_payment'),
            'provider' => Arr::get($attributes, 'provider'),
            'provider_reference' => Arr::get($attributes, 'provider_reference'),
            'currency' => Arr::get($attributes, 'currency', 'NGN'),
            'status' => Arr::get($attributes, 'status', 'pending'),
            'gross_amount' => $amounts['gross_amount'],
            'platform_fee_percentage' => $amounts['platform_fee_percentage'],
            'platform_fee_amount' => $amounts['platform_fee_amount'],
            'net_amount' => $amounts['net_amount'],
            'metadata' => $metadata,
            'paid_at' => Arr::get($attributes, 'paid_at'),
        ]);
    }

    public static function markPaid(PaymentTransaction $transaction, ?string $providerReference = null, array $metadata = []): PaymentTransaction
    {
        return DB::transaction(function () use ($transaction, $providerReference, $metadata): PaymentTransaction {
            /** @var PaymentTransaction $lockedTransaction */
            $lockedTransaction = PaymentTransaction::query()->lockForUpdate()->findOrFail($transaction->getKey());
            $mergedMetadata = array_replace($lockedTransaction->metadata ?? [], $metadata);
            $mergedMetadata = app(PaymentCompletionService::class)->applyPaidEffects($lockedTransaction, $mergedMetadata);

            $lockedTransaction->update([
                'status' => 'paid',
                'provider_reference' => $providerReference ?? $lockedTransaction->provider_reference,
                'paid_at' => now(),
                'metadata' => $mergedMetadata,
            ]);

            return $lockedTransaction->fresh();
        });
    }

    public static function markPending(PaymentTransaction $transaction, ?string $providerReference = null, array $metadata = []): PaymentTransaction
    {
        $transaction->update([
            'status' => 'pending',
            'provider_reference' => $providerReference ?? $transaction->provider_reference,
            'metadata' => array_replace($transaction->metadata ?? [], $metadata),
        ]);

        return $transaction->fresh();
    }

    public static function markFailed(PaymentTransaction $transaction, ?string $providerReference = null, array $metadata = []): PaymentTransaction
    {
        $transaction->update([
            'status' => 'failed',
            'provider_reference' => $providerReference ?? $transaction->provider_reference,
            'metadata' => array_replace($transaction->metadata ?? [], $metadata),
        ]);

        return $transaction->fresh();
    }

    protected static function generateReference(): string
    {
        return 'txn_'.now()->format('YmdHis').'_'.Str::lower(Str::random(8));
    }

    protected static function resolvePlatformFeePercentage(array $attributes): ?float
    {
        if (Arr::get($attributes, 'transaction_type') !== 'rent_payment') {
            return null;
        }

        $propertyId = Arr::get($attributes, 'property_id');

        if (! $propertyId) {
            return (float) config('payments.rent_platform_fee_percentage', 20);
        }

        $property = Property::query()->find($propertyId);

        return $property
            ? (float) $property->platform_fee_percentage
            : (float) config('payments.rent_platform_fee_percentage', 20);
    }

    protected static function metadataWithPricingSnapshot(array $attributes, array $amounts): ?array
    {
        $metadata = Arr::get($attributes, 'metadata', []);

        if (! is_array($metadata)) {
            $metadata = [];
        }

        if (Arr::get($attributes, 'transaction_type') !== 'rent_payment') {
            return $metadata === [] ? null : $metadata;
        }

        $property = null;
        $propertyId = Arr::get($attributes, 'property_id');

        if ($propertyId) {
            $property = Property::query()->find($propertyId);
        }

        return array_replace($metadata, [
            'pricing_model' => $property?->pricing_model,
            'pricing_input_amount' => $property?->pricing_input_amount,
            'listed_rent_amount' => $property?->rent_amount ?? $amounts['gross_amount'],
            'landlord_net_amount' => $property?->landlord_net_amount ?? $amounts['net_amount'],
            'platform_fee_percentage' => $amounts['platform_fee_percentage'],
            'platform_fee_amount' => $amounts['platform_fee_amount'],
        ]);
    }
}
