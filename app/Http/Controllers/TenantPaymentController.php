<?php

namespace App\Http\Controllers;

use App\Models\InspectionRequest;
use App\Models\Property;
use App\Models\PaymentTransaction;
use App\Support\PaymentCheckoutService;
use App\Support\InspectionRequestOptions;
use App\Support\Payments\PaymentGatewayManager;
use App\Support\TermsGateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;

class TenantPaymentController extends Controller
{
    public function storeInspectionRequestPayment(
        InspectionRequest $inspectionRequest,
        PaymentCheckoutService $checkoutService,
        PaymentGatewayManager $paymentGatewayManager,
        TermsGateService $termsGateService,
    ): RedirectResponse {
        abort_unless($inspectionRequest->tenant_id === auth()->id(), 404);

        request()->validate([
            'accepted_inspection_terms' => ['accepted'],
        ], [
            'accepted_inspection_terms.accepted' => 'Accept the inspection terms before starting payment checkout.',
        ]);

        if (! $termsGateService->isCompleted('inspection-payment:request:'.$inspectionRequest->getKey())) {
            return redirect()
                ->route('tenant.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()])
                ->withErrors([
                    'accepted_inspection_terms' => 'Please read the inspection terms before continuing.',
                ])
                ->withInput();
        }

        if (! Schema::hasTable('payment_transactions')) {
            return redirect()
                ->route('tenant.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()])
                ->with('status', 'Payments are not available yet in this environment.');
        }

        if (! $this->payerHasValidEmail()) {
            return redirect()
                ->route('tenant.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()])
                ->with('status', 'Add a valid email address to your account before starting payment.');
        }

        try {
            $transaction = $checkoutService->initiateInspectionRequestPayment($inspectionRequest, auth()->user());
        } catch (\Throwable $throwable) {
            report($throwable);

            return redirect()
                ->route('tenant.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()])
                ->with('status', 'We could not connect to the payment provider right now. Please try again.');
        }

        if (! $transaction) {
            return redirect()
                ->route('tenant.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()])
                ->with('status', 'We could not start the payment right now. Please try again.');
        }

        $termsGateService->clear('inspection-payment:request:'.$inspectionRequest->getKey());

        $checkoutUrl = (string) data_get($transaction->metadata, 'checkout_url', '');

        if ($checkoutUrl !== '') {
            return redirect()->away($checkoutUrl);
        }

        $gatewayLabel = $transaction->metadata['gateway_label']
            ?? $paymentGatewayManager->label($transaction->provider);

        return redirect()
            ->route('tenant.payments.index', ['reference' => $transaction->reference])
            ->with('status', "Payment checkout started with {$gatewayLabel}. We will update the transaction here once verified gateway confirmation arrives.");
    }

    public function handlePaymentCallback(
        PaymentCheckoutService $checkoutService,
    ): RedirectResponse {
        $reference = (string) request()->query('reference', '');

        if ($reference === '') {
            return redirect()
                ->route('tenant.payments.index')
                ->with('status', 'Payment callback returned without a transaction reference.');
        }

        $transaction = $checkoutService->verifyPaymentForPayer($reference, auth()->user());

        if (! $transaction) {
            return redirect()
                ->route('tenant.payments.index')
                ->with('status', 'We could not verify that payment record from the callback.');
        }

        if (in_array($transaction->transaction_type, ['house_purchase_payment', 'land_purchase_payment', 'purchase_payment'], true)
            && $transaction->status === 'paid'
            && Schema::hasTable('property_purchases')) {
            $purchase = \App\Models\PropertyPurchase::query()
                ->where('payment_transaction_id', $transaction->getKey())
                ->where('buyer_id', auth()->id())
                ->latest('purchased_at')
                ->first();

            if ($purchase) {
                return redirect()
                    ->route('tenant.purchases.show', $purchase)
                    ->with('status', 'Purchase confirmed. Your receipt is ready.');
            }
        }

        return redirect()
            ->route('tenant.payments.index', ['reference' => $transaction->reference])
            ->with('status', $this->callbackStatusMessage($transaction));
    }

    public function storeRentPayment(
        Property $property,
        PaymentCheckoutService $checkoutService,
        PaymentGatewayManager $paymentGatewayManager,
    ): RedirectResponse {
        abort_unless($property->isPubliclyVisible(), 404);

        if ($property->listing_intent !== 'for_rent') {
            return redirect()
                ->route('properties.show', $property)
                ->withErrors([
                    'property' => 'Rent payment is only available on rental listings.',
                ]);
        }

        if ($property->available_units <= 0) {
            return redirect()
                ->route('properties.show', $property)
                ->withErrors([
                    'property' => 'This listing has no available rent units right now.',
                ]);
        }

        if (! Schema::hasTable('inspection_requests')) {
            return redirect()
                ->route('properties.show', $property)
                ->with('status', 'Inspection requests are not available yet in this environment.');
        }

        if (! Schema::hasTable('payment_transactions')) {
            return redirect()
                ->route('properties.show', $property)
                ->with('status', 'Payments are not available yet in this environment.');
        }

        if (! $this->payerHasValidEmail()) {
            return redirect()
                ->route('properties.show', $property)
                ->with('status', 'Add a valid email address to your account before starting payment.');
        }

        $latestInspectionRequest = InspectionRequest::query()
            ->where('property_id', $property->getKey())
            ->where('tenant_id', auth()->id())
            ->latest('created_at')
            ->first();

        if (! $latestInspectionRequest) {
            return redirect()
                ->route('properties.show', $property)
                ->withErrors([
                    'property' => 'Request and complete an inspection before rent payment becomes available.',
                ]);
        }

        if (! InspectionRequestOptions::isCompleted((string) $latestInspectionRequest->status)) {
            return redirect()
                ->route('properties.show', $property)
                ->withErrors([
                    'property' => 'Rent payment becomes available only after your inspection request is completed.',
                ]);
        }

        if ($latestInspectionRequest->outcome_type !== 'inspected') {
            return redirect()
                ->route('properties.show', $property)
                ->withErrors([
                    'property' => 'This inspection outcome does not unlock rent payment yet.',
                ]);
        }

        $latestRentTransaction = PaymentTransaction::query()
            ->where('payer_id', auth()->id())
            ->where('property_id', $property->getKey())
            ->where('transaction_type', 'rent_payment')
            ->latest('created_at')
            ->first();

        if ($latestRentTransaction?->status === 'paid') {
            return redirect()
                ->route('tenant.payments.index', ['reference' => $latestRentTransaction->reference])
                ->with('status', 'Rent payment is already confirmed for this listing.');
        }

        try {
            $transaction = $checkoutService->initiateRentPayment($property, auth()->user());
        } catch (\Throwable $throwable) {
            report($throwable);

            return redirect()
                ->route('properties.show', $property)
                ->with('status', 'We could not connect to the payment provider right now. Please try again.');
        }

        if (! $transaction) {
            return redirect()
                ->route('properties.show', $property)
                ->with('status', 'We could not start the rent payment right now. Please try again.');
        }

        $checkoutUrl = (string) data_get($transaction->metadata, 'checkout_url', '');

        if ($checkoutUrl !== '') {
            return redirect()->away($checkoutUrl);
        }

        $gatewayLabel = $transaction->metadata['gateway_label']
            ?? $paymentGatewayManager->label($transaction->provider);

        return redirect()
            ->route('tenant.payments.index', ['reference' => $transaction->reference])
            ->with('status', "Rent payment checkout started with {$gatewayLabel}. We will update the transaction here once verified gateway confirmation arrives.");
    }

    public function storePurchasePayment(
        Property $property,
        PaymentCheckoutService $checkoutService,
        PaymentGatewayManager $paymentGatewayManager,
    ): RedirectResponse {
        abort_unless($property->isPubliclyVisible(), 404);

        if ($property->listing_intent !== 'for_sale') {
            return redirect()
                ->route('properties.show', $property)
                ->withErrors([
                    'property' => $property->listing_intent === 'for_lease'
                        ? 'Lease listings do not use purchase checkout yet. Complete inspection and we will guide the next step.'
                        : 'Purchase payment is only available on sale listings.',
                ]);
        }

        if ($property->available_units <= 0) {
            return redirect()
                ->route('properties.show', $property)
                ->withErrors([
                    'property' => 'This listing has no available units right now.',
                ]);
        }

        if (! Schema::hasTable('inspection_requests')) {
            return redirect()
                ->route('properties.show', $property)
                ->with('status', 'Inspection requests are not available yet in this environment.');
        }

        if (! Schema::hasTable('payment_transactions')) {
            return redirect()
                ->route('properties.show', $property)
                ->with('status', 'Payments are not available yet in this environment.');
        }

        if (! $this->payerHasValidEmail()) {
            return redirect()
                ->route('properties.show', $property)
                ->with('status', 'Add a valid email address to your account before starting payment.');
        }

        $latestInspectionRequest = InspectionRequest::query()
            ->where('property_id', $property->getKey())
            ->where('tenant_id', auth()->id())
            ->latest('created_at')
            ->first();

        if (! $latestInspectionRequest) {
            return redirect()
                ->route('properties.show', $property)
                ->withErrors([
                    'property' => 'Request and complete an inspection before purchase payment becomes available.',
                ]);
        }

        if (! InspectionRequestOptions::isCompleted((string) $latestInspectionRequest->status)) {
            return redirect()
                ->route('properties.show', $property)
                ->withErrors([
                    'property' => 'Purchase payment becomes available only after your inspection request is completed.',
                ]);
        }

        if ($latestInspectionRequest->outcome_type !== 'inspected') {
            return redirect()
                ->route('properties.show', $property)
                ->withErrors([
                    'property' => 'This inspection outcome does not unlock purchase payment yet.',
                ]);
        }

        $latestPurchaseTransaction = PaymentTransaction::query()
            ->where('payer_id', auth()->id())
            ->where('property_id', $property->getKey())
            ->whereIn('transaction_type', ['house_purchase_payment', 'land_purchase_payment'])
            ->latest('created_at')
            ->first();

        if ($latestPurchaseTransaction?->status === 'paid') {
            return redirect()
                ->route('tenant.payments.index', ['reference' => $latestPurchaseTransaction->reference])
                ->with('status', 'Purchase payment is already confirmed for this listing.');
        }

        $unitsRequested = 1;

        if ($property->property_type === 'land') {
            $unitsRequested = (int) request()->input('purchase_units', 1);
            $unitsRequested = max(1, $unitsRequested);

            if ($unitsRequested > $property->available_units) {
                return redirect()
                    ->route('properties.show', $property)
                    ->withErrors([
                        'purchase_units' => 'Select a valid land quantity to purchase based on availability.',
                    ])
                    ->withInput();
            }
        }

        try {
            $transaction = $checkoutService->initiatePurchasePayment($property, auth()->user(), $unitsRequested);
        } catch (\Throwable $throwable) {
            report($throwable);

            return redirect()
                ->route('properties.show', $property)
                ->with('status', 'We could not connect to the payment provider right now. Please try again.');
        }

        if (! $transaction) {
            return redirect()
                ->route('properties.show', $property)
                ->with('status', 'We could not start the purchase payment right now. Please try again.');
        }

        $checkoutUrl = (string) data_get($transaction->metadata, 'checkout_url', '');

        if ($checkoutUrl !== '') {
            return redirect()->away($checkoutUrl);
        }

        $gatewayLabel = $transaction->metadata['gateway_label']
            ?? $paymentGatewayManager->label($transaction->provider);

        return redirect()
            ->route('tenant.payments.index', ['reference' => $transaction->reference])
            ->with('status', "Purchase payment checkout started with {$gatewayLabel}. We will update the transaction here once verified gateway confirmation arrives.");
    }

    protected function callbackStatusMessage($transaction): string
    {
        $context = match ($transaction->transaction_type) {
            'rent_payment' => 'rent payment',
            'house_purchase_payment', 'land_purchase_payment', 'purchase_payment' => 'purchase payment',
            default => 'inspection payment',
        };

        return match ($transaction->status) {
            'paid' => ucfirst($context).' verified successfully.',
            'pending' => ucfirst($context).' checkout returned successfully, but provider confirmation is still pending.',
            'failed' => ucfirst($context).' checkout returned, but the payment was not confirmed successfully.',
            default => ucfirst($context).' callback received. We updated the latest transaction state where possible.',
        };
    }

    protected function payerHasValidEmail(): bool
    {
        $user = auth()->user();
        $email = is_string($user?->email) ? trim($user->email) : '';

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
