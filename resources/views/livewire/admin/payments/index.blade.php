<div class="admin-page">
    <div class="admin-page-inner">
        @if (session('status'))
            <x-admin.alert>
                {{ session('status') }}
            </x-admin.alert>
        @endif

        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-5">
            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Initiated</p>
                    <x-admin.kpi-value :value="$summary['initiated']" />
                    <p class="text-sm text-slate-600">Checkout started, but the payer may still need to finish the provider step.</p>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Awaiting verification</p>
                    <x-admin.kpi-value :value="$summary['pending']" />
                    <p class="text-sm text-slate-600">Provider flow is done, but final confirmation is still pending.</p>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Paid</p>
                    <x-admin.kpi-value :value="$summary['paid']" />
                    <p class="text-sm text-slate-600">Verified payments that can move the workflow forward.</p>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Failed</p>
                    <x-admin.kpi-value :value="$summary['failed']" />
                    <p class="text-sm text-slate-600">Transactions that failed or came back with an error.</p>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Verified volume</p>
                    <x-admin.kpi-value :value="\App\Support\Currency::formatCompact($summary['gross'])" :exact="$this->formatMoney($summary['gross'])" />
                    <p class="text-sm text-slate-600">Gross amount from transactions already marked paid.</p>
                </div>
            </x-admin.panel>
        </div>

        <x-admin.panel>
            <div class="space-y-4">
                <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <p class="admin-eyebrow">Payments</p>
                        <h2 class="admin-panel-title">Platform payment transactions</h2>
                        <p class="admin-panel-copy">Track verified payments and landlord settlement records. Recording a payout does not initiate a bank transfer.</p>
                    </div>

                    @if ($paymentsAvailable)
                        <div class="grid gap-4 md:grid-cols-2 xl:w-[28rem]">
                            <div>
                                <label for="statusFilter" class="admin-label">Status</label>
                                <select wire:model.live="statusFilter" id="statusFilter" class="admin-control admin-control-select">
                                    <option value="all">All statuses</option>
                                    <option value="initiated">Initiated</option>
                                    <option value="pending">Pending</option>
                                    <option value="paid">Paid</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </div>

                            <div>
                                <label for="providerFilter" class="admin-label">Provider</label>
                                <select wire:model.live="providerFilter" id="providerFilter" class="admin-control admin-control-select">
                                    <option value="all">All providers</option>
                                    @foreach ($providers as $provider)
                                        <option value="{{ $provider }}">{{ $this->providerLabel($provider) }}</option>
                                    @endforeach
                                </select>
                            </div>
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
                        <div class="admin-callout">
                            <p class="font-medium text-slate-900">
                                Reference <span class="font-mono text-xs">{{ $highlightedTransaction->reference }}</span>
                                is {{ str($highlightedTransaction->status)->headline() }} through {{ $this->providerLabel($highlightedTransaction->provider) }} for {{ $this->transactionTypeSummary($highlightedTransaction) }}.
                            </p>
                            <p class="mt-2 text-sm text-slate-600">{{ $this->statusSummary($highlightedTransaction) }}</p>
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
                                    <th class="admin-table-head-cell">Type</th>
                                    <th class="admin-table-head-cell">Payer</th>
                                    <th class="admin-table-head-cell">Related record</th>
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
                                            <p class="mt-1 text-xs text-slate-500">{{ $this->providerLabel($transaction->provider) }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p class="font-medium text-slate-900">{{ $this->transactionTypeSummary($transaction) }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p class="font-medium text-slate-900">{{ $transaction->payer?->name ?: 'No payer record' }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            @if ($transaction->inspectionRequest)
                                                <p class="font-medium text-slate-900">{{ $transaction->inspectionRequest->property?->title ?? 'Inspection request' }}</p>
                                                <a href="{{ route('admin.inspection-requests.show', ['inspectionRequestId' => $transaction->inspectionRequest->getKey()]) }}" class="admin-inline-link">Open request</a>
                                            @elseif ($transaction->property)
                                                <p class="font-medium text-slate-900">{{ $transaction->property->title }}</p>
                                                <a href="{{ route('admin.properties.show', $transaction->property) }}" class="admin-inline-link">Open property</a>
                                            @else
                                                <p class="text-slate-600">No related record</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p class="text-xs text-slate-500">{{ $this->amountLabel($transaction) }}</p>
                                            <p class="font-medium text-slate-900">{{ $this->formatMoney($transaction->gross_amount, $transaction->currency) }}</p>
                                            @if ($transaction->transaction_type === 'inspection_booking_fee')
                                                <p class="mt-1 text-xs text-slate-500">VerifyHomes revenue</p>
                                                <p class="text-xs text-slate-700">{{ $this->formatMoney($transaction->gross_amount, $transaction->currency) }}</p>
                                                <p class="mt-1 text-xs text-slate-500">Landlord payout</p>
                                                <p class="text-xs text-slate-700">{{ $this->formatMoney(0, $transaction->currency) }}</p>
                                            @else
                                                <p class="mt-1 text-xs text-slate-500">Platform fee</p>
                                                <p class="text-xs text-slate-700">{{ $this->formatMoney($transaction->platform_fee_amount, $transaction->currency) }}</p>
                                                <p class="mt-1 text-xs text-slate-500">Landlord payout</p>
                                                <p class="text-xs text-slate-700">{{ $this->formatMoney($transaction->net_amount, $transaction->currency) }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">{{ $transaction->rentalPeriodLabel() }}</td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <span class="admin-badge admin-badge-neutral">{{ str($transaction->status)->headline() }}</span>
                                            @if ($transaction->transaction_type === 'inspection_booking_fee')
                                                <p class="mt-2 text-xs text-slate-500">No payout required</p>
                                            @elseif ($transaction->status === 'paid')
                                                <p class="mt-2 text-xs text-slate-500">{{ $transaction->landlord_settlement_status === 'recorded_paid' ? 'Payout recorded' : 'Awaiting payout' }}</p>
                                            @endif
                                            @if ($this->canRecordLandlordSettlement($transaction))
                                                <button wire:click="markLandlordSettled({{ $transaction->id }})" wire:loading.attr="disabled" wire:target="markLandlordSettled" type="button" class="admin-inline-link mt-2">
                                                    <span wire:loading.remove wire:target="markLandlordSettled">Record landlord payout</span>
                                                    <span wire:loading wire:target="markLandlordSettled">Recording...</span>
                                                </button>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-500">
                                            <p>{{ $transaction->created_at->diffForHumans() }}</p>
                                            <p class="mt-1 text-xs text-slate-400">{{ $transaction->created_at->format('Y-m-d H:i') }}</p>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-8">
                                            <x-admin.empty-state
                                                :title="$statusFilter === 'all' && $providerFilter === 'all' ? 'No payment transactions have been recorded yet.' : 'No payment transactions match the current filters.'"
                                                copy="Transactions will appear here automatically after checkout starts."
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
