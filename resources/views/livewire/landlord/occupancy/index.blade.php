<div class="admin-page">
    <div class="admin-page-inner space-y-6">
        <x-admin.panel>
            <div class="space-y-2">
                <p class="admin-eyebrow">Occupants</p>
                <h2 class="admin-panel-title">Occupants and upcoming reservations</h2>
                <p class="admin-panel-copy">Monitor active tenants, move-out states, and rental units reserved for an upcoming stay.</p>
            </div>
        </x-admin.panel>

        @if ($tenantProfile)
            <x-admin.panel>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Filtered tenant</p>
                        <p class="text-lg font-semibold text-slate-900">{{ $tenantProfile->name }}</p>
                    </div>
                    <a href="{{ route('landlord.occupancy.index') }}" class="admin-button admin-button-secondary">Clear filter</a>
                </div>
            </x-admin.panel>
        @endif

        @if (! $occupanciesAvailable)
            <x-admin.empty-state
                title="Occupancy tracking is not available yet."
                copy="This page will populate automatically once occupancy tracking is enabled in this environment."
            />
        @elseif ($occupanciesByProperty->isEmpty())
            <x-admin.empty-state
                title="No active occupants yet."
                copy="Once rent payments are confirmed, active occupancies will show here."
            >
                <a href="{{ route('landlord.properties') }}" class="admin-inline-link mt-3 inline-flex">Review listings</a>
            </x-admin.empty-state>
        @else
            <div class="space-y-6">
                @foreach ($occupanciesByProperty as $propertyId => $occupancies)
                    @php
                        $property = $occupancies->first()?->property;
                        $coverImage = $property?->coverImage;
                    @endphp

                    <x-admin.panel>
                        <div class="space-y-6">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="flex items-start gap-4">
                                    <div class="h-20 w-20 shrink-0 overflow-hidden rounded-2xl border border-slate-200 bg-slate-100">
                                        @if ($coverImage)
                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($coverImage->image_path) }}" alt="{{ $property?->title ?? 'Property' }}" class="h-full w-full object-cover">
                                        @else
                                            <div class="flex h-full w-full items-center justify-center text-xs text-slate-400">No image</div>
                                        @endif
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Rental listing</p>
                                        <h3 class="text-lg font-semibold text-slate-950">{{ $property?->title ?? 'Property' }}</h3>
                                        <p class="text-sm text-slate-600">{{ $property?->city ?? 'Location' }} - {{ $property?->availabilityDetail() ?? '' }}</p>
                                        <p class="text-xs text-slate-500">{{ $property?->listingIntentLabel() ?? 'For rent' }}</p>
                                    </div>
                                </div>
                                <div class="text-sm text-slate-500">
                                    {{ $occupancies->count() }} occupant{{ $occupancies->count() === 1 ? '' : 's' }}
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead class="admin-table-head">
                                        <tr>
                                            <th class="admin-table-head-cell">Tenant</th>
                                            <th class="admin-table-head-cell">Status</th>
                                            <th class="admin-table-head-cell">Rental Period</th>
                                            <th class="admin-table-head-cell">Next rent due</th>
                                            <th class="admin-table-head-cell">Days remaining</th>
                                            <th class="admin-table-head-cell">Overdue</th>
                                            <th class="admin-table-head-cell">Rent status</th>
                                            <th class="admin-table-head-cell">Agreement</th>
                                            <th class="admin-table-head-cell">Move-in condition</th>
                                        </tr>
                                    </thead>
                                    <tbody class="admin-table-body">
                                        @foreach ($occupancies as $occupancy)
                                            @php
                                                $dueAt = $occupancy->computedNextPaymentDueAt();
                                                $isRent = ($occupancy->property?->listing_intent ?? 'for_rent') === 'for_rent';
                                                $daysRemaining = $occupancy->daysUntilNextPayment();
                                                $overdueDays = $occupancy->overdueDays();
                                                $statusTone = $occupancy->status === 'moved_out'
                                                    ? 'neutral'
                                                    : ($occupancy->status === 'upcoming' ? 'info' : ($occupancy->status === 'move_out_pending' ? 'warning' : 'success'));
                                                $rentTone = $overdueDays && $overdueDays > 0
                                                    ? 'danger'
                                                    : (($daysRemaining !== null && $daysRemaining <= 30) ? 'warning' : 'info');
                                            @endphp
                                            <tr class="align-top">
                                                <td class="px-4 py-4 text-sm text-slate-700">
                                                    <div class="flex items-center gap-3">
                                                        <a href="{{ route('landlord.tenants.show', $occupancy->tenant) }}" class="h-10 w-10 overflow-hidden rounded-xl bg-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500">
                                                            @if ($occupancy->tenant?->avatar_path)
                                                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($occupancy->tenant->avatar_path) }}" alt="{{ $occupancy->tenant->name }}" class="h-full w-full object-cover">
                                                            @else
                                                                <div class="flex h-full w-full items-center justify-center text-xs font-semibold text-slate-500">
                                                                    {{ str($occupancy->tenant?->name ?? 'T')->substr(0, 2)->upper() }}
                                                                </div>
                                                            @endif
                                                        </a>
                                                        <div><a href="{{ route('landlord.tenants.show', $occupancy->tenant) }}" class="font-medium text-slate-900 hover:text-sky-800">{{ $occupancy->tenant?->name ?? 'Tenant' }}</a></div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 text-sm text-slate-700">
                                                    <x-status-chip tone="{{ $statusTone }}">{{ str($occupancy->status)->headline() }}</x-status-chip>
                                                </td>
                                                <td class="px-4 py-4 text-sm text-slate-700">{{ $isRent ? $occupancy->rentalPeriodLabel() : '-' }}</td>
                                                <td class="px-4 py-4 text-sm text-slate-700">
                                                    {{ $occupancy->status === 'upcoming' ? 'After current stay closes' : ($dueAt && $isRent ? $dueAt->format('M j, Y') : 'Not required') }}
                                                </td>
                                                <td class="px-4 py-4 text-sm text-slate-700">
                                                    @if ($occupancy->status === 'upcoming')
                                                        -
                                                    @elseif (! $isRent)
                                                        Not required
                                                    @elseif ($daysRemaining === null)
                                                        Unavailable
                                                    @elseif ($daysRemaining < 0)
                                                        0 days
                                                    @else
                                                        {{ $daysRemaining }} day{{ $daysRemaining === 1 ? '' : 's' }}
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4 text-sm text-slate-700"><p class="font-medium">Maintenance</p><p class="mt-1 text-xs text-slate-600">{{ $occupancy->maintenance_open_count ? $occupancy->maintenance_open_count.' open' : 'No open issues' }}</p><a href="{{ route('landlord.maintenance.index') }}" class="admin-inline-link mt-1 inline-flex">View requests</a></td>
                                                <td class="px-4 py-4 text-sm text-slate-700">
                                                    @if (! $moveInReportsAvailable)
                                                        -
                                                    @elseif ($occupancy->moveInConditionReport)
                                                        @php($report = $occupancy->moveInConditionReport)
                                                        <div class="space-y-2"><x-status-chip tone="{{ $report->status === 'completed' ? 'success' : ($report->status === 'changes_requested' ? 'warning' : 'info') }}">{{ str($report->status)->headline() }}</x-status-chip><a href="{{ route('landlord.move-in-reports.edit', $occupancy) }}" class="admin-inline-link block">{{ $report->canBeEditedByLandlord() ? 'Continue report' : 'View report' }}</a></div>
                                                    @elseif ($occupancy->tenancyAgreement?->completed_at)
                                                        <div class="space-y-2"><span class="text-slate-600">Not started</span><button wire:click="startMoveInReport({{ $occupancy->id }})" type="button" class="admin-inline-link" wire:loading.attr="disabled">Start report</button></div>
                                                    @else
                                                        <span class="text-slate-500">Complete agreement first</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4 text-sm text-slate-700">
                                                    @if (! $agreementsAvailable)
                                                        -
                                                    @elseif (! $occupancy->tenancyAgreement)
                                                        <span class="text-slate-500">No agreement generated</span>
                                                    @else
                                                        @php($agreement = $occupancy->tenancyAgreement)
                                                        <div class="space-y-2"><x-status-chip tone="{{ $agreement->completed_at ? 'success' : ($agreement->landlord_accepted_at ? 'info' : ($agreement->tenant_accepted_at ? 'warning' : 'info')) }}">{{ $agreement->completed_at ? 'Completed' : ($agreement->landlord_accepted_at ? 'Awaiting tenant' : ($agreement->tenant_accepted_at ? 'Awaiting your acceptance' : 'Awaiting tenant')) }}</x-status-chip><a href="{{ route('landlord.agreements.show', $agreement) }}" class="admin-inline-link block">Review agreement</a></div>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4 text-sm text-slate-700">
                                                    @if ($occupancy->status === 'upcoming')
                                                        -
                                                    @elseif (! $isRent)
                                                        Not required
                                                    @elseif ($overdueDays && $overdueDays > 0)
                                                        {{ $overdueDays }} day{{ $overdueDays === 1 ? '' : 's' }}
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4 text-sm text-slate-700">
                                                    @if ($occupancy->status === 'upcoming')
                                                        <x-status-chip tone="info">Upcoming rental secured</x-status-chip>
                                                    @elseif ($isRent)
                                                        <x-status-chip tone="{{ $rentTone }}">{{ $occupancy->paymentStatusLabel() }}</x-status-chip>
                                                    @else
                                                        <x-status-chip tone="success">Purchase recorded</x-status-chip>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </x-admin.panel>
                @endforeach
            </div>
        @endif
    </div>
</div>
