<?php

namespace App\Support;

use App\Models\Occupancy;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\PropertyPurchase;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class PaymentCompletionService
{
    public function applyPaidEffects(PaymentTransaction $transaction, array $metadata = []): array
    {
        if ($transaction->transaction_type === 'inspection_booking_fee') {
            return $this->attachInspectionPaymentNotifications($transaction, $metadata);
        }

        if (in_array($transaction->transaction_type, ['house_purchase_payment', 'land_purchase_payment', 'purchase_payment'], true)) {
            return $this->applyPurchaseEffects($transaction, $metadata);
        }

        if ($transaction->transaction_type !== 'rent_payment' || ! $transaction->property_id) {
            return $metadata;
        }

        if ($transaction->payer_id) {
            // Serialize rent completion per tenant so two callbacks cannot create two upcoming stays.
            User::query()->lockForUpdate()->find($transaction->payer_id);
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
        $upcomingOccupancy = null;

        if ($occupanciesAvailable && $transaction->payer_id) {
            $activeOccupancy = Occupancy::query()
                ->where('tenant_id', $transaction->payer_id)
                ->active()
                ->lockForUpdate()
                ->latest('started_at')
                ->first();
            $upcomingOccupancy = Occupancy::query()
                ->where('tenant_id', $transaction->payer_id)
                ->upcoming()
                ->lockForUpdate()
                ->latest('created_at')
                ->first();
        }

        if ($activeOccupancy && $activeOccupancy->property_id === $property->getKey()) {
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

        if ($upcomingOccupancy) {
            return array_replace($metadata, [
                'occupancy_update_status' => 'skipped_upcoming_occupancy_exists',
                'occupancy_update_message' => 'Payment is confirmed, but this tenant already has an upcoming rental secured.',
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

        $isUpcoming = $activeOccupancy !== null;

        $property->forceFill($isUpcoming
            ? ['reserved_units' => min((int) $property->total_units, (int) $property->reserved_units + $unitsApplied)]
            : ['occupied_units' => min((int) $property->total_units, (int) $property->occupied_units + $unitsApplied)])
            ->save();

        if ($occupanciesAvailable && $transaction->payer_id) {
            $startedAt = $isUpcoming ? null : $timestamp;
            $nextDueAt = $isUpcoming ? null : $timestamp->copy()->addMonthsNoOverflow(12);

            $occupancy = Occupancy::query()->create([
                'property_id' => $property->getKey(),
                'tenant_id' => $transaction->payer_id,
                'payment_transaction_id' => $transaction->getKey(),
                'status' => $isUpcoming ? 'upcoming' : 'active',
                'units' => $unitsApplied,
                'payment_cycle_months' => 12,
                'started_at' => $startedAt,
                'last_payment_at' => $isUpcoming ? null : $startedAt,
                'next_payment_due_at' => $nextDueAt,
            ]);
        } else {
            $occupancy = null;
        }

        $metadata = array_replace($metadata, [
            'occupancy_update_status' => $isUpcoming ? 'upcoming_reserved' : 'applied',
            'occupancy_applied_at' => $timestamp->toIso8601String(),
            'occupancy_units_applied' => $unitsApplied,
            'property_occupied_units' => (int) $property->fresh()->occupied_units,
            'property_reserved_units' => (int) $property->fresh()->reserved_units,
            'property_available_units' => (int) $property->fresh()->available_units,
            'occupancy_id' => $occupancy?->getKey(),
            'occupancy_update_message' => $isUpcoming
                ? 'Rent payment confirmed. Your next rental is secured and will become active after your current stay ends.'
                : ($unitsApplied === 1
                    ? 'Rent payment confirmed. Listing availability has been reduced by 1 unit.'
                    : "Rent payment confirmed. Listing availability has been reduced by {$unitsApplied} units."),
        ]);

        return $this->attachRentNotifications($transaction, $metadata, $isUpcoming);
    }

    protected function attachInspectionPaymentNotifications(PaymentTransaction $transaction, array $metadata): array
    {
        if (! Schema::hasTable('user_notifications') || filled(data_get($transaction->metadata, 'inspection_payment_notification_sent_at'))) {
            return $metadata;
        }

        $request = $transaction->inspectionRequest;
        $property = $transaction->property;
        $notifier = app(WorkflowNotifier::class);

        if ($transaction->payer) {
            $notifier->notify(
                $transaction->payer,
                'inspection-booking-confirmed:'.$transaction->getKey(),
                'Inspection booking confirmed',
                $property ? "Your booking for {$property->title} is confirmed. Your inspection remains booked for the scheduled time." : 'Your inspection booking is confirmed.',
                $request ? route('tenant.inspection-requests.show', ['inspectionRequestId' => $request->getKey()]) : route('tenant.payments.index', ['reference' => $transaction->reference]),
                'payment_confirmed',
                'View inspection',
            );
        }

        User::query()->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'staff']))->get()->each(function (User $admin) use ($notifier, $transaction, $property, $request): void {
            $notifier->notify(
                $admin,
                'inspection-booking-confirmed:'.$transaction->getKey().':admin',
                'Inspection booking confirmed',
                $property ? "Inspection booking confirmed for {$property->title}." : 'An inspection booking was confirmed.',
                $request ? route('admin.inspection-requests.show', ['inspectionRequestId' => $request->getKey()]) : route('admin.payments.index', ['reference' => $transaction->reference]),
                'payment_confirmed',
                'View inspection',
            );
        });

        $metadata['inspection_payment_notification_sent_at'] = now()->toIso8601String();

        return $metadata;
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

    protected function attachRentNotifications(PaymentTransaction $transaction, array $metadata, bool $isUpcoming = false): array
    {
        if (! Schema::hasTable('user_notifications')) {
            return $metadata;
        }

        if (filled(data_get($transaction->metadata, 'rent_notification_sent_at'))) {
            return $metadata;
        }

        $property = $transaction->property;
        $tenant = $transaction->payer;
        $notifier = app(WorkflowNotifier::class);

        if ($tenant) {
            $notifier->notify(
                $tenant,
                'rent-payment-confirmed:'.$transaction->getKey(),
                $isUpcoming ? 'Your next rental is secured' : 'Rent payment confirmed',
                $isUpcoming
                    ? ($property ? "Your next rental at {$property->title} is secured and will become active after your current stay ends." : 'Your next rental is secured.')
                    : ($property ? "Your rent payment for {$property->title} is confirmed." : 'Your rent payment is confirmed.'),
                route('tenant.occupancy.index'),
                'payment_confirmed',
                $isUpcoming ? 'View Upcoming Stay' : 'View My Stay',
            );
        }

        if ($property?->landlord_id) {
            $notifier->notify(
                $property->landlord,
                'rent-payment-confirmed:'.$transaction->getKey().':landlord',
                $isUpcoming ? 'Upcoming rental secured' : 'Rent payment confirmed',
                $isUpcoming
                    ? ($property ? "An upcoming rental was secured for {$property->title}." : 'An upcoming rental was secured.')
                    : ($property ? "A rent payment for {$property->title} was confirmed." : 'A rent payment was confirmed.'),
                route('landlord.payments.index', ['reference' => $transaction->reference]),
                'payment_confirmed',
                'View payments',
            );
        }

        User::role(['admin', 'staff'])->get()->each(function (User $admin) use ($notifier, $transaction, $property, $isUpcoming): void {
            $notifier->notify(
                $admin,
                'rent-payment-confirmed:'.$transaction->getKey().':admin',
                $isUpcoming ? 'Upcoming rental secured' : 'Rent payment confirmed',
                $isUpcoming
                    ? ($property ? "An upcoming rental was secured for {$property->title}." : 'An upcoming rental was secured.')
                    : ($property ? "Rent payment confirmed for {$property->title}." : 'Rent payment confirmed.'),
                route('admin.payments.index', ['reference' => $transaction->reference]),
                'payment_confirmed',
                'View payment',
            );
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
        $notifier = app(WorkflowNotifier::class);
        $tenantTitle = $property?->property_type === 'land' ? 'Land purchase confirmed' : 'Purchase confirmed';

        if ($tenant) {
            $notifier->notify(
                $tenant,
                'purchase-confirmed:'.$transaction->getKey(),
                $tenantTitle,
                $property ? "Your purchase for {$property->title} is confirmed." : 'Your purchase is confirmed.',
                $purchaseRecord ? route('tenant.purchases.show', $purchaseRecord) : route('tenant.payments.index', ['reference' => $transaction->reference]),
                'payment_confirmed',
                'View receipt',
            );
        }

        if ($property?->landlord_id) {
            $notifier->notify($property->landlord, 'purchase-confirmed:'.$transaction->getKey().':landlord', 'Purchase confirmed', $property ? "A purchase for {$property->title} was confirmed." : 'A purchase was confirmed.', route('landlord.payments.index', ['reference' => $transaction->reference]), 'payment_confirmed', 'View payments');
        }

        User::role(['admin', 'staff'])->get()->each(function (User $admin) use ($notifier, $transaction, $property): void {
            $notifier->notify($admin, 'purchase-confirmed:'.$transaction->getKey().':admin', 'Purchase confirmed', $property ? "Purchase confirmed for {$property->title}." : 'Purchase confirmed.', route('admin.payments.index', ['reference' => $transaction->reference]), 'payment_confirmed', 'View payment');
        });

        $metadata['purchase_notification_sent_at'] = now()->toIso8601String();

        return $metadata;
    }
}
