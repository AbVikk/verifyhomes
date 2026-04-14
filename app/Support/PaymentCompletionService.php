<?php

namespace App\Support;

use App\Models\Occupancy;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\PropertyPurchase;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Schema;

class PaymentCompletionService
{
    public function applyPaidEffects(PaymentTransaction $transaction, array $metadata = []): array
    {
        if (in_array($transaction->transaction_type, ['house_purchase_payment', 'land_purchase_payment', 'purchase_payment'], true)) {
            return $this->applyPurchaseEffects($transaction, $metadata);
        }

        if ($transaction->transaction_type !== 'rent_payment' || ! $transaction->property_id) {
            return $metadata;
        }

        $property = Property::query()->lockForUpdate()->find($transaction->property_id);

        if (! $property) {
            return array_replace($metadata, [
                'occupancy_update_status' => 'property_not_found',
            ]);
        }

        if (filled(data_get($transaction->metadata, 'occupancy_applied_at'))) {
            return array_replace($metadata, [
                'occupancy_update_status' => 'already_applied',
                'occupancy_update_message' => data_get($transaction->metadata, 'occupancy_update_message', 'Listing occupancy was already updated for this payment.'),
            ]);
        }

        $occupanciesAvailable = Schema::hasTable('occupancies');
        $timestamp = now();
        $activeOccupancy = null;

        if ($occupanciesAvailable && $transaction->payer_id) {
            $activeOccupancy = Occupancy::query()
                ->where('property_id', $property->getKey())
                ->where('tenant_id', $transaction->payer_id)
                ->active()
                ->latest('started_at')
                ->first();
        }

        if ($activeOccupancy) {
            $nextDueAt = $timestamp->copy()->addMonthsNoOverflow($activeOccupancy->paymentCycleMonths());

            $activeOccupancy->forceFill([
                'last_payment_at' => $timestamp,
                'next_payment_due_at' => $nextDueAt,
                'payment_transaction_id' => $transaction->getKey(),
            ])->save();

            return array_replace($metadata, [
                'occupancy_update_status' => 'existing_occupancy_updated',
                'occupancy_applied_at' => $timestamp->toIso8601String(),
                'occupancy_id' => $activeOccupancy->getKey(),
                'occupancy_update_message' => 'Rent payment verified. Occupancy record updated for the next payment cycle.',
            ]);
        }

        $unitsRequested = max(1, (int) data_get($transaction->metadata, 'units_reserved', 1));
        $availableUnits = (int) $property->available_units;
        $unitsApplied = min($unitsRequested, $availableUnits);

        if ($unitsApplied <= 0) {
            return array_replace($metadata, [
                'occupancy_update_status' => 'skipped_no_available_units',
                'occupancy_update_message' => 'Payment is confirmed, but no additional unit could be deducted because the listing is already fully occupied.',
            ]);
        }

        $property->forceFill([
            'occupied_units' => min((int) $property->total_units, (int) $property->occupied_units + $unitsApplied),
        ])->save();

        if ($occupanciesAvailable && $transaction->payer_id) {
            $startedAt = $timestamp;
            $nextDueAt = $timestamp->copy()->addMonthsNoOverflow(12);

            $occupancy = Occupancy::query()->create([
                'property_id' => $property->getKey(),
                'tenant_id' => $transaction->payer_id,
                'payment_transaction_id' => $transaction->getKey(),
                'status' => 'active',
                'units' => $unitsApplied,
                'payment_cycle_months' => 12,
                'started_at' => $startedAt,
                'last_payment_at' => $startedAt,
                'next_payment_due_at' => $nextDueAt,
            ]);
        } else {
            $occupancy = null;
        }

        $metadata = array_replace($metadata, [
            'occupancy_update_status' => 'applied',
            'occupancy_applied_at' => $timestamp->toIso8601String(),
            'occupancy_units_applied' => $unitsApplied,
            'property_occupied_units' => (int) $property->fresh()->occupied_units,
            'property_available_units' => (int) $property->fresh()->available_units,
            'occupancy_id' => $occupancy?->getKey(),
            'occupancy_update_message' => $unitsApplied === 1
                ? 'Rent payment confirmed. Listing availability has been reduced by 1 unit.'
                : "Rent payment confirmed. Listing availability has been reduced by {$unitsApplied} units.",
        ]);

        return $this->attachRentNotifications($transaction, $metadata);
    }

    protected function applyPurchaseEffects(PaymentTransaction $transaction, array $metadata = []): array
    {
        if (! $transaction->property_id) {
            return $metadata;
        }

        $property = Property::query()->lockForUpdate()->find($transaction->property_id);

        if (! $property) {
            return array_replace($metadata, [
                'purchase_update_status' => 'property_not_found',
            ]);
        }

        if ($property->listing_intent !== 'for_sale') {
            return array_replace($metadata, [
                'purchase_update_status' => 'skipped_not_for_sale',
                'purchase_update_message' => 'Purchase effects were skipped because this listing is not for sale.',
            ]);
        }

        if (filled(data_get($transaction->metadata, 'purchase_applied_at'))) {
            return array_replace($metadata, [
                'purchase_update_status' => 'already_applied',
                'purchase_update_message' => data_get($transaction->metadata, 'purchase_update_message', 'Purchase effects were already applied for this payment.'),
            ]);
        }

        $purchaseType = $property->property_type === 'land' ? 'land' : 'house';
        $unitsRequested = max(1, (int) data_get($transaction->metadata, 'units_reserved', 1));
        $availableUnits = (int) $property->available_units;

        if ($availableUnits <= 0) {
            return array_replace($metadata, [
                'purchase_update_status' => 'skipped_no_available_units',
                'purchase_update_message' => 'Purchase is confirmed, but no available units remain to mark as sold.',
            ]);
        }

        $unitsApplied = $purchaseType === 'house'
            ? min(max(1, (int) $property->total_units), $availableUnits)
            : min($unitsRequested, $availableUnits);

        if ($unitsApplied <= 0) {
            return array_replace($metadata, [
                'purchase_update_status' => 'skipped_no_units_applied',
                'purchase_update_message' => 'Purchase is confirmed, but no available units could be deducted.',
            ]);
        }

        $property->forceFill([
            'occupied_units' => min((int) $property->total_units, (int) $property->occupied_units + $unitsApplied),
        ])->save();

        $purchasesAvailable = Schema::hasTable('property_purchases');
        $purchaseRecord = null;

        if ($purchasesAvailable && $transaction->payer_id) {
            $purchaseRecord = PropertyPurchase::query()->create([
                'property_id' => $property->getKey(),
                'buyer_id' => $transaction->payer_id,
                'payment_transaction_id' => $transaction->getKey(),
                'purchase_type' => $purchaseType,
                'status' => 'confirmed',
                'units' => $unitsApplied,
                'gross_amount' => $transaction->gross_amount,
                'currency' => $transaction->currency ?? 'NGN',
                'purchased_at' => now(),
            ]);
        }

        $metadata = array_replace($metadata, [
            'purchase_update_status' => 'applied',
            'purchase_applied_at' => now()->toIso8601String(),
            'purchase_units_applied' => $unitsApplied,
            'purchase_record_id' => $purchaseRecord?->getKey(),
            'property_occupied_units' => (int) $property->fresh()->occupied_units,
            'property_available_units' => (int) $property->fresh()->available_units,
            'purchase_update_message' => $purchaseType === 'land'
                ? "Purchase confirmed. Listing availability has been reduced by {$unitsApplied} unit".($unitsApplied === 1 ? '' : 's').'.'
                : 'Purchase confirmed. Listing is now marked as sold.',
        ]);

        return $this->attachPurchaseNotifications($transaction, $metadata, $purchaseRecord);
    }

    protected function attachRentNotifications(PaymentTransaction $transaction, array $metadata): array
    {
        if (! Schema::hasTable('user_notifications')) {
            return $metadata;
        }

        if (filled(data_get($transaction->metadata, 'rent_notification_sent_at'))) {
            return $metadata;
        }

        $property = $transaction->property;
        $tenant = $transaction->payer;

        if ($tenant) {
            UserNotification::create([
                'user_id' => $tenant->getKey(),
                'title' => 'Rent payment confirmed',
                'body' => $property ? "Your rent payment for {$property->title} is confirmed." : 'Your rent payment is confirmed.',
                'category' => 'payment_confirmed',
                'link' => $property ? route('tenant.payments.index', ['reference' => $transaction->reference]) : null,
            ]);
        }

        if ($property?->landlord_id) {
            UserNotification::create([
                'user_id' => $property->landlord_id,
                'title' => 'Rent payment confirmed',
                'body' => $property ? "A rent payment for {$property->title} was confirmed." : 'A rent payment was confirmed.',
                'category' => 'payment_confirmed',
                'link' => route('landlord.payments.index', ['reference' => $transaction->reference]),
            ]);
        }

        User::role(['admin', 'staff'])->get()->each(function (User $admin) use ($transaction, $property): void {
            UserNotification::create([
                'user_id' => $admin->getKey(),
                'title' => 'Rent payment confirmed',
                'body' => $property ? "Rent payment confirmed for {$property->title}." : 'Rent payment confirmed.',
                'category' => 'payment_confirmed',
                'link' => route('admin.payments.index', ['reference' => $transaction->reference]),
            ]);
        });

        $metadata['rent_notification_sent_at'] = now()->toIso8601String();

        return $metadata;
    }

    protected function attachPurchaseNotifications(PaymentTransaction $transaction, array $metadata, ?PropertyPurchase $purchaseRecord): array
    {
        if (! Schema::hasTable('user_notifications')) {
            return $metadata;
        }

        if (filled(data_get($transaction->metadata, 'purchase_notification_sent_at'))) {
            return $metadata;
        }

        $property = $transaction->property;
        $tenant = $transaction->payer;

        if ($tenant) {
            UserNotification::create([
                'user_id' => $tenant->getKey(),
                'title' => 'Purchase confirmed',
                'body' => $property ? "Your purchase for {$property->title} is confirmed." : 'Your purchase is confirmed.',
                'category' => 'payment_confirmed',
                'link' => $purchaseRecord ? route('tenant.purchases.show', $purchaseRecord) : null,
            ]);
        }

        if ($property?->landlord_id) {
            UserNotification::create([
                'user_id' => $property->landlord_id,
                'title' => 'Purchase confirmed',
                'body' => $property ? "A purchase for {$property->title} was confirmed." : 'A purchase was confirmed.',
                'category' => 'payment_confirmed',
                'link' => route('landlord.payments.index', ['reference' => $transaction->reference]),
            ]);
        }

        User::role(['admin', 'staff'])->get()->each(function (User $admin) use ($transaction, $property): void {
            UserNotification::create([
                'user_id' => $admin->getKey(),
                'title' => 'Purchase confirmed',
                'body' => $property ? "Purchase confirmed for {$property->title}." : 'Purchase confirmed.',
                'category' => 'payment_confirmed',
                'link' => route('admin.payments.index', ['reference' => $transaction->reference]),
            ]);
        });

        $metadata['purchase_notification_sent_at'] = now()->toIso8601String();

        return $metadata;
    }
}
