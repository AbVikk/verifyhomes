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
                    <p class="text-3xl font-semibold text-slate-950">{{ $summary['paid'] }}</p>
                    <p class="text-sm text-slate-600">Verified paid money tied to your properties.</p>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Verified volume</p>
                    <p class="text-3xl font-semibold text-slate-950">{{ $this->formatMoney($summary['gross']) }}</p>
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
                        <p class="admin-panel-copy">This landlord workspace shows only verified paid property transactions. Tenant checkout states and inspection payments remain in tenant and admin views.</p>
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
                                    <th class="admin-table-head-cell">Status</th>
                                    <th class="admin-table-head-cell">Logged</th>
                                </tr>
                            </thead>
                            <tbody class="admin-table-body">
                                @forelse ($transactions as $transaction)
                                    <tr class="align-top">
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p class="font-mono text-xs text-slate-900">{{ $transaction->reference }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $this->providerLabel($transaction->provider) }}</p>
                                            @if ($transaction->provider_reference)
                                                <p class="mt-1 text-xs text-slate-500">Provider ref: {{ $transaction->provider_reference }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p class="font-medium text-slate-900">{{ $transaction->property?->title ?: 'Property record' }}</p>
                                            <p class="mt-1 text-slate-500">{{ $this->transactionTypeLabel($transaction) }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p class="font-medium text-slate-900">{{ $transaction->payer?->name ?: 'No tenant record' }}</p>
                                            <p class="mt-1 text-slate-500">Visible because this verified payment settled against your property activity.</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p class="font-medium text-slate-900">{{ $this->formatMoney($transaction->gross_amount, $transaction->currency) }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $this->platformFeeSummary($transaction) }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <span class="admin-badge admin-badge-neutral">{{ str($transaction->status)->headline() }}</span>
                                            <p class="mt-2 text-slate-500">{{ $this->statusSummary($transaction->status) }}</p>
                                            @if ($this->workflowImpactSummary($transaction))
                                                <p class="mt-2 text-slate-600">{{ $this->workflowImpactSummary($transaction) }}</p>
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
