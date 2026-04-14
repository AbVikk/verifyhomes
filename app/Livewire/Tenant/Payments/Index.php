<?php

namespace App\Livewire\Tenant\Payments;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\PaymentTransaction;
use App\Support\Currency;
use App\Support\Payments\PaymentGatewayManager;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use InteractsWithAuthenticatedUser;
    use InteractsWithRoleShells;
    use WithPagination;

    #[Url(except: 'all')]
    public string $statusFilter = 'all';

    #[Url(except: '')]
    public string $reference = '';

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $paymentsAvailable = $this->paymentsAvailable();
        $baseQuery = $paymentsAvailable
            ? PaymentTransaction::query()
                ->where('payer_id', $this->currentUserId())
                ->with(['property', 'inspectionRequest.property'])
            : null;

        $transactions = $paymentsAvailable
            ? (clone $baseQuery)
                ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
                ->latest('created_at')
                ->paginate(10)
            : $this->emptyPaginator();

        $highlightedTransaction = $paymentsAvailable && $this->reference !== ''
            ? (clone $baseQuery)->where('reference', $this->reference)->first()
            : null;

        $summary = $paymentsAvailable
            ? [
                'initiated' => (clone $baseQuery)->where('status', 'initiated')->count(),
                'pending' => (clone $baseQuery)->where('status', 'pending')->count(),
                'paid' => (clone $baseQuery)->where('status', 'paid')->count(),
                'failed' => (clone $baseQuery)->where('status', 'failed')->count(),
            ]
            : [
                'initiated' => 0,
                'pending' => 0,
                'paid' => 0,
                'failed' => 0,
            ];

        return view('livewire.tenant.payments.index', [
            'paymentsAvailable' => $paymentsAvailable,
            'transactions' => $transactions,
            'highlightedTransaction' => $highlightedTransaction,
            'summary' => $summary,
        ])->layout('layouts.dashboard-shell', $this->tenantShell('Payments'));
    }

    public function formatMoney(float|int|string|null $amount, string $currency = 'NGN'): string
    {
        return Currency::format($amount, $currency);
    }

    public function providerLabel(?string $provider): string
    {
        return app(PaymentGatewayManager::class)->label($provider);
    }

    public function statusSummary(PaymentTransaction $transaction): string
    {
        $isRentPayment = $transaction->transaction_type === 'rent_payment';
        $isPurchasePayment = in_array($transaction->transaction_type, ['house_purchase_payment', 'land_purchase_payment', 'purchase_payment'], true);

        return match ($transaction->status) {
            'initiated' => $isRentPayment
                ? 'Rent checkout started. Finish the provider step to complete payment.'
                : ($isPurchasePayment
                    ? 'Purchase checkout started. Finish the provider step to complete payment.'
                    : 'Checkout started. Finish the provider step to move this payment forward.'),
            'pending' => $isRentPayment
                ? 'Rent checkout returned, and VerifyHomes is waiting for verified gateway confirmation.'
                : ($isPurchasePayment
                    ? 'Purchase checkout returned, and VerifyHomes is waiting for verified gateway confirmation.'
                    : 'Checkout returned, and VerifyHomes is waiting for verified gateway confirmation before scheduling can move forward.'),
            'paid' => $isRentPayment
                ? 'Rent payment verified successfully and recorded as paid.'
                : ($isPurchasePayment
                    ? 'Purchase payment verified successfully and recorded as paid.'
                    : 'Payment verified successfully and recorded as paid. VerifyHomes can continue the workflow from here.'),
            'failed' => $isRentPayment
                ? 'Rent payment failed or the provider returned an unsuccessful result.'
                : ($isPurchasePayment
                    ? 'Purchase payment failed or the provider returned an unsuccessful result.'
                    : 'Payment failed or the provider returned an unsuccessful result.'),
            default => 'This transaction is recorded, but there is no extra gateway update yet.',
        };
    }

    public function transactionTypeSummary(PaymentTransaction $transaction): string
    {
        $unitsReserved = (int) data_get($transaction->metadata, 'units_reserved', 1);
        $unitSuffix = $unitsReserved > 1 ? " ({$unitsReserved} units)" : '';

        return match ($transaction->transaction_type) {
            'rent_payment' => 'Rent payment for this property listing.',
            'house_purchase_payment' => 'House purchase payment for this property listing.',
            'land_purchase_payment' => 'Land purchase payment'.$unitSuffix.' for this property listing.',
            'purchase_payment' => 'Purchase payment for this property listing.',
            'inspection_booking_fee' => 'Inspection booking payment for a scheduled-property workflow.',
            default => $transaction->metadata['checkout_context'] ?? 'General payment record',
        };
    }

    public function platformFeeSummary(PaymentTransaction $transaction): string
    {
        $percentage = (float) ($transaction->platform_fee_percentage ?? 0);

        if ($percentage <= 0) {
            return 'No platform fee is being deducted from this transaction.';
        }

        return sprintf(
            'Platform fee: %s (%s%%). Landlord net snapshot: %s.',
            $this->formatMoney($transaction->platform_fee_amount, $transaction->currency),
            number_format($percentage, 2),
            $this->formatMoney($transaction->net_amount, $transaction->currency),
        );
    }

    public function workflowImpactSummary(PaymentTransaction $transaction): ?string
    {
        return data_get($transaction->metadata, 'occupancy_update_message')
            ?? data_get($transaction->metadata, 'purchase_update_message');
    }

    public function canContinueCheckout(PaymentTransaction $transaction): bool
    {
        return in_array($transaction->status, ['initiated', 'pending'], true)
            && filled(data_get($transaction->metadata, 'checkout_url'));
    }

    protected function paymentsAvailable(): bool
    {
        return Schema::hasTable('payment_transactions');
    }

    protected function emptyPaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            items: new Collection(),
            total: 0,
            perPage: 10,
            currentPage: 1,
            options: [
                'path' => request()->url(),
                'pageName' => 'page',
            ],
        );
    }
}
