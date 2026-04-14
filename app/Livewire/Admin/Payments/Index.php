<?php

namespace App\Livewire\Admin\Payments;

use App\Livewire\Admin\Concerns\HasAdminLayout;
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
    use HasAdminLayout;
    use WithPagination;

    #[Url(except: 'all')]
    public string $statusFilter = 'all';

    #[Url(except: 'all')]
    public string $providerFilter = 'all';

    #[Url(except: '')]
    public string $reference = '';

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingProviderFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $paymentsAvailable = $this->paymentsAvailable();
        $baseQuery = $paymentsAvailable
            ? PaymentTransaction::query()->with(['payer', 'property', 'inspectionRequest.property'])
            : null;

        $transactions = $paymentsAvailable
            ? (clone $baseQuery)
                ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
                ->when($this->providerFilter !== 'all', fn ($query) => $query->where('provider', $this->providerFilter))
                ->latest('created_at')
                ->paginate(12)
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
                'gross' => (float) ((clone $baseQuery)->where('status', 'paid')->sum('gross_amount') ?: 0),
            ]
            : [
                'initiated' => 0,
                'pending' => 0,
                'paid' => 0,
                'failed' => 0,
                'gross' => 0,
            ];

        $providers = $paymentsAvailable
            ? PaymentTransaction::query()
                ->select('provider')
                ->distinct()
                ->orderBy('provider')
                ->pluck('provider')
                ->filter()
                ->values()
            : collect();

        return $this->adminPage(view('livewire.admin.payments.index', [
            'paymentsAvailable' => $paymentsAvailable,
            'transactions' => $transactions,
            'highlightedTransaction' => $highlightedTransaction,
            'summary' => $summary,
            'providers' => $providers,
        ]), 'Payments');
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
                ? 'Rent checkout started. The tenant may still need to finish the provider step.'
                : ($isPurchasePayment
                    ? 'Purchase checkout started. The buyer may still need to finish the provider step.'
                    : 'Checkout started. The payer may still need to finish the provider step.'),
            'pending' => $isRentPayment
                ? 'Rent checkout finished on the provider, but VerifyHomes is still waiting for final confirmation.'
                : ($isPurchasePayment
                    ? 'Purchase checkout finished on the provider, but VerifyHomes is still waiting for final confirmation.'
                    : 'Provider checkout finished, but VerifyHomes is still waiting for final confirmation.'),
            'paid' => $isRentPayment
                ? 'Rent payment is verified and occupancy effects can move forward.'
                : ($isPurchasePayment
                    ? 'Purchase payment is verified and the sale workflow can move forward.'
                    : 'Payment is verified and the workflow can move forward.'),
            'failed' => $isRentPayment
                ? 'Rent checkout failed or verification came back with an error.'
                : ($isPurchasePayment
                    ? 'Purchase checkout failed or verification came back with an error.'
                    : 'Checkout failed or verification came back with an error.'),
            default => 'This transaction is recorded with no additional payment update yet.',
        };
    }

    public function transactionTypeSummary(PaymentTransaction $transaction): string
    {
        $unitsReserved = (int) data_get($transaction->metadata, 'units_reserved', 1);
        $unitSuffix = $unitsReserved > 1 ? " ({$unitsReserved} units)" : '';

        return match ($transaction->transaction_type) {
            'rent_payment' => 'Rent payment',
            'inspection_booking_fee' => 'Inspection booking fee',
            'house_purchase_payment' => 'House purchase payment',
            'land_purchase_payment' => 'Land purchase payment'.$unitSuffix,
            'purchase_payment' => 'Purchase payment',
            default => str($transaction->transaction_type)->replace(['_', '-'], ' ')->headline()->toString(),
        };
    }

    public function platformFeeSummary(PaymentTransaction $transaction): string
    {
        $percentage = (float) ($transaction->platform_fee_percentage ?? 0);

        if ($percentage <= 0) {
            return 'No platform fee is being deducted from this transaction.';
        }

        return sprintf(
            'Platform fee: %s (%s%%). Net amount: %s.',
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

    protected function paymentsAvailable(): bool
    {
        return Schema::hasTable('payment_transactions');
    }

    protected function emptyPaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            items: new Collection(),
            total: 0,
            perPage: 12,
            currentPage: 1,
            options: [
                'path' => request()->url(),
                'pageName' => 'page',
            ],
        );
    }
}
