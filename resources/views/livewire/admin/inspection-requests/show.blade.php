@php($showsOutcome = $detailAvailable && $inspectionRequest && $inspectionRequest->showsOutcome())

<div class="admin-page">
    <div class="admin-page-inner">
        @if (session('status'))
            <x-admin.alert>
                {{ session('status') }}
            </x-admin.alert>
        @endif

        @if (! $detailAvailable)
            <x-admin.panel>
                <x-admin.empty-state
                    title="Inspection request detail data is not available yet in this environment."
                    copy="This page will appear when inspection data is available."
                />
            </x-admin.panel>
        @else
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,0.95fr)]">
                    <div class="space-y-6">
                        <x-admin.panel>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-slate-950">Inspection control center</h3>
                                    <p class="mt-1 text-sm text-slate-600">Manage the inspection request here.</p>
                                </div>

                                <dl class="grid gap-4 md:grid-cols-2 text-sm text-slate-700">
                                    <div><dt class="font-medium text-slate-500">Title</dt><dd class="mt-1">{{ $inspectionRequest->property?->title }}</dd></div>
                                    <div><dt class="font-medium text-slate-500">Location</dt><dd class="mt-1">{{ $inspectionRequest->property?->area }}, {{ $inspectionRequest->property?->city }}</dd></div>
                                    <div><dt class="font-medium text-slate-500">Property type</dt><dd class="mt-1">{{ str($inspectionRequest->property?->property_type)->headline() }}</dd></div>
                                    <div><dt class="font-medium text-slate-500">{{ $inspectionRequest->property?->primaryPriceLabel() ?? 'Price' }}</dt><dd class="mt-1">{{ $this->formatMoney($inspectionRequest->property?->rent_amount) }}</dd></div>
                                    <div><dt class="font-medium text-slate-500">Landlord</dt><dd class="mt-1">{{ $inspectionRequest->property?->landlord?->name ?: 'Not available' }}</dd></div>
                                    <div><dt class="font-medium text-slate-500">Landlord view</dt><dd class="mt-1">The landlord only sees admin updates</dd></div>
                                </dl>
                            </div>
                        </x-admin.panel>

                        <x-admin.panel>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-slate-950">Tenant summary</h3>
                                </div>

                                <dl class="grid gap-4 md:grid-cols-2 text-sm text-slate-700">
                                    <div><dt class="font-medium text-slate-500">Name</dt><dd class="mt-1">{{ $inspectionRequest->tenant?->name }}</dd></div>
                                    <div><dt class="font-medium text-slate-500">Email</dt><dd class="mt-1">{{ $inspectionRequest->tenant?->email }}</dd></div>
                                    <div><dt class="font-medium text-slate-500">Preferred date</dt><dd class="mt-1">{{ $inspectionRequest->preferred_date?->toFormattedDateString() ?: 'Not provided' }}</dd></div>
                                    <div><dt class="font-medium text-slate-500">Preferred time note</dt><dd class="mt-1">{{ $inspectionRequest->preferred_time_note ?: 'Not provided' }}</dd></div>
                                    <div class="md:col-span-2"><dt class="font-medium text-slate-500">Message</dt><dd class="mt-1">{{ $inspectionRequest->message ?: 'No message provided.' }}</dd></div>
                                </dl>
                            </div>
                        </x-admin.panel>
                    </div>

                    <div class="space-y-6">
                        <x-admin.panel>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-slate-950">Coordinator actions</h3>
                                    <p class="mt-1 text-sm text-slate-600">Status: {{ str($inspectionRequest->status)->headline() }}</p>
                                </div>

                                <div class="admin-data-box">
                                    <dl class="grid gap-4 text-sm text-slate-700 md:grid-cols-2">
                                        <div>
                                            <dt class="font-medium text-slate-500">Payment readiness</dt>
                                            <dd class="mt-1">{{ $this->paymentReadiness($latestPaymentTransaction) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="font-medium text-slate-500">Scheduling responsibility</dt>
                                            <dd class="mt-1">
                                                @if ($latestPaymentTransaction && in_array($latestPaymentTransaction->status, ['initiated', 'pending'], true))
                                                    Hold scheduling until payment is verified
                                                @else
                                                    {{ $inspectionRequest->scheduled_at ? 'Scheduled by admin' : 'Needs scheduling' }}
                                                @endif
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="font-medium text-slate-500">Landlord coordination</dt>
                                            <dd class="mt-1">{{ $inspectionRequest->landlord_note ? 'Landlord note received' : 'No landlord note yet' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="font-medium text-slate-500">Outcome status</dt>
                                            <dd class="mt-1">{{ $showsOutcome ? ($inspectionRequest->outcomeLabel() ?: 'Completed without outcome label') : 'Not ready' }}</dd>
                                        </div>
                                    </dl>
                                </div>

                                <div>
                                    <x-admin.label for="scheduledAt">Scheduled date and time</x-admin.label>
                                    <x-admin.input wire:model.defer="scheduledAt" id="scheduledAt" type="datetime-local" />
                                    <x-admin.error for="scheduledAt" />
                                </div>

                                <div>
                                    <x-admin.label for="adminNotes">Admin notes</x-admin.label>
                                    <x-admin.textarea wire:model.defer="adminNotes" id="adminNotes" rows="4" />
                                    <x-admin.error for="adminNotes" />
                                </div>

                                <div>
                                    <x-admin.label for="outcomeNotes">Inspection outcome notes</x-admin.label>
                                    <x-admin.textarea wire:model.defer="outcomeNotes" id="outcomeNotes" rows="4" />
                                    <x-admin.error for="outcomeNotes" />
                                </div>

                                <div>
                                    <x-admin.label for="outcomeType">Inspection outcome</x-admin.label>
                                    <x-admin.select wire:model.defer="outcomeType" id="outcomeType">
                                        <option value="">No outcome selected</option>
                                        @foreach ($outcomeOptions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </x-admin.select>
                                    <x-admin.error for="outcomeType" />
                                </div>

                                @if ($paymentTransactionsAvailable)
                                    <div class="admin-data-box">
                                        <p class="text-sm font-medium text-slate-900">Latest payment status</p>
                                        <p class="mt-2 text-sm text-slate-700">{{ $this->paymentStatusSummary($latestPaymentTransaction) }}</p>
                                        @if ($latestPaymentTransaction)
                                            <p class="mt-2 text-xs text-slate-500">
                                                {{ str($latestPaymentTransaction->status)->headline() }} via {{ $this->providerLabel($latestPaymentTransaction->provider) }}
                                                at reference <span class="font-mono text-[11px] text-slate-600">{{ $latestPaymentTransaction->reference }}</span>.
                                            </p>
                                            <a href="{{ route('admin.payments.index', ['reference' => $latestPaymentTransaction->reference]) }}" class="admin-inline-link">Open payment record</a>
                                        @else
                                            <a href="{{ route('admin.payments.index') }}" class="admin-inline-link">Open payments workspace</a>
                                        @endif
                                    </div>
                                @endif

                                <div class="flex flex-wrap gap-3">
                                    <x-admin.button wire:click="changeStatus('scheduled')" wire:loading.attr="disabled" wire:target="changeStatus">
                                        <span wire:loading.remove wire:target="changeStatus">Schedule</span>
                                        <span wire:loading wire:target="changeStatus">Processing...</span>
                                    </x-admin.button>
                                    <x-admin.button wire:click="changeStatus('completed')" wire:loading.attr="disabled" wire:target="changeStatus" variant="success">
                                        <span wire:loading.remove wire:target="changeStatus">Complete</span>
                                        <span wire:loading wire:target="changeStatus">Processing...</span>
                                    </x-admin.button>
                                    <x-admin.button wire:click="changeStatus('rejected')" wire:loading.attr="disabled" wire:target="changeStatus" variant="danger">
                                        <span wire:loading.remove wire:target="changeStatus">Reject</span>
                                        <span wire:loading wire:target="changeStatus">Processing...</span>
                                    </x-admin.button>
                                    <x-admin.button wire:click="changeStatus('cancelled')" wire:loading.attr="disabled" wire:target="changeStatus" variant="warning">
                                        <span wire:loading.remove wire:target="changeStatus">Cancel</span>
                                        <span wire:loading wire:target="changeStatus">Processing...</span>
                                    </x-admin.button>
                                    <x-admin.button wire:click="saveCoordinationNotes" wire:loading.attr="disabled" wire:target="saveCoordinationNotes" variant="secondary">
                                        <span wire:loading.remove wire:target="saveCoordinationNotes">Save notes</span>
                                        <span wire:loading wire:target="saveCoordinationNotes">Saving...</span>
                                    </x-admin.button>
                                </div>

                                <div class="admin-data-box">
                                    <p class="text-sm font-medium text-slate-900">Landlord coordination note</p>
                                    <p class="mt-2 text-sm text-slate-700">{{ $inspectionRequest->landlord_note ?: 'No landlord note yet.' }}</p>
                                </div>

                                @if ($showsOutcome && ($inspectionRequest->outcomeLabel() || $inspectionRequest->hasOutcomeNotes()))
                                    <div class="admin-data-box-success">
                                        <p class="text-sm font-medium text-emerald-900">Current outcome summary</p>
                                        <p class="mt-2 text-sm text-emerald-800">{{ $inspectionRequest->outcomeLabel() ?: 'No outcome selected' }}</p>
                                        @if ($inspectionRequest->hasOutcomeNotes())
                                            <p class="mt-2 text-sm text-emerald-800">{{ $inspectionRequest->outcome_notes }}</p>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </x-admin.panel>

                        <x-admin.partials.status-history-card
                            title="Status history"
                            description="Every change is recorded here."
                            :histories="$inspectionRequest->statusHistories"
                            fallbackChangedBy="System"
                        />
                    </div>
                </div>
        @endif
    </div>
</div>
