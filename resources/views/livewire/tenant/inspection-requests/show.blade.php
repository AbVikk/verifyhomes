@php($showsOutcome = $detailAvailable && $inspectionRequest && $inspectionRequest->showsOutcome())

<div class="admin-page">
    <div class="admin-page-inner">
        @if (session('status'))
            <x-admin.alert>
                {{ session('status') }}
            </x-admin.alert>
        @endif

        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="admin-eyebrow">Inspection requests</p>
                <h2 class="admin-panel-title">Inspection request</h2>
            </div>
            <a href="{{ route('tenant.inspection-requests.index') }}" class="admin-action-link">Back to requests</a>
        </div>

        @if (! $detailAvailable)
            <x-admin.panel>
                <x-admin.empty-state
                    title="Inspection request detail data is not available yet."
                    copy="This detail page will populate automatically after the inspection workflow tables are migrated in this environment."
                />
            </x-admin.panel>
        @else
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(320px,0.9fr)]">
                <div class="space-y-6">
                    <x-admin.panel>
                        <div class="space-y-4">
                            <div>
                                <p class="admin-eyebrow">Property</p>
                                <h3 class="admin-panel-title">Request</h3>
                            </div>

                            <dl class="grid gap-4 text-sm text-slate-700 md:grid-cols-2">
                                <div><dt class="font-medium text-slate-500">Title</dt><dd class="mt-1">{{ $inspectionRequest->property?->title }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Location</dt><dd class="mt-1">{{ $inspectionRequest->property?->area }}, {{ $inspectionRequest->property?->city }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Request status</dt><dd class="mt-1">{{ str($inspectionRequest->status)->headline() }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Schedule</dt><dd class="mt-1">{{ $inspectionRequest->scheduled_at?->format('M j, Y g:i A') ?: 'Waiting for inspection schedule' }}</dd></div>
                            </dl>
                        </div>
                    </x-admin.panel>

                    @if ($inspectionRequest->status === 'requested' && ! $inspectionRequest->scheduled_at)
                        <x-admin.workflow-state
                            tone="waiting"
                            label="Waiting for VerifyHomes"
                            title="Inspection request received"
                            copy="VerifyHomes is reviewing your request."
                        >
                            <p class="mt-3 text-sm font-medium">Next step: wait for a proposed inspection schedule. No action is required right now.</p>
                        </x-admin.workflow-state>
                    @elseif ($inspectionRequest->scheduleIsAcceptedOrLegacy() && ! $hasPaidInspectionFee && ! $showsOutcome)
                        <x-admin.workflow-state
                            tone="action"
                            label="Action required"
                            title="Complete your booking"
                            copy="Your inspection schedule is accepted. Complete the booking fee to confirm the visit."
                        >
                            <a href="#booking-fee" class="admin-button admin-button-primary mt-4 w-full sm:w-auto">Pay Booking Fee</a>
                        </x-admin.workflow-state>
                    @elseif ($hasPaidInspectionFee && ! $showsOutcome)
                        <x-admin.workflow-state
                            tone="success"
                            label="Inspection booked"
                            title="Your visit is confirmed"
                            :copy="'Scheduled for '.($inspectionRequest->scheduled_at?->format('M j, Y g:i A') ?? 'the confirmed time').'.'"
                        >
                            <p class="mt-3 text-sm font-medium">No action is required right now. Please attend the inspection at the scheduled time.</p>
                        </x-admin.workflow-state>
                    @endif

                    @if ($inspectionRequest->scheduleNeedsTenantResponse())
                        <x-admin.workflow-state
                            tone="action"
                            label="Action required"
                            title="Review your inspection schedule"
                            :copy="'VerifyHomes proposed '.$inspectionRequest->scheduled_at?->format('l, M j, Y \\a\\t g:i A').' for '.$inspectionRequest->property?->title.'. Confirm this time before paying the booking fee.'"
                        >
                            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap">

                                <button wire:click="acceptSchedule" wire:loading.attr="disabled" wire:target="acceptSchedule" type="button" class="admin-button admin-button-primary w-full sm:w-auto">
                                    <span wire:loading.remove wire:target="acceptSchedule">Accept schedule</span>
                                    <span wire:loading wire:target="acceptSchedule">Accepting...</span>
                                </button>
                                <button type="button" x-data x-on:click="$dispatch('open-modal', 'request-another-date')" class="admin-button admin-button-secondary w-full sm:w-auto">Request another date</button>
                                <button wire:click="cancelRequest" wire:loading.attr="disabled" wire:target="cancelRequest" type="button" class="admin-button admin-button-quiet w-full sm:w-auto">
                                    <span wire:loading.remove wire:target="cancelRequest">Cancel request</span>
                                    <span wire:loading wire:target="cancelRequest">Cancelling...</span>
                                </button>
                            </div>
                        </x-admin.workflow-state>
                    @endif

                    <x-admin.panel>
                        <div class="space-y-4">
                            <div>
                                <p class="admin-eyebrow">Your request</p>
                                <h3 class="admin-panel-title">Submitted details</h3>
                            </div>

                            <dl class="grid gap-4 text-sm text-slate-700 md:grid-cols-2">
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
                                <p class="admin-eyebrow">Status</p>
                                <h3 class="admin-panel-title">Status</h3>
                            </div>

                            <dl class="grid gap-4 text-sm text-slate-700 md:grid-cols-2">
                                <div>
                                    <dt class="font-medium text-slate-500">Request</dt>
                                    <dd class="mt-1">{{ str($inspectionRequest->status)->headline() }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-slate-500">Payment</dt>
                                    <dd class="mt-1">{{ $this->paymentStatusLabel($latestPaymentTransaction?->status) }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-slate-500">Schedule</dt>
                                    <dd class="mt-1">{{ $inspectionRequest->scheduled_at ? 'Scheduled' : 'Waiting for inspection schedule' }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-slate-500">Next step</dt>
                                    <dd class="mt-1">
                                        @if ($latestPaymentTransaction && $latestPaymentTransaction->status === 'initiated')
                                            Return to the provider checkout and finish the payment step.
                                        @elseif ($latestPaymentTransaction && $latestPaymentTransaction->status === 'pending')
                                            We have your checkout return and we are waiting for final confirmation.
                                        @elseif ($latestPaymentTransaction && $latestPaymentTransaction->status === 'paid')
                                            Booking fee paid. Your inspection is booked; no action is needed right now.
                                        @elseif ($inspectionRequest->status === 'requested' && ! $inspectionRequest->scheduled_at)
                                            VerifyHomes is arranging a suitable inspection date. We'll notify you when a schedule is ready for your review.
                                        @elseif ($inspectionRequest->status === 'scheduled')
                                            Your visit is scheduled.
                                        @elseif ($inspectionRequest->status === 'completed')
                                            Your result is below.
                                        @else
                                            Check this page for updates.
                                        @endif
                                    </dd>
                                </div>
                                @if ($showsOutcome && $inspectionRequest->outcomeLabel())
                                    <div>
                                        <dt class="font-medium text-slate-500">Outcome</dt>
                                        <dd class="mt-1">{{ $inspectionRequest->outcomeLabel() }}</dd>
                                    </div>
                                @endif
                                @if ($showsOutcome && $inspectionRequest->hasOutcomeNotes())
                                    <div>
                                        <dt class="font-medium text-slate-500">Outcome notes</dt>
                                        <dd class="mt-1">{{ $inspectionRequest->outcome_notes }}</dd>
                                    </div>
                                @endif
                                @if (! $showsOutcome)
                                    <div>
                                        <dt class="font-medium text-slate-500">Outcome</dt>
                                        <dd class="mt-1">Available after the visit is completed.</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    </x-admin.panel>

                    @if ($showsOutcome && $inspectionRequest->outcome_type === 'inspected')
                        <x-admin.workflow-state tone="completed" label="Inspection outcome" title="Inspection completed" copy="You've completed your inspection. If you're satisfied with this property, you can continue to secure it.">
                            <div class="space-y-4">
                                @if ($inspectionRequest->property?->available_units <= 0)
                                    <p class="text-sm text-slate-700">This property is no longer available, so payment cannot continue.</p>
                                @elseif ($inspectionRequest->property?->listing_intent === 'for_rent')
                                    @if ($latestRentPaymentTransaction?->status === 'paid')
                                        <p class="font-medium text-slate-900">Property secured</p>
                                        <a href="{{ route('tenant.occupancy.index') }}" class="admin-button admin-button-primary w-full sm:w-auto">View My Stay</a>
                                    @else
                                        <p class="text-sm text-slate-700">Your inspection booking fee was paid separately and is not deducted from the property amount.</p>
                                        <a href="{{ route('properties.show', $inspectionRequest->property) }}" class="admin-button admin-button-primary w-full sm:w-auto">Continue to rent payment</a>
                                    @endif
                                @elseif ($inspectionRequest->property?->listing_intent === 'for_sale')
                                    @if ($latestPurchasePaymentTransaction?->status === 'paid' && $purchaseReceipt)
                                        <p class="font-medium text-slate-900">Purchase confirmed</p>
                                        <a href="{{ route('tenant.purchases.show', $purchaseReceipt) }}" class="admin-button admin-button-primary w-full sm:w-auto">View purchase receipt</a>
                                    @else
                                        <p class="text-sm text-slate-700">Your inspection booking fee was paid separately and is not deducted from the purchase amount.</p>
                                        <a href="{{ route('properties.show', $inspectionRequest->property) }}" class="admin-button admin-button-primary w-full sm:w-auto">Continue to purchase</a>
                                    @endif
                                @else
                                    <p class="text-sm text-slate-700">Your inspection is complete. VerifyHomes will guide the lease coordination from here.</p>
                                    <a href="{{ route('properties.show', $inspectionRequest->property) }}" class="admin-button admin-button-secondary w-full sm:w-auto">View lease next step</a>
                                @endif
                            </div>
                        </x-admin.workflow-state>
                    @endif

                    <x-admin.panel id="booking-fee">
                        <div class="space-y-4">
                            <div>
                                <p class="admin-eyebrow">Payment</p>
                                <h3 class="admin-panel-title">Booking fee</h3>
                                <p class="admin-panel-copy">
                                    {{ $inspectionRequest->scheduleIsAcceptedOrLegacy() || $hasPaidInspectionFee
                                        ? 'Review the booking details for this inspection.'
                                        : 'Booking-fee checkout unlocks after you accept an inspection schedule proposed by VerifyHomes.' }}
                                </p>
                            </div>

                            @if (! $paymentTransactionsAvailable)
                                <x-admin.empty-state
                                    title="Payment transactions are not available yet."
                                    copy="This section will appear when payment data is available."
                                />
                            @else
                                <dl class="space-y-4 text-sm text-slate-700">
                                    <div>
                                        <dt class="font-medium text-slate-500">Inspection booking fee</dt>
                                        <dd class="mt-1">{{ $this->formatMoney($inspectionBookingFeeAmount) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium text-slate-500">Latest transaction status</dt>
                                        <dd class="mt-1">{{ $this->paymentStatusLabel($latestPaymentTransaction?->status) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium text-slate-500">Next step</dt>
                                        <dd class="mt-1">{{ $this->paymentStatusSummary($latestPaymentTransaction?->status) }}</dd>
                                    </div>
                                    @if ($latestPaymentTransaction)
                                        <div>
                                            <dt class="font-medium text-slate-500">Gateway</dt>
                                            <dd class="mt-1">{{ $this->providerLabel($latestPaymentTransaction->provider) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="font-medium text-slate-500">Reference</dt>
                                            <dd class="mt-1 font-mono text-xs text-slate-700">{{ $latestPaymentTransaction->reference }}</dd>
                                        </div>
                                    @endif
                                </dl>

                                <div class="flex flex-wrap gap-3">
                                    @if (! $hasPaidInspectionFee && $inspectionRequest->scheduleIsAcceptedOrLegacy() && ! $tenantIsVerified)
                                        <div class="admin-alert admin-alert-warning"><p class="font-semibold">Verify your identity to continue</p><p class="mt-1">Identity verification is required before booking your inspection.</p><a href="{{ route('tenant.verification') }}" class="admin-inline-link">Verify identity</a></div>
                                    @elseif (! $hasPaidInspectionFee && $inspectionRequest->scheduleIsAcceptedOrLegacy())
                                        <form method="POST" action="{{ route('tenant.inspection-requests.payments.store', $inspectionRequest) }}" class="space-y-4" data-processing-form>
                                            @csrf

                                            <div
                                                class="admin-callout"
                                                data-terms-gate-root
                                                data-terms-gate="{{ $this->inspectionTermsGate() }}"
                                                data-terms-gate-modal="inspection-payment-terms"
                                                data-terms-gate-open-url="{{ route('terms-gates.open') }}"
                                                data-terms-gate-complete-url="{{ route('terms-gates.complete') }}"
                                                data-terms-gate-seconds="{{ config('payments.terms_gate_seconds', 10) }}"
                                                data-terms-gate-seconds-remaining="{{ $this->inspectionTermsSecondsRemaining() }}"
                                                data-terms-gate-ready="{{ $this->inspectionTermsReady() ? 'true' : 'false' }}"
                                                data-terms-gate-accepted="{{ old('accepted_inspection_terms') ? 'true' : 'false' }}"
                                            >
                                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                    <div>
                                                        <p class="font-semibold text-slate-900">Inspection terms</p>
                                                        <p class="mt-1 text-sm text-slate-600">Review and accept the terms inside the modal before starting checkout.</p>
                                                    </div>
                                                    <button type="button" data-terms-gate-open class="admin-button admin-button-primary">
                                                        View terms
                                                    </button>
                                                </div>

                                                <input
                                                    name="accepted_inspection_terms"
                                                    value="1"
                                                    type="checkbox"
                                                    class="sr-only"
                                                    tabindex="-1"
                                                    aria-hidden="true"
                                                    data-terms-gate-hidden-input
                                                    {{ old('accepted_inspection_terms') ? 'checked' : '' }}
                                                />
                                                <p class="admin-help" data-terms-gate-summary>
                                                    @if (old('accepted_inspection_terms'))
                                                        Terms accepted. You can continue with the form.
                                                    @else
                                                        Open the terms to read and accept them in the modal.
                                                    @endif
                                                </p>
                                                @error('accepted_inspection_terms') <p class="admin-error">{{ $message }}</p> @enderror
                                            </div>

                                            <button type="submit" class="admin-button admin-button-primary" data-processing-button data-terms-gate-submit-button>
                                                <span data-button-idle>{{ $latestPaymentTransaction && $latestPaymentTransaction->status === 'failed' ? 'Start new checkout' : 'Pay booking fee' }}</span>
                                                <span data-button-processing class="hidden">Processing...</span>
                                            </button>
                                        </form>
                                    @endif

                                    @if (! $hasPaidInspectionFee && ! $inspectionRequest->scheduleIsAcceptedOrLegacy())
                                        <p class="admin-help">
                                            {{ $inspectionRequest->scheduleNeedsTenantResponse()
                                                ? 'Accept the proposed inspection schedule before booking-fee checkout becomes available.'
                                                : "Waiting for inspection schedule. VerifyHomes is arranging a suitable inspection date. We'll notify you when a schedule is ready for your review." }}
                                        </p>
                                    @endif

                                    <a href="{{ route('tenant.payments.index') }}" class="admin-button admin-button-secondary">Payment history</a>
                                    @if ($this->canContinueCheckout($latestPaymentTransaction))
                                        <a href="{{ data_get($latestPaymentTransaction->metadata, 'checkout_url') }}" target="_blank" rel="noopener noreferrer" class="admin-button admin-button-secondary">Continue checkout</a>
                                    @endif
                                </div>

                                @if ($paymentTransactions->isNotEmpty())
                                    <div class="space-y-3">
                                        @foreach ($paymentTransactions as $paymentTransaction)
                                            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                                <div class="flex items-start justify-between gap-4">
                                                    <div>
                                                        <p class="font-medium text-slate-900">{{ str($paymentTransaction->transaction_type)->headline() }}</p>
                                                        <p class="mt-1 text-sm text-slate-600">{{ $this->formatMoney($paymentTransaction->gross_amount, $paymentTransaction->currency) }}</p>
                                                        <p class="mt-1 text-xs text-slate-500">{{ $this->providerLabel($paymentTransaction->provider) }}</p>
                                                        <p class="mt-1 font-mono text-xs text-slate-500">{{ $paymentTransaction->reference }}</p>
                                                        <p class="mt-2 text-sm text-slate-500">{{ $this->paymentStatusSummary($paymentTransaction->status) }}</p>
                                                    </div>
                                                    <span class="admin-badge admin-badge-neutral">{{ str($paymentTransaction->status)->headline() }}</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            @endif
                        </div>
                    </x-admin.panel>
                </div>
            </div>
        @endif

        <x-modal name="inspection-payment-terms" maxWidth="3xl">
            <div class="admin-modal-panel" data-terms-gate-modal-content="{{ $this->inspectionTermsGate() }}">
                <div class="admin-modal-header">
                    <h3 class="text-lg font-semibold text-slate-950">Inspection terms</h3>
                    <p class="mt-1 text-sm text-slate-600">Read before you pay.</p>
                </div>
                <div class="admin-modal-body">
                    <p>The booking fee is separate from the property price.</p>
                    <p>The fee covers inspection handling and platform coordination for the visit.</p>
                    <p>The fee should be treated as non-refundable once checkout starts.</p>
                    <p>Successful payment moves the request into scheduling and verification follow-through rather than instant visit confirmation.</p>
                    <p>Keep this modal open and read it fully before you accept the checkbox on the form.</p>
                    <div class="admin-callout">
                        <label for="inspection-payment-terms-checkbox" class="flex items-start gap-3 text-sm text-slate-700">
                            <input id="inspection-payment-terms-checkbox" type="checkbox" data-terms-gate-checkbox class="admin-checkbox mt-1" />
                            <span>I have read and accept the inspection terms before paying the booking fee.</span>
                        </label>
                        <p class="admin-help" data-terms-gate-modal-status>
                            Keep this modal open for {{ config('payments.terms_gate_seconds', 10) }} seconds before the checkbox unlocks.
                        </p>
                        <p data-terms-gate-modal-warning class="mt-3 hidden text-sm text-rose-700 opacity-0 transition-opacity duration-300"></p>
                    </div>
                </div>
                <div class="admin-modal-footer">
                    <p class="text-sm text-slate-600">Accept the checkbox here, then close the modal and continue with checkout.</p>
                    <button type="button" x-data x-on:click="$dispatch('close-modal', 'inspection-payment-terms')" class="admin-button admin-button-primary">Close</button>
                </div>
            </div>
        </x-modal>

        <x-modal name="request-another-date" maxWidth="lg">
            <div class="admin-modal-panel">
                <div class="admin-modal-header">
                    <h3 class="text-lg font-semibold text-slate-950">Request another inspection date</h3>
                    <p class="mt-1 text-sm text-slate-600">Tell VerifyHomes what would work better. Your booking fee is not charged by this request.</p>
                </div>
                <div class="admin-modal-body space-y-4">
                    <div>
                        <label for="scheduleResponseNotes" class="admin-label">Preferred alternative</label>
                        <textarea wire:model.defer="scheduleResponseNotes" id="scheduleResponseNotes" rows="4" class="admin-control admin-control-textarea" placeholder="Example: Saturday morning or after 4pm on Tuesday."></textarea>
                        @error('scheduleResponseNotes') <p class="admin-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="admin-modal-footer flex flex-wrap gap-3">
                    <button wire:click="requestAnotherDate" wire:loading.attr="disabled" wire:target="requestAnotherDate" type="button" class="admin-button admin-button-primary">
                        <span wire:loading.remove wire:target="requestAnotherDate">Send request</span>
                        <span wire:loading wire:target="requestAnotherDate">Sending...</span>
                    </button>
                    <button type="button" x-data x-on:click="$dispatch('close-modal', 'request-another-date')" class="admin-button admin-button-secondary">Cancel</button>
                </div>
            </div>
        </x-modal>
    </div>
</div>
