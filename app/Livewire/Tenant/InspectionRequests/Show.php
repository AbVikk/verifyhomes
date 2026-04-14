<?php

namespace App\Livewire\Tenant\InspectionRequests;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\InspectionRequest;
use App\Models\PaymentTransaction;
use App\Support\Currency;
use App\Support\InspectionRequestOptions;
use App\Support\Payments\PaymentGatewayManager;
use App\Support\TermsGateService;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    use InteractsWithAuthenticatedUser;
    use InteractsWithRoleShells;

    public ?InspectionRequest $inspectionRequest = null;

    public function mount(?InspectionRequest $inspectionRequest = null, ?string $inspectionRequestId = null): void
    {
        if (! $this->detailAvailable()) {
            return;
        }

        $inspectionRequest ??= InspectionRequest::query()->find($inspectionRequestId);

        abort_if(! $inspectionRequest, 404);
        abort_unless($inspectionRequest->tenant_id === $this->currentUserId(), 404);

        $this->inspectionRequest = $inspectionRequest;
    }

    public function render(): View
    {
        $paymentTransactionsAvailable = $this->hasPaymentTransactionsTable();
        $inspectionRequest = $this->detailAvailable() && $this->inspectionRequest
            ? $this->inspectionRequest->load(['property'])
            : $this->inspectionRequest;
        $paymentTransactions = $paymentTransactionsAvailable && $this->inspectionRequest
            ? PaymentTransaction::query()
                ->where('payer_id', $this->currentUserId())
                ->where('inspection_request_id', $this->inspectionRequest->getKey())
                ->latest('created_at')
                ->get()
            : collect();
        $latestPaymentTransaction = $paymentTransactions->first();
        $hasPaidInspectionFee = $paymentTransactions->contains(fn (PaymentTransaction $transaction) => $transaction->status === 'paid');

        return view('livewire.tenant.inspection-requests.show', [
            'inspectionRequest' => $inspectionRequest,
            'detailAvailable' => $this->detailAvailable() && $this->inspectionRequest !== null,
            'outcomes' => InspectionRequestOptions::outcomes(),
            'paymentTransactionsAvailable' => $paymentTransactionsAvailable,
            'paymentTransactions' => $paymentTransactions,
            'latestPaymentTransaction' => $latestPaymentTransaction,
            'hasPaidInspectionFee' => $hasPaidInspectionFee,
            'inspectionBookingFeeAmount' => (float) config('payments.transaction_amounts.inspection_booking_fee', 0),
        ])->layout('layouts.dashboard-shell', $this->tenantShell('Inspection Request'));
    }

    public function formatMoney(float|int|string|null $amount, string $currency = 'NGN'): string
    {
        return Currency::format($amount, $currency);
    }

    public function providerLabel(?string $provider): string
    {
        return app(PaymentGatewayManager::class)->label($provider);
    }

    public function paymentStatusSummary(?string $status): string
    {
        return match ($status) {
            'initiated' => 'Checkout started. Finish the provider step to move this request forward.',
            'pending' => 'Checkout returned. VerifyHomes is waiting for final payment confirmation.',
            'paid' => 'Payment confirmed. We are scheduling your visit.',
            'failed' => 'Payment failed. Start a new checkout when you are ready.',
            default => 'Payment has not started yet.',
        };
    }

    public function paymentStatusLabel(?string $status): string
    {
        return match ($status) {
            'initiated' => 'Checkout started',
            'pending' => 'Awaiting verification',
            'paid' => 'Payment confirmed',
            'failed' => 'Payment failed',
            default => 'Not started',
        };
    }

    public function canContinueCheckout(?PaymentTransaction $transaction): bool
    {
        return $transaction instanceof PaymentTransaction
            && in_array($transaction->status, ['initiated', 'pending'], true)
            && filled(data_get($transaction->metadata, 'checkout_url'));
    }

    public function inspectionTermsGate(): ?string
    {
        return $this->inspectionRequest
            ? 'inspection-payment:request:'.$this->inspectionRequest->getKey()
            : null;
    }

    public function inspectionTermsReady(): bool
    {
        $gate = $this->inspectionTermsGate();

        return $gate ? app(TermsGateService::class)->isCompleted($gate) : false;
    }

    public function inspectionTermsSecondsRemaining(): int
    {
        $gate = $this->inspectionTermsGate();

        return $gate ? app(TermsGateService::class)->secondsRemaining($gate) : 10;
    }

    protected function detailAvailable(): bool
    {
        return $this->hasInspectionRequestsTable() && $this->hasInspectionRequestStatusHistoriesTable();
    }

    protected function hasInspectionRequestsTable(): bool
    {
        return Schema::hasTable('inspection_requests');
    }

    protected function hasInspectionRequestStatusHistoriesTable(): bool
    {
        return Schema::hasTable('inspection_request_status_histories');
    }

    protected function hasPaymentTransactionsTable(): bool
    {
        return Schema::hasTable('payment_transactions');
    }
}
