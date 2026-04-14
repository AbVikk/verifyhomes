<div class="admin-page">
    <div class="admin-page-inner">
        @if (session('status'))
            <x-admin.alert>
                {{ session('status') }}
            </x-admin.alert>
        @endif

        <div class="grid gap-6 md:grid-cols-4">
            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Initiated</p>
                    <p class="text-3xl font-semibold text-slate-950">{{ $summary['initiated'] }}</p>
                    <p class="text-sm text-slate-600">Checkout started, but you may still need to finish the provider step.</p>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Awaiting verification</p>
                    <p class="text-3xl font-semibold text-slate-950">{{ $summary['pending'] ?? 0 }}</p>
                    <p class="text-sm text-slate-600">Checkout came back, but the final gateway confirmation is still pending.</p>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Paid</p>
                    <p class="text-3xl font-semibold text-slate-950">{{ $summary['paid'] }}</p>
                    <p class="text-sm text-slate-600">Transactions that are fully confirmed and recorded as paid.</p>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Failed</p>
                    <p class="text-3xl font-semibold text-slate-950">{{ $summary['failed'] }}</p>
                    <p class="text-sm text-slate-600">Transactions where checkout did not finish successfully.</p>
                </div>
            </x-admin.panel>
        </div>

        <x-admin.panel>
            <div class="space-y-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="admin-eyebrow">Payments</p>
                        <h2 class="admin-panel-title">Your payment transactions</h2>
                        <p class="admin-panel-copy">Review rent and inspection payment references, related records, gateway updates, and the next step for each transaction.</p>
                    </div>

                    @if ($paymentsAvailable)
                        <div class="w-full md:w-64">
                            <label for="statusFilter" class="admin-label">Filter by status</label>
                            <select wire:model.live="statusFilter" id="statusFilter" class="admin-control admin-control-select">
                                <option value="all">All statuses</option>
                                <option value="initiated">Initiated</option>
                                <option value="pending">Pending</option>
                                <option value="paid">Paid</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                    @endif
                </div>

                @if (! $paymentsAvailable)
                    <x-admin.empty-state
                        title="Payment transactions are not available yet."
                        copy="This page will populate automatically after the payment transaction table is available in this environment."
                    />
                @else
                    @if ($highlightedTransaction)
                        <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                            Checkout reference <span class="font-mono">{{ $highlightedTransaction->reference }}</span> is currently
                            <span class="font-medium">{{ str($highlightedTransaction->status)->headline() }}</span>
                            through {{ $this->providerLabel($highlightedTransaction->provider) }}.
                            {{ $this->statusSummary($highlightedTransaction) }}
                            @if ($this->canContinueCheckout($highlightedTransaction))
                                <a href="{{ data_get($highlightedTransaction->metadata, 'checkout_url') }}" target="_blank" rel="noopener noreferrer" class="admin-inline-link">Continue checkout</a>
                            @endif
                        </div>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="admin-table-head">
                                <tr>
                                    <th class="admin-table-head-cell">Reference</th>
                                    <th class="admin-table-head-cell">Type</th>
                                    <th class="admin-table-head-cell">Related record</th>
                                    <th class="admin-table-head-cell">Amount</th>
                                    <th class="admin-table-head-cell">Status</th>
                                    <th class="admin-table-head-cell">Logged</th>
                                </tr>
                            </thead>
                            <tbody class="admin-table-body">
                                @forelse ($transactions as $transaction)
                                    <tr class="align-top">
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p class="font-mono text-xs text-slate-800">{{ $transaction->reference }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $this->providerLabel($transaction->provider) }}</p>
                                            @if ($transaction->provider_reference)
                                                <p class="mt-1 text-xs text-slate-500">Provider ref: {{ $transaction->provider_reference }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p class="font-medium text-slate-900">{{ str($transaction->transaction_type)->headline() }}</p>
                                            <p class="mt-1 text-slate-500">{{ $this->transactionTypeSummary($transaction) }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            @if ($transaction->inspectionRequest)
                                                <p class="font-medium text-slate-900">Inspection request</p>
                                                <p class="mt-1 text-slate-600">{{ $transaction->inspectionRequest->property?->title ?? 'Property record' }}</p>
                                                <a href="{{ route('tenant.inspection-requests.show', ['inspectionRequestId' => $transaction->inspectionRequest->getKey()]) }}" class="admin-inline-link mt-2 inline-flex">Open request</a>
                                            @elseif ($transaction->property)
                                                <p class="font-medium text-slate-900">Property</p>
                                                <p class="mt-1 text-slate-600">{{ $transaction->property->title }}</p>
                                                <a href="{{ route('properties.show', $transaction->property) }}" class="admin-inline-link mt-2 inline-flex">Open property</a>
                                            @else
                                                <p class="text-slate-600">No related record</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p class="font-medium text-slate-900">{{ $this->formatMoney($transaction->gross_amount, $transaction->currency) }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $this->platformFeeSummary($transaction) }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <span class="admin-badge admin-badge-neutral">{{ str($transaction->status)->headline() }}</span>
                                            <p class="mt-2 text-slate-500">{{ $this->statusSummary($transaction) }}</p>
                                            @if ($this->workflowImpactSummary($transaction))
                                                <p class="mt-2 text-slate-600">{{ $this->workflowImpactSummary($transaction) }}</p>
                                            @endif
                                            @if ($this->canContinueCheckout($transaction))
                                                <a href="{{ data_get($transaction->metadata, 'checkout_url') }}" target="_blank" rel="noopener noreferrer" class="admin-inline-link">Continue checkout</a>
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
                                                :title="$statusFilter === 'all' ? 'You do not have any payment transactions yet.' : 'No payment transactions match the current status filter.'"
                                                copy="Start an inspection payment from the request detail page, a rent payment from a rental listing, or a purchase payment from a sale listing and it will appear here automatically."
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
