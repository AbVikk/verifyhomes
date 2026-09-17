<div class="admin-page">
    <div class="admin-page-inner">
        @if (session('status'))
            <x-admin.alert>
                {{ session('status') }}
            </x-admin.alert>
        @endif

        <div class="grid gap-6 md:grid-cols-2">
            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Paid</p>
                    <x-admin.kpi-value :value="$summary['paid']" />
                    <p class="text-sm text-slate-600">Verified paid money tied to your properties.</p>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Verified volume</p>
                    <x-admin.kpi-value :value="\App\Support\Currency::formatCompact($summary['gross'])" :exact="$this->formatMoney($summary['gross'])" />
                    <p class="text-sm text-slate-600">Gross value from paid transactions visible to landlords.</p>
                </div>
            </x-admin.panel>
        </div>

        <x-admin.panel>
            <div class="space-y-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="admin-eyebrow">Payments</p>
                        <h2 class="admin-panel-title">Paid money tied to your listings</h2>
                        <p class="admin-panel-copy">Verified property payments only.</p>
                    </div>
                </div>

                @if (! $paymentsAvailable)
                    <x-admin.empty-state
                        title="Payment transactions are not available yet."
                        copy="This page will populate automatically after the payment transaction table is available in this environment."
                    />
                @else
                    @if ($highlightedTransaction)
                        <div class="admin-callout">
                            <p class="font-medium text-slate-900">
                                Reference <span class="font-mono text-xs">{{ $highlightedTransaction->reference }}</span>
                                is {{ str($highlightedTransaction->status)->headline() }} through {{ $this->providerLabel($highlightedTransaction->provider) }}.
                            </p>
                            <p class="mt-2 text-sm text-slate-600">{{ $this->statusSummary($highlightedTransaction->status) }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ $this->platformFeeSummary($highlightedTransaction) }}</p>
                            @if ($this->workflowImpactSummary($highlightedTransaction))
                                <p class="mt-2 text-sm text-slate-600">{{ $this->workflowImpactSummary($highlightedTransaction) }}</p>
                            @endif
                        </div>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="admin-table-head">
                                <tr>
                                    <th class="admin-table-head-cell">Reference</th>
                                    <th class="admin-table-head-cell">Property</th>
                                    <th class="admin-table-head-cell">Tenant</th>
                                    <th class="admin-table-head-cell">Amount</th>
                                    <th class="admin-table-head-cell">Rental Period</th>
                                    <th class="admin-table-head-cell">Status</th>
                                    <th class="admin-table-head-cell">Logged</th>
                                </tr>
                            </thead>
                            <tbody class="admin-table-body">
                                @forelse ($transactions as $transaction)
                                    <tr class="align-top">
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p class="font-mono text-xs text-slate-900" title="{{ $transaction->reference }}">{{ str($transaction->reference)->limit(18) }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p class="font-medium text-slate-900">{{ $transaction->property?->title ?: 'Property record' }}</p>
                                            <p class="mt-1 text-slate-500">{{ $this->transactionTypeLabel($transaction) }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p class="font-medium text-slate-900">{{ $transaction->payer?->name ?: 'No tenant record' }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p class="text-xs text-slate-500">{{ $this->paidAmountLabel($transaction) }}</p>
                                            <p class="font-medium text-slate-900">{{ $this->formatMoney($transaction->gross_amount, $transaction->currency) }}</p>
                                            <p class="mt-1 text-xs text-slate-500">Landlord amount</p>
                                            <p class="text-xs text-slate-700">{{ $this->formatMoney($transaction->net_amount, $transaction->currency) }}</p>
                                            <p class="mt-1 text-xs text-slate-500">Paid to you</p>
                                            <p class="text-xs text-slate-700">{{ $this->formatMoney($this->settlementPaidToDate($transaction), $transaction->currency) }}</p>
                                            @if ($this->settlementOutstanding($transaction) > 0)
                                                <p class="mt-1 text-xs text-slate-500">Outstanding</p>
                                                <p class="text-xs text-slate-700">{{ $this->formatMoney($this->settlementOutstanding($transaction), $transaction->currency) }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">{{ $transaction->rentalPeriodLabel() }}</td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <span class="admin-badge admin-badge-neutral">{{ $this->settlementStatus($transaction) }}</span>
                                            @if ($this->settlementStatus($transaction) === 'Legacy payout recorded')
                                                <p class="mt-2 text-xs text-slate-500">Detailed payout history is unavailable for this legacy record.</p>
                                            @endif
                                            @if ($transaction->landlordSettlements->isNotEmpty())
                                                <p class="mt-2 text-xs text-slate-500">Last payout: {{ $transaction->landlordSettlements->first()->recorded_at?->format('M j, Y') }}</p>
                                                @foreach ($transaction->landlordSettlements as $settlement)
                                                    <p class="mt-1 text-xs text-slate-500">{{ $this->formatMoney($settlement->payout_amount, $settlement->currency) }}{{ $settlement->payout_reference ? ' · '.$settlement->payout_reference : '' }}</p>
                                                @endforeach
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-500">
                                            <p>{{ $transaction->created_at->diffForHumans() }}</p>
                                            <p class="mt-1 text-xs text-slate-400">{{ $transaction->created_at->format('Y-m-d H:i') }}</p>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8">
                                            <x-admin.empty-state
                                                title="No verified paid transactions are tied to your properties yet."
                                                copy="Paid landlord-visible transactions will appear here after payment verification finishes."
                                            />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $transactions->links() }}
                @endif
            </div>
        </x-admin.panel>
    </div>
</div>
