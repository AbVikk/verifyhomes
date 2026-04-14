<div class="admin-page">
    <div class="admin-page-inner space-y-6">
        @if (session('status'))
            <x-admin.alert>
                {{ session('status') }}
            </x-admin.alert>
        @endif

        <x-admin.panel>
            <div class="space-y-2">
                <p class="admin-eyebrow">Occupancy control</p>
                <h2 class="admin-panel-title">Post-payment occupancy workflows</h2>
                <p class="admin-panel-copy">Approve move-out requests, triage complaints, and follow up on overdue rent cycles.</p>
            </div>
        </x-admin.panel>

        <div class="grid gap-6 md:grid-cols-3">
            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Pending move-outs</p>
                    <p class="text-3xl font-semibold text-slate-950">{{ $summary['pending_move_outs'] ?? 0 }}</p>
                    <p class="text-sm text-slate-600">Requests waiting for a decision.</p>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Open complaints</p>
                    <p class="text-3xl font-semibold text-slate-950">{{ $summary['open_complaints'] ?? 0 }}</p>
                    <p class="text-sm text-slate-600">Issues awaiting admin follow-up.</p>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Overdue rent</p>
                    <p class="text-3xl font-semibold text-slate-950">{{ $summary['overdue_occupancies'] ?? 0 }}</p>
                    <p class="text-sm text-slate-600">Occupancies past the rent due date.</p>
                </div>
            </x-admin.panel>
        </div>

        <x-admin.panel>
            <div class="space-y-4">
                <div>
                    <p class="admin-eyebrow">Move-out requests</p>
                    <h3 class="admin-panel-title">Pending and historical move-out requests</h3>
                </div>

                @if (! $moveOutAvailable)
                    <x-admin.empty-state
                        title="Move-out tracking is not available yet."
                        copy="This section will appear once move-out tracking is enabled."
                    />
                @elseif ($moveOutRequests->isEmpty())
                    <x-admin.empty-state
                        title="No move-out requests yet."
                        copy="Tenant move-out requests will show here for approval."
                    />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="admin-table-head">
                                <tr>
                                    <th class="admin-table-head-cell">Tenant</th>
                                    <th class="admin-table-head-cell">Property</th>
                                    <th class="admin-table-head-cell">Status</th>
                                    <th class="admin-table-head-cell">Requested</th>
                                    <th class="admin-table-head-cell">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="admin-table-body">
                                @foreach ($moveOutRequests as $request)
                                    <tr class="align-top">
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p class="font-medium text-slate-900">{{ $request->tenant?->name ?? 'Tenant' }}</p>
                                            <p class="text-xs text-slate-500">{{ $request->tenant?->email ?? 'No email' }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            {{ $request->occupancy?->property?->title ?? 'Property' }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <span class="admin-badge admin-badge-neutral">{{ str($request->status)->headline() }}</span>
                                            @if ($request->decision_notes)
                                                <p class="mt-2 text-xs text-slate-500">{{ $request->decision_notes }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            {{ $request->requested_at?->format('M j, Y') ?? $request->created_at?->format('M j, Y') }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            @if ($request->status === 'pending')
                                                <div class="space-y-2">
                                                    <textarea
                                                        rows="2"
                                                        class="admin-control admin-control-textarea"
                                                        wire:model.defer="decisionNotes.{{ $request->id }}"
                                                        placeholder="Optional admin note"
                                                    ></textarea>
                                                    <div class="flex flex-wrap gap-2">
                                                        <button type="button" class="admin-button admin-button-primary" wire:click="approveMoveOut({{ $request->id }})" wire:loading.attr="disabled" wire:target="approveMoveOut({{ $request->id }})">
                                                            <span wire:loading.remove wire:target="approveMoveOut({{ $request->id }})">Approve</span>
                                                            <span wire:loading wire:target="approveMoveOut({{ $request->id }})">Approving...</span>
                                                        </button>
                                                        <button type="button" class="admin-button admin-button-secondary" wire:click="rejectMoveOut({{ $request->id }})" wire:loading.attr="disabled" wire:target="rejectMoveOut({{ $request->id }})">
                                                            <span wire:loading.remove wire:target="rejectMoveOut({{ $request->id }})">Reject</span>
                                                            <span wire:loading wire:target="rejectMoveOut({{ $request->id }})">Rejecting...</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            @else
                                                <span class="text-xs text-slate-500">Decision complete</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </x-admin.panel>

        <x-admin.panel>
            <div class="space-y-4">
                <div>
                    <p class="admin-eyebrow">Complaints</p>
                    <h3 class="admin-panel-title">Logged tenant issues</h3>
                </div>

                @if (! $complaintsAvailable)
                    <x-admin.empty-state
                        title="Complaints are not available yet."
                        copy="This section will appear once complaint tracking is enabled."
                    />
                @elseif ($complaints->isEmpty())
                    <x-admin.empty-state
                        title="No complaints logged yet."
                        copy="Tenant complaints tied to occupancy will show here."
                    />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="admin-table-head">
                                <tr>
                                    <th class="admin-table-head-cell">Tenant</th>
                                    <th class="admin-table-head-cell">Property</th>
                                    <th class="admin-table-head-cell">Category</th>
                                    <th class="admin-table-head-cell">Status</th>
                                    <th class="admin-table-head-cell">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="admin-table-body">
                                @foreach ($complaints as $complaint)
                                    <tr class="align-top">
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p class="font-medium text-slate-900">{{ $complaint->tenant?->name ?? 'Tenant' }}</p>
                                            <p class="text-xs text-slate-500">{{ $complaint->tenant?->email ?? 'No email' }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            {{ $complaint->occupancy?->property?->title ?? 'Property' }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p class="font-medium text-slate-900">{{ str($complaint->category)->headline() }}</p>
                                            <p class="mt-2 text-xs text-slate-500">{{ $complaint->description }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <span class="admin-badge admin-badge-neutral">{{ str($complaint->status)->headline() }}</span>
                                            @if ($complaint->admin_notes)
                                                <p class="mt-2 text-xs text-slate-500">{{ $complaint->admin_notes }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <div class="space-y-2">
                                                <textarea
                                                    rows="2"
                                                    class="admin-control admin-control-textarea"
                                                    wire:model.defer="complaintNotes.{{ $complaint->id }}"
                                                    placeholder="Optional admin note"
                                                ></textarea>
                                                <div class="flex flex-wrap gap-2">
                                                    <button type="button" class="admin-button admin-button-secondary" wire:click="markComplaintInReview({{ $complaint->id }})" wire:loading.attr="disabled" wire:target="markComplaintInReview({{ $complaint->id }})">
                                                        <span wire:loading.remove wire:target="markComplaintInReview({{ $complaint->id }})">Mark in review</span>
                                                        <span wire:loading wire:target="markComplaintInReview({{ $complaint->id }})">Updating...</span>
                                                    </button>
                                                    <button type="button" class="admin-button admin-button-primary" wire:click="resolveComplaint({{ $complaint->id }})" wire:loading.attr="disabled" wire:target="resolveComplaint({{ $complaint->id }})">
                                                        <span wire:loading.remove wire:target="resolveComplaint({{ $complaint->id }})">Resolve</span>
                                                        <span wire:loading wire:target="resolveComplaint({{ $complaint->id }})">Resolving...</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </x-admin.panel>

        <x-admin.panel>
            <div class="space-y-4">
                <div>
                    <p class="admin-eyebrow">Overdue follow-up</p>
                    <h3 class="admin-panel-title">Overdue rent cycles</h3>
                </div>

                @if (! $occupanciesAvailable)
                    <x-admin.empty-state
                        title="Occupancy tracking is not available yet."
                        copy="Overdue follow-ups will appear once occupancy tracking is enabled."
                    />
                @elseif ($overdueOccupancies->isEmpty())
                    <x-admin.empty-state
                        title="No overdue occupancies right now."
                        copy="Overdue rent follow-ups will show here once they appear."
                    />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="admin-table-head">
                                <tr>
                                    <th class="admin-table-head-cell">Tenant</th>
                                    <th class="admin-table-head-cell">Property</th>
                                    <th class="admin-table-head-cell">Overdue</th>
                                    <th class="admin-table-head-cell">Reminder</th>
                                </tr>
                            </thead>
                            <tbody class="admin-table-body">
                                @foreach ($overdueOccupancies as $occupancy)
                                    <tr class="align-top">
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p class="font-medium text-slate-900">{{ $occupancy->tenant?->name ?? 'Tenant' }}</p>
                                            <p class="text-xs text-slate-500">{{ $occupancy->tenant?->email ?? 'No email' }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            {{ $occupancy->property?->title ?? 'Property' }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            {{ $occupancy->paymentStatusLabel() }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <div class="space-y-2">
                                                @if ($occupancy->last_reminder_at)
                                                    <p class="text-xs text-slate-500">Last reminder: {{ $occupancy->last_reminder_at->format('M j, Y') }}</p>
                                                @endif
                                                    <button type="button" class="admin-button admin-button-secondary" wire:click="sendPaymentReminder({{ $occupancy->id }})" wire:loading.attr="disabled" wire:target="sendPaymentReminder({{ $occupancy->id }})">
                                                        <span wire:loading.remove wire:target="sendPaymentReminder({{ $occupancy->id }})">Send reminder</span>
                                                        <span wire:loading wire:target="sendPaymentReminder({{ $occupancy->id }})">Saving...</span>
                                                    </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </x-admin.panel>
    </div>
</div>
