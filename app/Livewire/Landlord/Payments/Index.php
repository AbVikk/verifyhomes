<?php

namespace App\Livewire\Landlord\Payments;

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
        $landlordVisibleTypes = $this->landlordVisibleTransactionTypes();
        $baseQuery = $paymentsAvailable
            ? PaymentTransaction::query()
                ->whereHas('property', fn ($query) => $query->where('landlord_id', $this->currentUserId()))
                ->where('status', 'paid')
                ->whereIn('transaction_type', $landlordVisibleTypes)
                ->with(['payer', 'property'])
            : null;

        $transactions = $paymentsAvailable
            ? (clone $baseQuery)
                ->latest('created_at')
                ->paginate(10)
            : $this->emptyPaginator();

        $highlightedTransaction = $paymentsAvailable && $this->reference !== ''
            ? (clone $baseQuery)->where('reference', $this->reference)->first()
            : null;

        $summary = $paymentsAvailable
            ? [
                'paid' => (clone $baseQuery)->where('status', 'paid')->count(),
                'gross' => (float) ((clone $baseQuery)->sum('gross_amount') ?: 0),
            ]
            : [
                'paid' => 0,
                'gross' => 0,
            ];

        return view('livewire.landlord.payments.index', [
            'paymentsAvailable' => $paymentsAvailable,
            'transactions' => $transactions,
            'highlightedTransaction' => $highlightedTransaction,
            'summary' => $summary,
        ])->layout('layouts.dashboard-shell', $this->landlordShell('Payments'));
    }

    public function formatMoney(float|int|string|null $amount, string $currency = 'NGN'): string
    {
        return Currency::format($amount, $currency);
    }

    public function providerLabel(?string $provider): string
    {
        return app(PaymentGatewayManager::class)->label($provider);
    }

    public function statusSummary(?string $status): string
    {
        return match ($status) {
            'paid' => 'Payment verified. This is landlord-relevant settled money tied to your property activity.',
            default => 'Only verified paid transactions are shown in this workspace.',
        };
    }

    public function transactionTypeLabel(PaymentTransaction $transaction): string
    {
        $unitsReserved = (int) data_get($transaction->metadata, 'units_reserved', 1);
        $unitSuffix = $unitsReserved > 1 ? " ({$unitsReserved} units)" : '';

        return match ($transaction->transaction_type) {
            'rent_payment' => 'Rent payment',
            'house_purchase_payment' => 'House purchase payment',
            'land_purchase_payment' => 'Land purchase payment'.$unitSuffix,
            'purchase_payment' => 'Purchase payment',
            default => 'Property payment',
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

    protected function paymentsAvailable(): bool
    {
        return Schema::hasTable('payment_transactions');
    }

    protected function landlordVisibleTransactionTypes(): array
    {
        return [
            'rent_payment',
            'purchase_payment',
            'house_purchase_payment',
            'land_purchase_payment',
        ];
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
