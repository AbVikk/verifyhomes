<div class="admin-page">
    <div class="admin-page-inner">
        @if (session('status'))
            <div class="admin-flash-success">
                {{ session('status') }}
            </div>
        @endif

        <div class="space-y-6">
            <x-admin.panel>
                <div class="space-y-5">
                    <div>
                        <p class="admin-eyebrow">Property submission</p>
                        <h2 class="admin-panel-title">Edit property</h2>
                        <p class="admin-panel-copy">Use this page as the main landlord detail and readiness surface for the listing. Update the form only after checking the current state below.</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="admin-subsurface p-5">
                            <p class="admin-eyebrow">Current state</p>
                            <p class="mt-2 text-base font-semibold text-slate-900">{{ str($property->status)->headline() }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ $this->listingIntentLabel($property->listing_intent) }} listing using {{ $primaryAmountLabel }} wording.</p>
                        </div>

                        <div class="admin-subsurface p-5">
                            <p class="admin-eyebrow">Visibility</p>
                            <p class="mt-2 text-base font-semibold text-slate-900">{{ $this->propertyVisibilitySummary($property) }}</p>
                            <p class="mt-2 text-sm text-slate-600">Public visibility depends on review status, verification, and publication state.</p>
                        </div>

                        <div class="admin-subsurface p-5">
                            <p class="admin-eyebrow">Request activity</p>
                            <p class="mt-2 text-base font-semibold text-slate-900">{{ $property->open_inspection_requests_count }} open request{{ $property->open_inspection_requests_count === 1 ? '' : 's' }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ $property->scheduled_inspection_requests_count }} scheduled visit{{ $property->scheduled_inspection_requests_count === 1 ? '' : 's' }} currently tied to this listing.</p>
                        </div>

                        <div class="admin-subsurface p-5">
                            <p class="admin-eyebrow">Readiness gaps</p>
                            <p class="mt-2 text-base font-semibold text-slate-900">{{ count($this->readinessGaps($property)) }}</p>
                            <p class="mt-2 text-sm text-slate-600">Items that still need attention before the listing feels fully ready.</p>
                        </div>

                        <div class="admin-subsurface p-5 md:col-span-2 xl:col-span-4">
                            <p class="admin-eyebrow">Unit inventory</p>
                            <p class="mt-2 text-base font-semibold text-slate-900">{{ $this->unitInventorySummary($property) }}</p>
                            <p class="mt-2 text-sm text-slate-600">Inventory only reduces after successful completed rent payments. Inspection requests, saves/favorites, and early payment initiation do not change occupancy.</p>
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
                        <div class="rounded-2xl border border-slate-200 bg-white p-5">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Next step</p>
                            <p class="mt-2 text-sm text-slate-700">{{ $this->propertyNextStepSummary($property) }}</p>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white p-5">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Current uploads</p>
                            <p class="mt-2 text-sm text-slate-700">{{ $property->images->count() }} image(s) and {{ $property->documents->count() }} private document(s).</p>
                        </div>

                        <div
                            class="rounded-2xl border border-slate-200 bg-white p-5 lg:col-span-2"
                            data-terms-gate-root
                            data-terms-gate="{{ $this->listingTermsGate($property) }}"
                            data-terms-gate-modal="listing-terms-edit"
                            data-terms-gate-open-url="{{ route('terms-gates.open') }}"
                            data-terms-gate-complete-url="{{ route('terms-gates.complete') }}"
                            data-terms-gate-seconds="{{ config('payments.terms_gate_seconds', 10) }}"
                            data-terms-gate-seconds-remaining="{{ $this->listingTermsSecondsRemaining($property) }}"
                            data-terms-gate-ready="{{ $this->listingTermsReady($property) ? 'true' : 'false' }}"
                            data-terms-gate-accepted="{{ $hasAcceptedListingTerms ? 'true' : 'false' }}"
                        >
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Listing terms</p>
                                    <p class="mt-2 text-sm text-slate-700">Open the modal, review the terms there, and accept them inside the modal before saving changes.</p>
                                </div>
                                <button type="button" data-terms-gate-open class="admin-button admin-button-primary">
                                    View Listing Terms
                                </button>
                            </div>

                            <input
                                wire:model.live="hasAcceptedListingTerms"
                                value="1"
                                type="checkbox"
                                class="sr-only"
                                tabindex="-1"
                                aria-hidden="true"
                                data-terms-gate-hidden-input
                                @checked($hasAcceptedListingTerms)
                            />
                            <p class="admin-help" data-terms-gate-summary>
                                @if ($hasAcceptedListingTerms)
                                    Terms accepted. You can continue with the form.
                                @else
                                    Open the terms to read and accept them in the modal.
                                @endif
                            </p>
                            @error('hasAcceptedListingTerms') <p class="admin-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    @if ($this->readinessGaps($property) !== [])
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                            <p class="text-sm font-semibold text-amber-900">Readiness summary</p>
                            <div class="mt-3 space-y-2 text-sm text-amber-900">
                                @foreach ($this->readinessGaps($property) as $gap)
                                    <p>{{ $gap }}</p>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('landlord.properties') }}" class="admin-button admin-button-secondary">Back to Properties</a>
                        @if ($property->open_inspection_requests_count > 0)
                            <a href="{{ route('landlord.inspection-requests.index') }}" class="admin-button admin-button-secondary">View Inspection Requests</a>
                        @endif
                        @if ($property->isPubliclyVisible())
                            <a href="{{ route('properties.show', $property) }}" class="admin-button admin-button-secondary">View Public Listing</a>
                        @endif
                    </div>
                </div>
            </x-admin.panel>

            <x-admin.panel>
                <form wire:submit="save" class="space-y-8">
                    <div>
                        <p class="admin-eyebrow">Property form</p>
                        <h3 class="admin-panel-title">Update listing details</h3>
                        <p class="admin-panel-copy">Changes keep the property in pending review so the later admin workflow can inspect the latest version.</p>
                    </div>

                    @include('livewire.landlord.properties.form-fields')

                    <div class="flex flex-col gap-4 border-t border-slate-200 pt-5 md:flex-row md:items-center md:justify-between">
                        <a href="{{ route('landlord.properties') }}" class="admin-action-link">Back to properties</a>

                        <button type="submit" wire:loading.attr="disabled" wire:target="save,images,documents" @disabled(! $hasAcceptedListingTerms) class="admin-button {{ $hasAcceptedListingTerms ? 'admin-button-primary' : 'admin-button-blocked' }}" data-terms-gate-submit-button>
                            <span wire:loading.remove wire:target="save,images,documents">Update Property</span>
                            <span wire:loading wire:target="save,images,documents">Updating...</span>
                        </button>
                    </div>
                </form>
            </x-admin.panel>
        </div>

        <x-modal name="listing-terms-edit" maxWidth="2xl">
            <div class="admin-modal-panel" data-terms-gate-modal-content="{{ $this->listingTermsGate($property) }}">
                <div class="admin-modal-header">
                    <h3 class="text-lg font-semibold text-slate-950">Listing terms</h3>
                    <p class="mt-1 text-sm text-slate-600">Use these terms before saving changes to a property record.</p>
                </div>
                <div class="admin-modal-body">
                    <p>Listing on VerifyHomes is free. No upfront listing payment is required for editing or maintaining a property record.</p>
                    <p>For rent listings, VerifyHomes keeps 20% of a successful completed rent payment as the platform fee, and that percentage is stored on the listing and transaction records so the result stays auditable.</p>
                    <p>If the listing uses the tenant-facing listed-rent model, the rent shown to tenants is the final rent and the 20% fee is deducted from that successful completed payment.</p>
                    <p>If the listing uses the landlord-target model, the public listed rent is grossed up so the landlord target amount remains after the 20% fee.</p>
                    <p>Property details, occupancy, uploaded images, and uploaded documents must stay honest, accurate, and aligned with the real property.</p>
                    <p>Bank details for future payout handling should stay current in your landlord profile, even though automated payout is not part of this pass.</p>
                    <div class="admin-callout">
                        <label class="flex items-start gap-3 text-sm text-slate-700">
                            <input type="checkbox" data-terms-gate-checkbox class="admin-checkbox mt-1" />
                            <span>I have read and accept the listing terms for this property update.</span>
                        </label>
                        <p class="admin-help" data-terms-gate-modal-status>
                            Keep this modal open for {{ config('payments.terms_gate_seconds', 10) }} seconds before the checkbox unlocks.
                        </p>
                        <p data-terms-gate-modal-warning class="mt-3 hidden text-sm text-rose-700 opacity-0 transition-opacity duration-300"></p>
                    </div>
                </div>
                <div class="admin-modal-footer">
                    <p class="text-sm text-slate-600">Accept the checkbox here, then close the modal and continue with the property form.</p>
                    <button type="button" x-data x-on:click="$dispatch('close-modal', 'listing-terms-edit')" class="admin-button admin-button-primary">Close</button>
                </div>
            </div>
        </x-modal>
    </div>
</div>
