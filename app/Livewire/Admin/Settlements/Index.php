<?php

namespace App\Livewire\Admin\Settlements;

use App\Livewire\Admin\Concerns\HasAdminLayout;
use App\Models\LandlordSettlement;
use App\Models\PaymentTransaction;
use App\Support\Currency;
use App\Support\LandlordSettlementService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use HasAdminLayout;
    use WithPagination;

    #[Url(except: 'all')] public string $settlementFilter = 'all';
    public ?int $recordingTransactionId = null;
    public string $payoutAmount = '';
    public string $payoutReference = '';
    public string $payoutMethod = 'Bank transfer';
    public string $payoutNote = '';
    public ?int $reversingSettlementId = null;
    public string $reversalReason = '';

    public function updatingSettlementFilter(): void { $this->resetPage(); }

    public function beginPayout(int $transactionId): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
        $transaction = $this->transaction($transactionId);
        $this->recordingTransactionId = $transactionId;
        $this->payoutAmount = number_format(app(LandlordSettlementService::class)->outstanding($transaction), 2, '.', '');
        $this->resetValidation();
    }

    public function recordPayout(): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
        $this->validate(['recordingTransactionId' => ['required', 'integer'], 'payoutAmount' => ['required', 'numeric', 'gt:0'], 'payoutReference' => ['nullable', 'string', 'max:255'], 'payoutMethod' => ['nullable', 'string', 'max:80'], 'payoutNote' => ['nullable', 'string', 'max:2000']]);
        app(LandlordSettlementService::class)->record((int) $this->recordingTransactionId, Auth::user(), $this->payoutAmount, $this->payoutReference, $this->payoutMethod, $this->payoutNote);
        $this->reset('recordingTransactionId', 'payoutAmount', 'payoutReference', 'payoutMethod', 'payoutNote');
        session()->flash('status', 'Landlord payout recorded. This does not initiate or confirm an external bank transfer.');
    }

    public function beginReversal(int $settlementId): void { abort_unless(Auth::user()?->isAdmin(), 403); $this->reversingSettlementId = $settlementId; $this->reversalReason = ''; $this->resetValidation(); }
    public function reversePayout(): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
        $this->validate(['reversingSettlementId' => ['required', 'integer'], 'reversalReason' => ['required', 'string', 'min:3', 'max:2000']]);
        app(LandlordSettlementService::class)->reverse((int) $this->reversingSettlementId, Auth::user(), $this->reversalReason);
        $this->reset('reversingSettlementId', 'reversalReason');
        session()->flash('status', 'The payout was reversed and remains in the audit history.');
    }

    public function render(): View
    {
        $query = PaymentTransaction::query()->where('status', 'paid')->whereIn('transaction_type', LandlordSettlementService::ELIGIBLE_TYPES)
            ->whereHas('property', fn ($query) => $query->whereNotNull('landlord_id'))
            ->with(['property.landlord', 'landlordSettlements' => fn ($query) => $query->where('status', 'paid')->latest('recorded_at')])
            ->withSum(['landlordSettlements as paid_to_date' => fn ($query) => $query->where('status', 'paid')], 'payout_amount');
        $transactions = $query->latest('paid_at')->paginate(15);
        $service = app(LandlordSettlementService::class);
        $transactions->getCollection()->transform(function (PaymentTransaction $transaction) use ($service): PaymentTransaction {
            $transaction->settlement_entitlement = $service->entitlement($transaction);
            $transaction->settlement_paid = (float) ($transaction->paid_to_date ?? 0);
            $transaction->settlement_outstanding = $service->outstanding($transaction);
            $transaction->settlement_state = $service->status($transaction);
            $transaction->settlement_attention = $service->attention($transaction);
            return $transaction;
        });
        $transactions->setCollection($transactions->getCollection()
            ->filter(fn (PaymentTransaction $transaction) => $this->settlementFilter === 'all' || match ($this->settlementFilter) {
                'needs_attention' => ($transaction->settlement_attention['label'] ?? null) === 'Needs attention',
                'overdue' => $transaction->settlement_attention['overdue'] ?? false,
                'due_soon' => ($transaction->settlement_attention['label'] ?? null) === 'Due soon',
                'pending' => ($transaction->settlement_attention['label'] ?? null) === 'Awaiting payout',
                'partially_paid' => $transaction->settlement_state === 'partially_paid',
                'paid' => in_array($transaction->settlement_state, ['paid', 'legacy_paid'], true),
                default => false,
            })
            ->sortBy([fn (PaymentTransaction $transaction) => match ($transaction->settlement_attention['label'] ?? null) { 'Overdue' => 0, 'Needs attention' => 1, 'Due soon' => 2, 'Awaiting payout' => 3, default => 4 }, fn (PaymentTransaction $transaction) => -($transaction->settlement_attention['days'] ?? 0), fn (PaymentTransaction $transaction) => -$transaction->settlement_outstanding])
            ->values());

        $all = PaymentTransaction::query()->where('status', 'paid')->whereIn('transaction_type', LandlordSettlementService::ELIGIBLE_TYPES)->get(['id', 'gross_amount', 'platform_fee_amount', 'net_amount']);
        $paid = (float) LandlordSettlement::query()->where('status', 'paid')->sum('payout_amount');
        $attention = $transactions->getCollection()->pluck('settlement_attention')->filter();
        return $this->adminPage(view('livewire.admin.settlements.index', ['transactions' => $transactions, 'canManageSettlements' => Auth::user()?->isAdmin() ?? false, 'summary' => ['outstanding' => max(0, (float) $all->sum('net_amount') - $paid), 'paid' => $paid, 'platform' => (float) $all->sum('platform_fee_amount'), 'awaiting' => $transactions->getCollection()->where('settlement_state', 'pending')->count(), 'overdue' => $attention->where('overdue', true)->count(), 'oldest' => (int) ($attention->max('days') ?? 0)]]), 'Settlements');
    }

    public function formatMoney(float|int|string|null $amount, string $currency = 'NGN'): string { return Currency::format($amount, $currency); }
    private function transaction(int $id): PaymentTransaction { return PaymentTransaction::query()->with('property.landlord')->findOrFail($id); }
}
