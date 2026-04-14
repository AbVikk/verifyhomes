@php($showsOutcome = $detailAvailable && $inspectionRequest && $inspectionRequest->showsOutcome())

<div class="admin-page">
    <div class="admin-page-inner">
        @if (session('status'))
            <div class="admin-flash-success">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="admin-eyebrow">Inspection coordination</p>
                <h2 class="admin-panel-title">Inspection request</h2>
            </div>
            <a href="{{ route('landlord.inspection-requests.index') }}" class="admin-action-link">Back to requests</a>
        </div>

        @if (! $detailAvailable)
            <x-admin.panel>
                <x-admin.empty-state
                    title="Inspection request detail data is not available yet."
                    copy="This detail page will populate automatically after the inspection workflow tables are migrated in this environment."
                />
            </x-admin.panel>
        @else
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,0.95fr)]">
                <div class="space-y-6">
                    <x-admin.panel>
                        <div class="space-y-4">
                            <div>
                                <p class="admin-eyebrow">Property</p>
                                <h3 class="admin-panel-title">Inspection request</h3>
                            </div>

                            <dl class="grid gap-4 text-sm text-slate-700 md:grid-cols-2">
                                <div><dt class="font-medium text-slate-500">Title</dt><dd class="mt-1">{{ $inspectionRequest->property?->title }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Location</dt><dd class="mt-1">{{ $inspectionRequest->property?->area }}, {{ $inspectionRequest->property?->city }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Status</dt><dd class="mt-1">{{ str($inspectionRequest->status)->headline() }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Schedule</dt><dd class="mt-1">{{ $inspectionRequest->scheduled_at?->format('M j, Y g:i A') ?: 'Waiting for scheduling' }}</dd></div>
                                @if ($showsOutcome && $inspectionRequest->outcomeLabel())
                                    <div><dt class="font-medium text-slate-500">Outcome</dt><dd class="mt-1">{{ $inspectionRequest->outcomeLabel() }}</dd></div>
                                @endif
                                @if ($showsOutcome && $inspectionRequest->hasOutcomeNotes())
                                    <div class="md:col-span-2"><dt class="font-medium text-slate-500">Outcome notes</dt><dd class="mt-1">{{ $inspectionRequest->outcome_notes }}</dd></div>
                                @endif
                            </dl>

                            <div class="flex flex-wrap gap-3">
                                <a href="{{ route('landlord.properties.edit', $inspectionRequest->property) }}" class="admin-button admin-button-secondary">Edit property</a>
                                @if ($inspectionRequest->property?->isPubliclyVisible())
                                    <a href="{{ route('properties.show', $inspectionRequest->property) }}" class="admin-button admin-button-secondary">View Public Listing</a>
                                @endif
                            </div>
                        </div>
                    </x-admin.panel>

                    <x-admin.panel>
                        <div class="space-y-4">
                            <div>
                                <p class="admin-eyebrow">Request</p>
                                <h3 class="admin-panel-title">Submitted details</h3>
                                <p class="admin-panel-copy">Tenant contact is private.</p>
                            </div>

                            <dl class="grid gap-4 text-sm text-slate-700 md:grid-cols-2">
                                <div><dt class="font-medium text-slate-500">Tenant name</dt><dd class="mt-1">{{ $inspectionRequest->tenant?->name }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Preferred date</dt><dd class="mt-1">{{ $inspectionRequest->preferred_date?->toFormattedDateString() ?: 'Not provided' }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Preferred time note</dt><dd class="mt-1">{{ $inspectionRequest->preferred_time_note ?: 'Not provided' }}</dd></div>
                                <div class="md:col-span-2"><dt class="font-medium text-slate-500">Message</dt><dd class="mt-1">{{ $inspectionRequest->message ?: 'No message provided.' }}</dd></div>
                            </dl>
                        </div>
                    </x-admin.panel>

                    @if (! $showsOutcome)
                        <x-admin.panel>
                            <div class="space-y-2">
                                <p class="admin-eyebrow">Inspection outcome</p>
                                <h3 class="admin-panel-title">Outcome visibility</h3>
                                <p class="admin-panel-copy">Shown after admin records the result.</p>
                            </div>
                        </x-admin.panel>
                    @endif

                    <x-admin.panel>
                        <div class="space-y-4">
                            <div>
                                <p class="admin-eyebrow">Timeline</p>
                                <h3 class="admin-panel-title">Status history</h3>
                            </div>

                            <div class="space-y-3">
                                @forelse ($inspectionRequest->statusHistories as $history)
                                    <div class="admin-subsurface p-4">
                                        <div class="flex items-center justify-between gap-4">
                                            <p class="font-medium text-slate-900">
                                                @if ($history->from_status)
                                                    {{ str($history->from_status)->headline() }} to {{ str($history->to_status)->headline() }}
                                                @else
                                                    Request moved to {{ str($history->to_status)->headline() }}
                                                @endif
                                            </p>
                                            <p class="text-xs text-slate-500">{{ $history->created_at->diffForHumans() }}</p>
                                        </div>
                                    </div>
                                @empty
                                    <x-admin.empty-state
                                        title="No request history yet."
                                        copy="Updates will appear here."
                                    />
                                @endforelse
                            </div>
                        </div>
                    </x-admin.panel>
                </div>

                <div class="space-y-6">
                    <x-admin.panel>
                        <div class="space-y-4">
                            <div>
                                <p class="admin-eyebrow">Next step</p>
                                <h3 class="admin-panel-title">Next step</h3>
                            </div>

                            <dl class="space-y-4 text-sm text-slate-700">
                                <div>
                                    <dt class="font-medium text-slate-500">Current action</dt>
                                    <dd class="mt-1">{{ $this->nextStepSummary($inspectionRequest) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </x-admin.panel>

                    <x-admin.panel>
                        <div class="space-y-4">
                            <div>
                                <p class="admin-eyebrow">Landlord note</p>
                                <h3 class="admin-panel-title">Coordination note</h3>
                                <p class="admin-panel-copy">Share access details or readiness notes with admin.</p>
                            </div>

                            <div>
                                <label for="landlordNote" class="admin-label">Note for admin</label>
                                <textarea wire:model.defer="landlordNote" id="landlordNote" rows="6" class="admin-control admin-control-textarea"></textarea>
                                @error('landlordNote') <p class="admin-error">{{ $message }}</p> @enderror
                            </div>

                            <button wire:click="saveLandlordNote" type="button" class="admin-button admin-button-primary">
                                Save note
                            </button>
                        </div>
                    </x-admin.panel>
                </div>
            </div>
        @endif
    </div>
</div>
