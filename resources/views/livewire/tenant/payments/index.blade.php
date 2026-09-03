<div class="admin-page">
    <div class="admin-page-inner space-y-6">
        @if (session('status'))
            <x-admin.alert>{{ session('status') }}</x-admin.alert>
        @endif

        <x-admin.panel>
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="admin-eyebrow">Payments</p>
                    <h2 class="admin-panel-title">Your payment transactions</h2>
                    <p class="admin-panel-copy">Track property payments and inspection booking payments separately.</p>
                </div>

                @if ($paymentsAvailable)
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="categoryFilter" class="admin-label">Payment category</label>
                            <select wire:model.live="categoryFilter" id="categoryFilter" class="admin-control admin-control-select">
                                <option value="all">All payments</option>
                                <option value="property">Property payments</option>
                                <option value="inspection">Inspection bookings</option>
                            </select>
                        </div>
                        <div>
                            <label for="statusFilter" class="admin-label">Status</label>
                            <select wire:model.live="statusFilter" id="statusFilter" class="admin-control admin-control-select">
                                <option value="all">All statuses</option>
                                <option value="initiated">Initiated</option>
                                <option value="pending">Awaiting verification</option>
                                <option value="paid">Paid</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                    </div>
                @endif
            </div>
        </x-admin.panel>

        @if (! $paymentsAvailable)
            <x-admin.empty-state title="Payment transactions are not available yet." copy="This page will populate after payment transactions are available in this environment." />
        @else
            <div class="grid gap-4 md:grid-cols-2">
                <div class="admin-data-box"><p class="font-semibold text-slate-900">Property Payments</p><p class="mt-1 text-sm text-slate-600">Rent and purchase payments that secure a property.</p></div>
                <div class="admin-data-box"><p class="font-semibold text-slate-900">Inspection Booking Payments</p><p class="mt-1 text-sm text-slate-600">Fees paid to confirm an inspection booking. Separate from rent or purchase.</p></div>
            </div>

            @if ($highlightedTransaction && $this->canContinueCheckout($highlightedTransaction))
                <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                    <span class="font-medium">Your {{ strtolower($this->transactionTypeLabel($highlightedTransaction)) }} is waiting for completion.</span>
                    <a href="{{ data_get($highlightedTransaction->metadata, 'checkout_url') }}" target="_blank" rel="noopener noreferrer" class="admin-inline-link ml-2">Continue checkout</a>
                    <p class="mt-1 font-mono text-xs">{{ $highlightedTransaction->reference }}</p>
                </div>
            @endif

            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full">
                    <thead class="admin-table-head"><tr><th class="admin-table-head-cell">Reference</th><th class="admin-table-head-cell">Type</th><th class="admin-table-head-cell">Property / Request</th><th class="admin-table-head-cell">Amount</th><th class="admin-table-head-cell">Status</th><th class="admin-table-head-cell">Date</th><th class="admin-table-head-cell">Action</th></tr></thead>
                    <tbody class="admin-table-body">
                        @forelse ($transactions as $transaction)
                            <tr class="align-top">
                                <td class="px-4 py-4 font-mono text-xs text-slate-700">{{ $transaction->reference }}</td>
                                <td class="px-4 py-4 text-sm font-medium text-slate-900">{{ $this->transactionTypeLabel($transaction) }}</td>
                                <td class="px-4 py-4 text-sm text-slate-700">{{ $transaction->inspectionRequest?->property?->title ?? $transaction->property?->title ?? 'No related record' }}</td>
                                <td class="px-4 py-4 text-sm font-semibold text-slate-900">{{ $this->formatMoney($transaction->gross_amount, $transaction->currency) }}</td>
                                <td class="px-4 py-4 text-sm"><span class="admin-badge admin-badge-neutral">{{ $this->statusLabel($transaction) }}</span></td>
                                <td class="px-4 py-4 text-sm text-slate-500">{{ $transaction->created_at->format('M j, Y') }}</td>
                                <td class="px-4 py-4 text-sm">
                                    @if ($this->canContinueCheckout($transaction))
                                        <a href="{{ data_get($transaction->metadata, 'checkout_url') }}" target="_blank" rel="noopener noreferrer" class="admin-inline-link">Continue checkout</a>
                                    @elseif ($transaction->inspectionRequest)
                                        <a href="{{ route('tenant.inspection-requests.show', ['inspectionRequestId' => $transaction->inspectionRequest->getKey()]) }}" class="admin-inline-link">View inspection</a>
                                    @elseif ($transaction->status === 'paid' && $transaction->transaction_type === 'rent_payment')
                                        <a href="{{ route('tenant.occupancy.index') }}" class="admin-inline-link">View stay</a>
                                    @elseif (in_array($transaction->transaction_type, ['house_purchase_payment', 'land_purchase_payment', 'purchase_payment'], true) && data_get($transaction->metadata, 'purchase_record_id'))
                                        <a href="{{ route('tenant.purchases.show', data_get($transaction->metadata, 'purchase_record_id')) }}" class="admin-inline-link">View receipt</a>
                                    @elseif ($transaction->property)
                                        <a href="{{ route('properties.show', $transaction->property) }}" class="admin-inline-link">View property</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8"><x-admin.empty-state :title="$statusFilter === 'all' ? 'You do not have any payment transactions yet.' : 'No payment transactions match the current status filter.'" copy="Your inspection and property payments will appear here." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="space-y-4 md:hidden">
                @forelse ($transactions as $transaction)
                    <div class="admin-subsurface space-y-3 p-4">
                        <div class="flex items-start justify-between gap-3"><p class="font-semibold text-slate-900">{{ $this->transactionTypeLabel($transaction) }}</p><span class="admin-badge admin-badge-neutral">{{ $this->statusLabel($transaction) }}</span></div>
                        <p class="text-sm text-slate-600">{{ $transaction->inspectionRequest?->property?->title ?? $transaction->property?->title ?? 'No related record' }}</p>
                        <p class="text-lg font-semibold text-slate-950">{{ $this->formatMoney($transaction->gross_amount, $transaction->currency) }}</p>
                        <p class="text-xs text-slate-500">{{ $transaction->created_at->format('M j, Y') }} · {{ $transaction->reference }}</p>
                        @if ($this->canContinueCheckout($transaction))
                            <a href="{{ data_get($transaction->metadata, 'checkout_url') }}" target="_blank" rel="noopener noreferrer" class="admin-inline-link">Continue checkout</a>
                        @elseif ($transaction->inspectionRequest)
                            <a href="{{ route('tenant.inspection-requests.show', ['inspectionRequestId' => $transaction->inspectionRequest->getKey()]) }}" class="admin-inline-link">View inspection</a>
                        @elseif ($transaction->status === 'paid' && $transaction->transaction_type === 'rent_payment')
                            <a href="{{ route('tenant.occupancy.index') }}" class="admin-inline-link">View stay</a>
                        @elseif ($transaction->property)
                            <a href="{{ route('properties.show', $transaction->property) }}" class="admin-inline-link">View property</a>
                        @endif
                    </div>
                @empty
                    <x-admin.empty-state :title="$statusFilter === 'all' ? 'You do not have any payment transactions yet.' : 'No payment transactions match the current status filter.'" copy="Your inspection and property payments will appear here." />
                @endforelse
            </div>

            {{ $transactions->links() }}
        @endif
    </div>
</div>
