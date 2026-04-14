<div class="admin-page">
    <div class="admin-page-inner">
        <x-admin.panel>
            <div class="space-y-4">
                @if (session('status'))
                    <x-admin.alert :tone="session('statusTone', 'success')">
                        {{ session('status') }}
                    </x-admin.alert>
                @endif

                <div class="space-y-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-950">Inspection control center</h3>
                        <p class="mt-1 text-sm text-slate-600">Receive requests, confirm payment, schedule visits, and record outcomes.</p>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.35fr)_minmax(0,0.65fr)]">
                        <div>
                            <x-admin.label for="search">Search inspection requests</x-admin.label>
                            <x-admin.input
                                wire:model.live.debounce.300ms="search"
                                id="search"
                                type="search"
                                placeholder="Search property, tenant, email, city, or area"
                            />
                        </div>

                        <div>
                            <x-admin.label for="statusFilter">Filter by status</x-admin.label>
                            @if ($inspectionRequestsAvailable)
                                <x-admin.select wire:model.live="statusFilter" id="statusFilter">
                                    <option value="all">All statuses</option>
                                    @foreach ($statuses as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </x-admin.select>
                            @else
                                <x-admin.select id="statusFilter" disabled>
                                    <option value="all">All statuses</option>
                                    @foreach ($statuses as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </x-admin.select>
                            @endif
                        </div>
                    </div>
                </div>

                @if (! $inspectionRequestsAvailable)
                    <x-admin.empty-state
                        title="Inspection request data is not available yet in this environment."
                        copy="This page will appear when inspection data is available."
                    />
                @else
                    @if (! $inspectionHistoryAvailable)
                        <x-admin.alert tone="warning">
                            Bulk actions are unavailable until request history is available.
                        </x-admin.alert>
                    @endif

                    <div class="space-y-2">
                        <div class="admin-bulk-bar">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="admin-bulk-count">{{ count($selectedInspectionRequestIds) }} selected</span>
                                <x-admin.button wire:click="bulkSchedule" variant="secondary" size="sm" disabled>
                                    Schedule
                                </x-admin.button>
                                <x-admin.button wire:click="bulkComplete" variant="secondary" size="sm" disabled>
                                    Complete
                                </x-admin.button>
                                <x-admin.button
                                    wire:click="bulkReject"
                                    variant="danger"
                                    size="sm"
                                    :disabled="count($selectedInspectionRequestIds) === 0 || ! $bulkActionsAvailable"
                                >
                                    Reject
                                </x-admin.button>
                                <x-admin.button
                                    wire:click="bulkCancel"
                                    variant="warning"
                                    size="sm"
                                    :disabled="count($selectedInspectionRequestIds) === 0 || ! $bulkActionsAvailable"
                                >
                                    Cancel
                                </x-admin.button>
                            </div>

                            <x-admin.button
                                wire:click="clearSelection"
                                variant="quiet"
                                size="sm"
                                :disabled="count($selectedInspectionRequestIds) === 0"
                            >
                                Clear
                            </x-admin.button>
                        </div>

                        <p class="admin-help">
                            Schedule and outcome updates happen on each request page.
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="admin-table-head">
                                <tr>
                                    <th class="admin-table-head-cell w-14">
                                        <label class="inline-flex items-center justify-center">
                                            <span class="sr-only">Select all inspection requests on this page</span>
                                            <input
                                                type="checkbox"
                                                class="admin-checkbox"
                                                wire:model.live="selectPage"
                                                @disabled(! $bulkActionsAvailable)
                                            />
                                        </label>
                                    </th>
                                    <th class="admin-table-head-cell">Property</th>
                                    <th class="admin-table-head-cell">Tenant</th>
                                    <th class="admin-table-head-cell">Request status</th>
                                    <th class="admin-table-head-cell">Preferred timing</th>
                                    <th class="admin-table-head-cell">Schedule / landlord</th>
                                    <th class="admin-table-head-cell">Requested</th>
                                    <th class="admin-table-head-cell"></th>
                                </tr>
                        </thead>
                        <tbody class="admin-table-body">
                            @forelse ($inspectionRequests as $inspectionRequest)
                                <tr>
                                    <td class="px-4 py-4 align-top">
                                        <label class="inline-flex items-center justify-center">
                                            <span class="sr-only">Select inspection request for {{ $inspectionRequest->property?->title }}</span>
                                            <input
                                                type="checkbox"
                                                class="admin-checkbox"
                                                wire:model.live="selectedInspectionRequestIds"
                                                value="{{ $inspectionRequest->id }}"
                                                @disabled(! $bulkActionsAvailable)
                                            />
                                        </label>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-slate-700">
                                            <p class="font-medium text-slate-900">{{ $inspectionRequest->property?->title }}</p>
                                            <p>{{ $inspectionRequest->property?->area }}, {{ $inspectionRequest->property?->city }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p>{{ $inspectionRequest->tenant?->name }}</p>
                                            <p class="text-slate-500">{{ $inspectionRequest->tenant?->email }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <x-admin.badge>
                                                {{ str($inspectionRequest->status)->headline() }}
                                            </x-admin.badge>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p>{{ $inspectionRequest->preferred_date?->toFormattedDateString() ?: 'No date provided' }}</p>
                                            <p class="text-slate-500">{{ $inspectionRequest->preferred_time_note ?: 'No time note' }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p>{{ $inspectionRequest->scheduled_at?->format('M j, Y g:i A') ?: 'Not scheduled yet' }}</p>
                                            <p class="text-slate-500">{{ $inspectionRequest->landlord_note ? 'Landlord note received' : 'No landlord note yet' }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-500">{{ $inspectionRequest->created_at->diffForHumans() }}</td>
                                        <td class="px-4 py-4 text-right"><x-admin.action-link href="{{ route('admin.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()]) }}">View request</x-admin.action-link></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-8">
                                            <x-admin.empty-state
                                                title="No inspection requests match the current search or filters."
                                                copy="Try a broader search or clear the filter."
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
