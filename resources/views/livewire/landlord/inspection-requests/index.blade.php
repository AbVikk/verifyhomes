<div class="admin-page">
    <div class="admin-page-inner">
        <x-admin.panel>
            <div class="space-y-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <p class="admin-eyebrow">Inspection coordination</p>
                        <h2 class="admin-panel-title">Requests for your properties</h2>
                        <p class="admin-panel-copy">Stay ready for admin updates and scheduled visits.</p>
                    </div>

                    @if ($inspectionRequestsAvailable)
                        <div class="w-full xl:w-64">
                            <label for="statusFilter" class="admin-label">Filter by status</label>
                            <select wire:model.live="statusFilter" id="statusFilter" class="admin-control admin-control-select">
                                <option value="all">All statuses</option>
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>

                @if (! $inspectionRequestsAvailable)
                    <x-admin.empty-state
                        title="Inspection request data is not available yet."
                        copy="This page will populate automatically after the inspection workflow tables are migrated in this environment."
                    />
                @else
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="admin-micro-stat">
                            <span class="admin-micro-stat-label">Open requests</span>
                            <span class="admin-micro-stat-value">{{ $openInspectionRequestCount }}</span>
                            <p class="text-sm text-slate-600">Active.</p>
                        </div>

                        <div class="admin-micro-stat">
                            <span class="admin-micro-stat-label">Requested visits</span>
                            <span class="admin-micro-stat-value">{{ $requestedInspectionRequestCount }}</span>
                            <p class="text-sm text-slate-600">May need your note.</p>
                        </div>

                        <div class="admin-micro-stat">
                            <span class="admin-micro-stat-label">Scheduled requests</span>
                            <span class="admin-micro-stat-value">{{ $scheduledInspectionRequestCount }}</span>
                            <p class="text-sm text-slate-600">Booked.</p>
                        </div>

                        <div class="admin-micro-stat">
                            <span class="admin-micro-stat-label">Closed requests</span>
                            <span class="admin-micro-stat-value">{{ $closedInspectionRequestCount }}</span>
                            <p class="text-sm text-slate-600">No action needed.</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="admin-table-head">
                                <tr>
                                    <th class="admin-table-head-cell">Property</th>
                                    <th class="admin-table-head-cell">Status</th>
                                    <th class="admin-table-head-cell">Preferred timing</th>
                                    <th class="admin-table-head-cell">Next step</th>
                                    <th class="admin-table-head-cell"></th>
                                </tr>
                            </thead>
                            <tbody class="admin-table-body">
                                @forelse ($inspectionRequests as $inspectionRequest)
                                    <tr class="align-top">
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p class="font-medium text-slate-900">{{ $inspectionRequest->property?->title }}</p>
                                            <p>{{ $inspectionRequest->property?->area }}, {{ $inspectionRequest->property?->city }}</p>
                                            <p class="mt-1 text-xs text-slate-500">Requested {{ $inspectionRequest->created_at->diffForHumans() }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <span class="admin-badge admin-badge-neutral">
                                                {{ str($inspectionRequest->status)->headline() }}
                                            </span>
                                            @if ($inspectionRequest->scheduled_at)
                                                <p class="mt-2 text-xs text-slate-500">{{ $inspectionRequest->scheduled_at->format('M j, Y g:i A') }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p>{{ $inspectionRequest->preferred_date?->toFormattedDateString() ?: 'No date provided' }}</p>
                                            <p class="text-slate-500">{{ $inspectionRequest->preferred_time_note ?: 'No time note' }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            {{ $this->nextStepSummary($inspectionRequest) }}
                                        </td>
                                        <td class="px-4 py-4 text-right">
                                            <a href="{{ route('landlord.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()]) }}" class="admin-action-link">View request</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-8">
                                            <x-admin.empty-state
                                                :title="$statusFilter === 'all' ? 'No inspection requests found for your properties yet.' : 'No inspection requests match the current status filter.'"
                                                copy="New requests will appear here."
                                            />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $inspectionRequests->links() }}
                @endif
            </div>
        </x-admin.panel>
    </div>
</div>
