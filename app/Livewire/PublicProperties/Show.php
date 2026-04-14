<?php

namespace App\Livewire\PublicProperties;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\InspectionRequest;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Support\Currency;
use App\Support\InspectionRequestOptions;
use App\Support\Payments\PaymentGatewayManager;
use App\Support\TermsGateService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    use InteractsWithAuthenticatedUser;
    use InteractsWithRoleShells;

    public Property $property;

    public bool $hasOpenInspectionRequest = false;

    public bool $inspectionRequestsAvailable = true;

    public bool $savedPropertiesAvailable = true;

    public bool $paymentTransactionsAvailable = true;

    public bool $isSavedByCurrentTenant = false;

    public ?InspectionRequest $latestInspectionRequest = null;

    public ?PaymentTransaction $latestInspectionPaymentTransaction = null;

    public ?PaymentTransaction $latestRentPaymentTransaction = null;

    public ?PaymentTransaction $latestPurchasePaymentTransaction = null;

    public function mount(Property $property): void
    {
        abort_unless($property->isPubliclyVisible(), 404);

        $this->property = $property->loadMissing(['images']);
        $this->inspectionRequestsAvailable = Schema::hasTable('inspection_requests');
        $this->savedPropertiesAvailable = Schema::hasTable('saved_properties');
        $this->paymentTransactionsAvailable = Schema::hasTable('payment_transactions');

        if (Auth::check() && $this->currentUser()->isTenant()) {
            if ($this->inspectionRequestsAvailable) {
                $this->latestInspectionRequest = $this->property->inspectionRequests()
                    ->where('tenant_id', $this->currentUserId())
                    ->latest('created_at')
                    ->first();

                $this->hasOpenInspectionRequest = $this->property->inspectionRequests()
                    ->where('tenant_id', $this->currentUserId())
                    ->open()
                    ->exists();
            }

            if ($this->savedPropertiesAvailable) {
                $this->isSavedByCurrentTenant = $this->currentUser()
                    ->savedProperties()
                    ->whereKey($this->property->getKey())
                    ->exists();
            }

            if ($this->paymentTransactionsAvailable) {
                $this->latestInspectionPaymentTransaction = PaymentTransaction::query()
                    ->where('payer_id', $this->currentUserId())
                    ->where('property_id', $this->property->getKey())
                    ->where('transaction_type', 'inspection_booking_fee')
                    ->latest('created_at')
                    ->first();

                $this->latestRentPaymentTransaction = PaymentTransaction::query()
                    ->where('payer_id', $this->currentUserId())
                    ->where('property_id', $this->property->getKey())
                    ->where('transaction_type', 'rent_payment')
                    ->latest('created_at')
                    ->first();

                $this->latestPurchasePaymentTransaction = PaymentTransaction::query()
                    ->where('payer_id', $this->currentUserId())
                    ->where('property_id', $this->property->getKey())
                    ->whereIn('transaction_type', ['house_purchase_payment', 'land_purchase_payment', 'purchase_payment'])
                    ->latest('created_at')
                    ->first();
            }
        }
    }

    public function toggleSavedProperty(): void
    {
        abort_unless(Auth::check() && $this->currentUser()->isTenant(), 403);

        if (! $this->savedPropertiesAvailable) {
            session()->flash('status', 'Saved listings are not available yet in this environment.');

            return;
        }

        if ($this->isSavedByCurrentTenant) {
            $this->currentUser()->savedProperties()->detach($this->property->getKey());
            $this->isSavedByCurrentTenant = false;
            session()->flash('status', 'Listing removed from your saved properties.');

            return;
        }

        $this->currentUser()->savedProperties()->syncWithoutDetaching([$this->property->getKey()]);
        $this->isSavedByCurrentTenant = true;
        session()->flash('status', 'Listing saved successfully.');
    }

    public function render(): View
    {
        if (Auth::check() && $this->currentUser()->isTenant()) {
            return view('livewire.public-properties.show', [
                'property' => $this->property,
                'inspectionRequestsAvailable' => $this->inspectionRequestsAvailable,
                'savedPropertiesAvailable' => $this->savedPropertiesAvailable,
                'paymentTransactionsAvailable' => $this->paymentTransactionsAvailable,
                'latestInspectionRequest' => $this->latestInspectionRequest,
                'latestInspectionPaymentTransaction' => $this->latestInspectionPaymentTransaction,
                'latestRentPaymentTransaction' => $this->latestRentPaymentTransaction,
                'latestPurchasePaymentTransaction' => $this->latestPurchasePaymentTransaction,
            ])->layout('layouts.dashboard-shell', $this->tenantShell('Property Details'));
        }

        if (Auth::check() && $this->currentUser()->isLandlord()) {
            return view('livewire.public-properties.show-workspace', [
                'property' => $this->property,
            ])->layout('layouts.dashboard-shell', $this->landlordShell('Public Listing'));
        }

        if (Auth::check() && $this->currentUser()->hasAnyRole(['admin', 'staff'])) {
            return view('livewire.public-properties.show-workspace', [
                'property' => $this->property,
            ])->layout('components.admin-layout', [
                'pageHeading' => 'Public Listing',
            ]);
        }

        return view('livewire.public-properties.show-public', [
            'property' => $this->property,
        ])->layout('layouts.app');
    }

    public function formatMoney(float|int|string|null $amount): string
    {
        return Currency::format($amount);
    }

    public function inspectionActionCopy(): string
    {
        if (! $this->inspectionRequestsAvailable) {
            return 'Inspection requests are not available yet in this environment.';
        }

        if ($this->hasOpenInspectionRequest && $this->latestInspectionRequest) {
            return 'You already have an active request here. Open it to track payment, scheduling, and the next update.';
        }

        if ($this->latestInspectionRequest) {
            return 'You can request another visit after your earlier request is closed.';
        }

        return 'Send your request to VerifyHomes. We handle scheduling with the landlord.';
    }

    public function inspectionPaymentStatusCopy(): string
    {
        if (! $this->paymentTransactionsAvailable) {
            return 'Payment history is not available yet in this environment.';
        }

        if (! $this->latestInspectionPaymentTransaction) {
            return 'Payment updates show here after checkout starts.';
        }

        return match ($this->latestInspectionPaymentTransaction->status) {
            'paid' => 'Booking fee confirmed. Your request is moving forward.',
            'initiated', 'pending' => 'Waiting for payment confirmation.',
            'failed' => 'The last payment did not go through.',
            default => 'Payment updates show here.',
        };
    }

    public function rentPaymentStatusCopy(): string
    {
        if (! $this->paymentTransactionsAvailable) {
            return 'Rent payment history is not available yet in this environment.';
        }

        if ($this->latestRentPaymentTransaction) {
            return match ($this->latestRentPaymentTransaction->status) {
                'initiated' => 'Rent checkout started. Finish the provider step to complete payment.',
                'pending' => 'Rent checkout returned and is awaiting final verification.',
                'paid' => 'Rent payment confirmed.',
                'failed' => $this->isEligibleForRentPayment()
                    ? 'The last rent payment did not go through. You can start a new checkout when you are ready.'
                    : 'The last rent payment did not go through.',
                default => 'Rent payment updates will appear here.',
            };
        }

        if ($this->property->listing_intent !== 'for_rent') {
            return 'Rent payment is only available on rental listings.';
        }

        if ($this->property->available_units <= 0) {
            return 'This listing has no available rent units right now.';
        }

        if (! $this->latestInspectionRequest) {
            return 'Rent payment becomes available after your inspection request is completed.';
        }

        if (! InspectionRequestOptions::isCompleted((string) $this->latestInspectionRequest->status)) {
            return 'Rent payment becomes available after your inspection is completed.';
        }

        if (! $this->inspectionOutcomeAllowsRentProgression()) {
            return 'This inspection outcome does not unlock rent payment yet.';
        }

        return 'Rent payment has not started yet.';
    }

    public function purchasePaymentStatusCopy(): string
    {
        if (! $this->paymentTransactionsAvailable) {
            return 'Purchase payment history is not available yet in this environment.';
        }

        if ($this->property->listing_intent !== 'for_sale') {
            return 'Purchase payment is only available on sale listings.';
        }

        if ($this->latestPurchasePaymentTransaction) {
            return match ($this->latestPurchasePaymentTransaction->status) {
                'initiated' => 'Purchase checkout started. Finish the provider step to complete payment.',
                'pending' => 'Purchase checkout returned and is awaiting final verification.',
                'paid' => 'Purchase payment confirmed.',
                'failed' => $this->isEligibleForPurchasePayment()
                    ? 'The last purchase payment did not go through. You can start a new checkout when you are ready.'
                    : 'The last purchase payment did not go through.',
                default => 'Purchase payment updates will appear here.',
            };
        }

        if ($this->property->available_units <= 0) {
            return 'This listing has no available units right now.';
        }

        if (! $this->latestInspectionRequest) {
            return 'Purchase payment becomes available after your inspection request is completed.';
        }

        if (! InspectionRequestOptions::isCompleted((string) $this->latestInspectionRequest->status)) {
            return 'Purchase payment becomes available after your inspection is completed.';
        }

        if (! $this->inspectionOutcomeAllowsRentProgression()) {
            return 'This inspection outcome does not unlock purchase payment yet.';
        }

        return 'Purchase payment has not started yet.';
    }

    public function rentPaymentActionCopy(): string
    {
        if ($this->property->listing_intent !== 'for_rent') {
            return 'Rent payment is only available on rental listings.';
        }

        if ($this->property->available_units <= 0 && ! $this->latestRentPaymentTransaction) {
            return 'This listing has no available units right now.';
        }

        if ($this->latestRentPaymentTransaction) {
            return match ($this->latestRentPaymentTransaction->status) {
                'initiated' => 'Return to checkout and finish the provider step.',
                'pending' => 'Checkout returned. We are waiting for provider confirmation.',
                'paid' => 'Your rent payment is complete for this listing.',
                'failed' => $this->isEligibleForRentPayment()
                    ? 'Your inspection is complete. You can start a new rent checkout when you are ready.'
                    : 'Rent payment stays locked until inspection progression is complete.',
                default => 'Review the current rent payment status before taking the next step.',
            };
        }

        if (! $this->latestInspectionRequest) {
            return 'Request and complete an inspection before rent payment becomes available.';
        }

        if (! InspectionRequestOptions::isCompleted((string) $this->latestInspectionRequest->status)) {
            return 'Complete the inspection workflow first. Rent payment comes later in the process.';
        }

        if (! $this->inspectionOutcomeAllowsRentProgression()) {
            return 'This inspection outcome does not allow rent payment progression yet.';
        }

        return 'Your inspection is complete. You can now pay rent for this listing.';
    }

    public function purchasePaymentActionCopy(): string
    {
        if ($this->property->listing_intent !== 'for_sale') {
            return 'Purchase payment is only available on sale listings.';
        }

        if ($this->property->available_units <= 0 && ! $this->latestPurchasePaymentTransaction) {
            return 'This listing has no available units right now.';
        }

        if ($this->latestPurchasePaymentTransaction) {
            return match ($this->latestPurchasePaymentTransaction->status) {
                'initiated' => 'Return to checkout and finish the provider step.',
                'pending' => 'Checkout returned. We are waiting for provider confirmation.',
                'paid' => 'Your purchase payment is complete for this listing.',
                'failed' => $this->isEligibleForPurchasePayment()
                    ? 'Your inspection is complete. You can start a new purchase checkout when you are ready.'
                    : 'Purchase payment stays locked until inspection progression is complete.',
                default => 'Review the current purchase payment status before taking the next step.',
            };
        }

        if (! $this->latestInspectionRequest) {
            return 'Request and complete an inspection before purchase payment becomes available.';
        }

        if (! InspectionRequestOptions::isCompleted((string) $this->latestInspectionRequest->status)) {
            return 'Complete the inspection workflow first. Purchase payment comes later in the process.';
        }

        if (! $this->inspectionOutcomeAllowsRentProgression()) {
            return 'This inspection outcome does not allow purchase progression yet.';
        }

        return 'Your inspection is complete. You can now pay the purchase price for this listing.';
    }

    public function canStartRentPayment(): bool
    {
        if (! $this->isEligibleForRentPayment()) {
            return false;
        }

        return ! $this->latestRentPaymentTransaction
            || $this->latestRentPaymentTransaction->status === 'failed';
    }

    public function canStartPurchasePayment(): bool
    {
        if (! $this->isEligibleForPurchasePayment()) {
            return false;
        }

        return ! $this->latestPurchasePaymentTransaction
            || $this->latestPurchasePaymentTransaction->status === 'failed';
    }

    public function canContinueCheckout(?PaymentTransaction $transaction): bool
    {
        return $transaction instanceof PaymentTransaction
            && in_array($transaction->status, ['initiated', 'pending'], true)
            && filled(data_get($transaction->metadata, 'checkout_url'));
    }

    public function availabilityMessage(): string
    {
        return $this->property->availabilityLabel();
    }

    public function availabilityDetail(): string
    {
        return $this->property->availabilityDetail();
    }

    public function providerLabel(?string $provider): string
    {
        return app(PaymentGatewayManager::class)->label($provider);
    }

    public function inspectionTermsGate(): string
    {
        return 'inspection-request:property:'.$this->property->getKey();
    }

    public function inspectionTermsReady(): bool
    {
        return app(TermsGateService::class)->isCompleted($this->inspectionTermsGate());
    }

    public function inspectionTermsSecondsRemaining(): int
    {
        return app(TermsGateService::class)->secondsRemaining($this->inspectionTermsGate());
    }

    public function inspectionOutcomeAllowsRentProgression(): bool
    {
        return $this->latestInspectionRequest?->outcome_type === 'inspected';
    }

    protected function isEligibleForRentPayment(): bool
    {
        if ($this->property->listing_intent !== 'for_rent' || $this->property->available_units <= 0) {
            return false;
        }

        if (! $this->latestInspectionRequest) {
            return false;
        }

        if (! InspectionRequestOptions::isCompleted((string) $this->latestInspectionRequest->status)) {
            return false;
        }

        if (! $this->inspectionOutcomeAllowsRentProgression()) {
            return false;
        }

        return ! ($this->latestRentPaymentTransaction && $this->latestRentPaymentTransaction->status === 'paid');
    }

    protected function isEligibleForPurchasePayment(): bool
    {
        if ($this->property->listing_intent !== 'for_sale' || $this->property->available_units <= 0) {
            return false;
        }

        if (! $this->latestInspectionRequest) {
            return false;
        }

        if (! InspectionRequestOptions::isCompleted((string) $this->latestInspectionRequest->status)) {
            return false;
        }

        if (! $this->inspectionOutcomeAllowsRentProgression()) {
            return false;
        }

        return ! ($this->latestPurchasePaymentTransaction && $this->latestPurchasePaymentTransaction->status === 'paid');
    }

    public function leaseStatusCopy(): string
    {
        if (! $this->inspectionRequestsAvailable) {
            return 'Inspection requests are not available yet in this environment.';
        }

        if (! $this->latestInspectionRequest) {
            return 'Lease coordination becomes available after your inspection request is completed.';
        }

        if (! InspectionRequestOptions::isCompleted((string) $this->latestInspectionRequest->status)) {
            return 'Complete the inspection workflow first. Lease coordination comes next.';
        }

        if (! $this->inspectionOutcomeAllowsRentProgression()) {
            return 'This inspection outcome does not unlock lease coordination yet.';
        }

        return 'Inspection is complete. VerifyHomes will guide the lease coordination and next step.';
    }

    public function leaseActionCopy(): string
    {
        if (! $this->latestInspectionRequest) {
            return 'Request and complete an inspection before lease coordination becomes available.';
        }

        if (! InspectionRequestOptions::isCompleted((string) $this->latestInspectionRequest->status)) {
            return 'Complete the inspection workflow first. Lease coordination comes later in the process.';
        }

        if (! $this->inspectionOutcomeAllowsRentProgression()) {
            return 'This inspection outcome does not allow lease coordination yet.';
        }

        return 'Your inspection is complete. VerifyHomes will guide the lease coordination from here.';
    }
}
