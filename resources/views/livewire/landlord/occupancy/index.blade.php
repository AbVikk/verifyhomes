<div class="admin-page">
    <div class="admin-page-inner space-y-6">
        <x-admin.panel>
            <div class="space-y-2">
                <p class="admin-eyebrow">Occupants</p>
                <h2 class="admin-panel-title">Active tenants and rent cadence</h2>
                <p class="admin-panel-copy">Monitor your occupied listings and the next rent due for each tenant.</p>
            </div>
        </x-admin.panel>

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
                                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Occupied listing</p>
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
                                            <th class="admin-table-head-cell">Next rent due</th>
                                            <th class="admin-table-head-cell">Days remaining</th>
                                            <th class="admin-table-head-cell">Overdue</th>
                                            <th class="admin-table-head-cell">Rent status</th>
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
                                                    : ($occupancy->status === 'move_out_pending' ? 'warning' : 'success');
                                                $rentTone = $overdueDays && $overdueDays > 0
                                                    ? 'danger'
                                                    : (($daysRemaining !== null && $daysRemaining <= 30) ? 'warning' : 'info');
                                            @endphp
                                            <tr class="align-top">
                                                <td class="px-4 py-4 text-sm text-slate-700">
                                                    <div class="flex items-center gap-3">
                                                        <div class="h-10 w-10 overflow-hidden rounded-xl bg-slate-100">
                                                            @if ($occupancy->tenant?->avatar_path)
                                                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($occupancy->tenant->avatar_path) }}" alt="{{ $occupancy->tenant->name }}" class="h-full w-full object-cover">
                                                            @else
                                                                <div class="flex h-full w-full items-center justify-center text-xs font-semibold text-slate-500">
                                                                    {{ str($occupancy->tenant?->name ?? 'T')->substr(0, 2)->upper() }}
                                                                </div>
                                                            @endif
                                                        </div>
                                                        <div>
                                                            <p class="font-medium text-slate-900">{{ $occupancy->tenant?->name ?? 'Tenant' }}</p>
                                                            <p class="text-xs text-slate-500">{{ $occupancy->tenant?->email ?? 'No email' }}</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 text-sm text-slate-700">
                                                    <x-status-chip tone="{{ $statusTone }}">{{ str($occupancy->status)->headline() }}</x-status-chip>
                                                </td>
                                                <td class="px-4 py-4 text-sm text-slate-700">
                                                    {{ $dueAt && $isRent ? $dueAt->format('M j, Y') : 'Not required' }}
                                                </td>
                                                <td class="px-4 py-4 text-sm text-slate-700">
                                                    @if (! $isRent)
                                                        Not required
                                                    @elseif ($daysRemaining === null)
                                                        Unavailable
                                                    @elseif ($daysRemaining < 0)
                                                        0 days
                                                    @else
                                                        {{ $daysRemaining }} day{{ $daysRemaining === 1 ? '' : 's' }}
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4 text-sm text-slate-700">
                                                    @if (! $isRent)
                                                        Not required
                                                    @elseif ($overdueDays && $overdueDays > 0)
                                                        {{ $overdueDays }} day{{ $overdueDays === 1 ? '' : 's' }}
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4 text-sm text-slate-700">
                                                    @if ($isRent)
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
