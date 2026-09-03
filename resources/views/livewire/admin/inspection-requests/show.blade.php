@php
    $isPaid = $latestPaymentTransaction?->status === 'paid';
    $isAwaitingTenantResponse = $inspectionRequest?->scheduleNeedsTenantResponse() ?? false;
    $tenantRequestedAnotherDate = $inspectionRequest?->status === 'requested' && $inspectionRequest?->schedule_response === 'reschedule_requested';
    $isNewRequest = $inspectionRequest?->status === 'requested' && ! $tenantRequestedAnotherDate;
    $scheduleAccepted = $inspectionRequest?->scheduleIsAcceptedOrLegacy() ?? false;
    $canCompleteInspection = $isPaid && $scheduleAccepted;
    $scheduleActionLabel = $tenantRequestedAnotherDate
        ? 'Send new schedule'
        : ($isPaid ? 'Send updated schedule' : ($isNewRequest ? 'Send proposed schedule' : 'Send updated schedule'));
@endphp

<div class="admin-page">
    <div class="admin-page-inner">
        @if (session('status'))
            <x-admin.alert>{{ session('status') }}</x-admin.alert>
        @endif

        @if (! $detailAvailable)
            <x-admin.panel>
                <x-admin.empty-state title="Inspection request detail data is not available yet in this environment." copy="This page will appear when inspection data is available." />
            </x-admin.panel>
        @else
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(360px,0.9fr)]">
                <div class="space-y-6">
                    <x-admin.panel>
                        <div class="space-y-4">
                            <div>
                                <p class="admin-eyebrow">Current inspection status</p>
                                <h3 class="admin-panel-title">{{ str($inspectionRequest->status)->headline() }}</h3>
                            </div>
                            <div class="admin-data-box">
                                @if ($isNewRequest)
                                    <p class="font-semibold text-slate-900">New inspection request</p>
                                    <p class="mt-1 text-sm text-slate-700">Choose a suitable visit time and send the tenant a proposed schedule.</p>
                                @elseif ($tenantRequestedAnotherDate)
                                    <p class="font-semibold text-slate-900">Tenant requested another date</p>
                                    <p class="mt-1 text-sm text-slate-700">Review the tenant's alternative-date note and send a new proposal.</p>
                                @elseif ($isAwaitingTenantResponse)
                                    <p class="font-semibold text-slate-900">Waiting for tenant response</p>
                                    <p class="mt-1 text-sm text-slate-700">The tenant must accept this proposed time or request another date before booking-fee checkout is available.</p>
                                @elseif ($scheduleAccepted && ! $isPaid)
                                    <p class="font-semibold text-slate-900">Schedule accepted</p>
                                    <p class="mt-1 text-sm text-slate-700">Waiting for booking fee. The tenant can now review the terms and complete checkout.</p>
                                @elseif ($isPaid && ! $inspectionRequest->showsOutcome())
                                    <p class="font-semibold text-slate-900">Booking fee paid</p>
                                    <p class="mt-1 text-sm text-slate-700">Inspection booked. Coordinate the visit and record the outcome when it is ready to be completed.</p>
                                @elseif ($inspectionRequest->showsOutcome())
                                    <p class="font-semibold text-slate-900">Inspection completed</p>
                                    <p class="mt-1 text-sm text-slate-700">The tenant can review the recorded outcome and any available next step.</p>
                                @else
                                    <p class="font-semibold text-slate-900">Inspection update</p>
                                    <p class="mt-1 text-sm text-slate-700">This inspection is currently {{ str($inspectionRequest->status)->headline() }}.</p>
                                @endif
                            </div>
                        </div>
                    </x-admin.panel>

                    <x-admin.panel>
                        <div class="space-y-4">
                            <div>
                                <p class="admin-eyebrow">Tenant request</p>
                                <h3 class="admin-panel-title">Request summary</h3>
                            </div>
                            <dl class="grid gap-4 text-sm text-slate-700 md:grid-cols-2">
                                <div><dt class="font-medium text-slate-500">Tenant</dt><dd class="mt-1 break-words">{{ $inspectionRequest->tenant?->name }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Preferred date</dt><dd class="mt-1">{{ $inspectionRequest->preferred_date?->toFormattedDateString() ?: 'Not provided' }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Preferred time note</dt><dd class="mt-1 break-words">{{ $inspectionRequest->preferred_time_note ?: 'Not provided' }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Property</dt><dd class="mt-1 break-words">{{ $inspectionRequest->property?->title }}</dd></div>
                                <div class="md:col-span-2"><dt class="font-medium text-slate-500">Tenant message</dt><dd class="mt-1 break-words">{{ $inspectionRequest->message ?: 'No message provided.' }}</dd></div>
                            </dl>
                        </div>
                    </x-admin.panel>

                    @if ($inspectionRequest->scheduled_at)
                        <x-admin.panel>
                            <div class="space-y-4">
                                <div>
                                    <p class="admin-eyebrow">Schedule</p>
                                    <h3 class="admin-panel-title">{{ $isAwaitingTenantResponse ? 'Proposed schedule' : 'Inspection date' }}</h3>
                                </div>
                                <dl class="grid gap-4 text-sm text-slate-700 md:grid-cols-2">
                                    <div><dt class="font-medium text-slate-500">Date and time</dt><dd class="mt-1 font-medium text-slate-900">{{ $inspectionRequest->scheduled_at->format('M j, Y g:i A') }}</dd></div>
                                    @if ($isAwaitingTenantResponse)
                                        <div><dt class="font-medium text-slate-500">Date sent</dt><dd class="mt-1">{{ $inspectionRequest->updated_at?->format('M j, Y g:i A') ?: 'Not available' }}</dd></div>
                                    @elseif ($scheduleAccepted)
                                        <div><dt class="font-medium text-slate-500">Tenant acceptance</dt><dd class="mt-1">Accepted {{ $inspectionRequest->schedule_responded_at?->format('M j, Y g:i A') ?: '' }}</dd></div>
                                    @endif
                                </dl>
                                @if ($isAwaitingTenantResponse && ! $showScheduleEditor)
                                    <x-admin.button wire:click="changeProposedSchedule" wire:loading.attr="disabled" wire:target="changeProposedSchedule" variant="secondary">Change proposed schedule</x-admin.button>
                                @elseif ($isPaid && $scheduleAccepted && ! $showScheduleEditor && ! $inspectionRequest->showsOutcome())
                                    <x-admin.button wire:click="rescheduleInspection" wire:loading.attr="disabled" wire:target="rescheduleInspection" variant="secondary">Reschedule inspection</x-admin.button>
                                @endif
                            </div>
                        </x-admin.panel>
                    @endif

                    @if ($tenantRequestedAnotherDate)
                        <x-admin.panel>
                            <div class="space-y-3">
                                <p class="admin-eyebrow">Tenant response</p>
                                <h3 class="admin-panel-title">Requested alternative</h3>
                                <p class="break-words text-sm text-slate-700">{{ $inspectionRequest->schedule_response_notes ?: 'No alternative-date note was provided.' }}</p>
                                <p class="text-xs text-slate-500">Responded {{ $inspectionRequest->schedule_responded_at?->format('M j, Y g:i A') ?: 'recently' }}</p>
                            </div>
                        </x-admin.panel>
                    @endif

                    @if ($inspectionRequest->showsOutcome() && ($inspectionRequest->outcomeLabel() || $inspectionRequest->hasOutcomeNotes()))
                        <x-admin.panel>
                            <div class="space-y-3">
                                <p class="admin-eyebrow">Inspection outcome</p>
                                <h3 class="admin-panel-title">Recorded outcome</h3>
                                <p class="font-medium text-slate-900">{{ $inspectionRequest->outcomeLabel() ?: 'No outcome selected' }}</p>
                                @if ($inspectionRequest->hasOutcomeNotes())
                                    <p class="break-words text-sm text-slate-700">{{ $inspectionRequest->outcome_notes }}</p>
                                @endif
                            </div>
                        </x-admin.panel>
                    @endif
                </div>

                <div class="space-y-6">
                    @if ($showScheduleEditor)
                        <x-admin.panel>
                            <div class="space-y-4">
                                <div>
                                    <p class="admin-eyebrow">Schedule action</p>
                                    <h3 class="admin-panel-title">{{ $scheduleActionLabel }}</h3>
                                    <p class="admin-panel-copy">The tenant will be notified and must accept this time before the inspection can proceed.</p>
                                </div>
                                <div>
                                    <x-admin.label for="scheduledAt">Proposed date and time</x-admin.label>
                                    <x-admin.input wire:model.defer="scheduledAt" id="scheduledAt" type="datetime-local" />
                                    <x-admin.error for="scheduledAt" />
                                </div>
                                <div>
                                    <x-admin.label for="scheduleAdminNotes">Scheduling note (optional)</x-admin.label>
                                    <x-admin.textarea wire:model.defer="adminNotes" id="scheduleAdminNotes" rows="4" />
                                    <x-admin.error for="adminNotes" />
                                </div>
                                <x-admin.button wire:click="changeStatus('scheduled')" wire:loading.attr="disabled" wire:target="changeStatus" class="w-full sm:w-auto">
                                    <span wire:loading.remove wire:target="changeStatus">{{ $scheduleActionLabel }}</span>
                                    <span wire:loading wire:target="changeStatus">Sending...</span>
                                </x-admin.button>
                            </div>
                        </x-admin.panel>
                    @endif

                    @if ($canCompleteInspection)
                        <x-admin.panel>
                            <div class="space-y-4">
                                <div>
                                    <p class="admin-eyebrow">Inspection completion</p>
                                    <h3 class="admin-panel-title">Record inspection outcome</h3>
                                    <p class="admin-panel-copy">Booking fee is paid. Record the visit result when the inspection is ready to be closed.</p>
                                </div>
                                <div>
                                    <x-admin.label for="outcomeType">Inspection outcome</x-admin.label>
                                    <x-admin.select wire:model.defer="outcomeType" id="outcomeType">
                                        <option value="">Select an outcome</option>
                                        @foreach ($outcomeOptions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </x-admin.select>
                                    <x-admin.error for="outcomeType" />
                                </div>
                                <div>
                                    <x-admin.label for="outcomeNotes">Inspection outcome notes</x-admin.label>
                                    <x-admin.textarea wire:model.defer="outcomeNotes" id="outcomeNotes" rows="4" />
                                    <x-admin.error for="outcomeNotes" />
                                </div>
                                <x-admin.button wire:click="changeStatus('completed')" wire:loading.attr="disabled" wire:target="changeStatus" variant="success" class="w-full sm:w-auto">
                                    <span wire:loading.remove wire:target="changeStatus">Complete inspection</span>
                                    <span wire:loading wire:target="changeStatus">Completing...</span>
                                </x-admin.button>
                            </div>
                        </x-admin.panel>
                    @endif

                    <x-admin.panel>
                        <div class="space-y-4">
                            <div><p class="admin-eyebrow">Coordination notes</p><h3 class="admin-panel-title">Internal notes</h3></div>
                            <div>
                                <x-admin.label for="adminNotes">Note</x-admin.label>
                                <x-admin.textarea wire:model.defer="adminNotes" id="adminNotes" rows="4" />
                                <x-admin.error for="adminNotes" />
                            </div>
                            <x-admin.button wire:click="saveCoordinationNotes" wire:loading.attr="disabled" wire:target="saveCoordinationNotes" variant="secondary" class="w-full sm:w-auto">
                                <span wire:loading.remove wire:target="saveCoordinationNotes">Save notes</span>
                                <span wire:loading wire:target="saveCoordinationNotes">Saving...</span>
                            </x-admin.button>
                            <div class="admin-data-box"><p class="text-sm font-medium text-slate-900">Landlord coordination note</p><p class="mt-2 break-words text-sm text-slate-700">{{ $inspectionRequest->landlord_note ?: 'No landlord note yet.' }}</p></div>
                        </div>
                    </x-admin.panel>

                    @if ($paymentTransactionsAvailable && ($scheduleAccepted || $isPaid || $latestPaymentTransaction))
                        <x-admin.panel>
                            <div class="space-y-3">
                                <p class="admin-eyebrow">Booking payment</p>
                                <h3 class="admin-panel-title">{{ $isPaid ? 'Booking fee paid' : 'Waiting for booking fee' }}</h3>
                                <p class="text-sm text-slate-700">{{ $this->paymentReadiness($latestPaymentTransaction) }}</p>
                                @if ($latestPaymentTransaction)
                                    <p class="text-sm text-slate-600">{{ $this->paymentStatusSummary($latestPaymentTransaction) }}</p>
                                    <a href="{{ route('admin.payments.index', ['reference' => $latestPaymentTransaction->reference]) }}" class="admin-inline-link">Open payment record</a>
                                @endif
                            </div>
                        </x-admin.panel>
                    @endif

                    @if ($scheduleAccepted && ! $isPaid)
                        <div class="flex flex-wrap gap-3">
                            <x-admin.button wire:click="changeStatus('cancelled')" wire:loading.attr="disabled" wire:target="changeStatus" variant="warning">Cancel</x-admin.button>
                            <x-admin.button wire:click="changeStatus('rejected')" wire:loading.attr="disabled" wire:target="changeStatus" variant="danger">Reject</x-admin.button>
                        </div>
                    @endif

                    <x-admin.partials.status-history-card title="Status history" description="Every change is recorded here." :histories="$inspectionRequest->statusHistories" fallbackChangedBy="System" />
                </div>
            </div>
        @endif
    </div>
</div>
