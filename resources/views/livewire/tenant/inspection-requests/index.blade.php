<div class="admin-page">
    <div class="admin-page-inner">
        <x-admin.panel>
            <div class="space-y-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <p class="admin-eyebrow">Inspection requests</p>
                        <h2 class="admin-panel-title">Your inspection requests</h2>
                        <p class="admin-panel-copy">Track scheduled visits, completed inspections, and follow-up updates in one place.</p>
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
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="admin-micro-stat">
                            <span class="admin-micro-stat-label">Open requests</span>
                            <span class="admin-micro-stat-value">{{ $openInspectionRequestCount }}</span>
                            <p class="text-sm text-slate-600">Requests still waiting for scheduling or completion.</p>
                        </div>

                        <div class="admin-micro-stat">
                            <span class="admin-micro-stat-label">Scheduled requests</span>
                            <span class="admin-micro-stat-value">{{ $scheduledInspectionRequestCount }}</span>
                            <p class="text-sm text-slate-600">
                                {{ $upcomingInspectionRequest?->scheduled_at ? 'Next visit: '.$upcomingInspectionRequest->scheduled_at->format('M j, Y g:i A') : 'No upcoming visit has been confirmed yet.' }}
                            </p>
                        </div>

                        <div class="admin-micro-stat">
                            <span class="admin-micro-stat-label">Closed requests</span>
                            <span class="admin-micro-stat-value">{{ $closedInspectionRequestCount }}</span>
                            <p class="text-sm text-slate-600">Completed, rejected, or cancelled request history.</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="admin-table-head">
                                <tr>
                                    <th class="admin-table-head-cell">Property</th>
                                    <th class="admin-table-head-cell">Status</th>
                                    <th class="admin-table-head-cell">Scheduled</th>
                                    <th class="admin-table-head-cell">Outcome</th>
                                    <th class="admin-table-head-cell">Requested</th>
                                    <th class="admin-table-head-cell"></th>
                                </tr>
                            </thead>
                            <tbody class="admin-table-body">
                                @forelse ($inspectionRequests as $inspectionRequest)
                                    <tr class="align-top">
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p class="font-medium text-slate-900">{{ $inspectionRequest->property?->title }}</p>
                                            <p>{{ $inspectionRequest->property?->area }}, {{ $inspectionRequest->property?->city }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <span class="admin-badge admin-badge-neutral">
                                                {{ str($inspectionRequest->status)->headline() }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            {{ $inspectionRequest->scheduled_at?->format('M j, Y g:i A') ?: 'Not scheduled yet' }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            {{ $inspectionRequest->outcome_type ? $outcomes[$inspectionRequest->outcome_type] : 'No outcome yet' }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-500">{{ $inspectionRequest->created_at->diffForHumans() }}</td>
                                        <td class="px-4 py-4 text-right">
                                            <a href="{{ route('tenant.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()]) }}" class="admin-action-link">Open Request</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8">
                                            <x-admin.empty-state
                                                :title="$statusFilter === 'all' ? 'You have not submitted any inspection requests yet.' : 'No inspection requests match the current status filter.'"
                                                copy="Request activity will appear here automatically once your next inspection is booked."
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
