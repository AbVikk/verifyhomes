<div class="admin-page">
    <div class="admin-page-inner space-y-6">
        <x-admin.panel>
            <div class="space-y-2">
                <p class="admin-eyebrow">Purchases</p>
                <h2 class="admin-panel-title">Confirmed property purchases</h2>
                <p class="admin-panel-copy">Track purchase records created after verified sale payments.</p>
            </div>
        </x-admin.panel>

        <div class="grid gap-6 md:grid-cols-4">
            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Total purchases</p>
                    <p class="text-3xl font-semibold text-slate-950">{{ $summary['total'] }}</p>
                    <p class="text-sm text-slate-600">Confirmed purchase records.</p>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">House purchases</p>
                    <p class="text-3xl font-semibold text-slate-950">{{ $summary['house'] }}</p>
                    <p class="text-sm text-slate-600">Completed house sale payments.</p>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Land purchases</p>
                    <p class="text-3xl font-semibold text-slate-950">{{ $summary['land'] }}</p>
                    <p class="text-sm text-slate-600">Completed land sale payments.</p>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Gross total</p>
                    <p class="text-3xl font-semibold text-slate-950">{{ $this->formatMoney($summary['gross']) }}</p>
                    <p class="text-sm text-slate-600">Total recorded purchase value.</p>
                </div>
            </x-admin.panel>
        </div>

        <x-admin.panel>
            <div class="space-y-4">
                <div>
                    <p class="admin-eyebrow">Purchase records</p>
                    <h3 class="admin-panel-title">Ownership confirmations</h3>
                    <p class="admin-panel-copy">Each record is created after a paid purchase transaction is verified.</p>
                </div>

                @if (! $purchasesAvailable)
                    <x-admin.empty-state
                        title="Purchase records are not available yet."
                        copy="This page will populate automatically once property purchase tracking is enabled."
                    />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="admin-table-head">
                                <tr>
                                    <th class="admin-table-head-cell">Property</th>
                                    <th class="admin-table-head-cell">Buyer</th>
                                    <th class="admin-table-head-cell">Type</th>
                                    <th class="admin-table-head-cell">Units</th>
                                    <th class="admin-table-head-cell">Amount</th>
                                    <th class="admin-table-head-cell">Status</th>
                                    <th class="admin-table-head-cell">Reference</th>
                                    <th class="admin-table-head-cell">Confirmed</th>
                                </tr>
                            </thead>
                            <tbody class="admin-table-body">
                                @forelse ($purchases as $purchase)
                                    <tr class="align-top">
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p class="font-medium text-slate-900">{{ $purchase->property?->title ?? 'Property' }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $purchase->property?->listingIntentLabel() ?? 'Listing' }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p class="font-medium text-slate-900">{{ $purchase->buyer?->name ?? 'Buyer' }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $purchase->buyer?->email ?? 'No email' }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            {{ $purchase->purchaseTypeLabel() }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            {{ $purchase->units }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            {{ $this->formatMoney($purchase->gross_amount, $purchase->currency) }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <span class="admin-badge admin-badge-success">{{ str($purchase->status)->headline() }}</span>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            @if ($purchase->paymentTransaction)
                                                <p class="font-mono text-xs text-slate-800">{{ $purchase->paymentTransaction->reference }}</p>
                                                <a href="{{ route('admin.payments.index', ['reference' => $purchase->paymentTransaction->reference]) }}" class="admin-inline-link mt-1 inline-flex">View payment</a>
                                            @else
                                                <p class="text-xs text-slate-500">No transaction</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-500">
                                            {{ $purchase->purchased_at?->format('Y-m-d H:i') ?? 'Pending' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-8">
                                            <x-admin.empty-state
                                                title="No purchase records yet."
                                                copy="Confirmed purchase payments will create records here automatically."
                                            />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $purchases->links() }}
                @endif
            </div>
        </x-admin.panel>
    </div>
</div>
