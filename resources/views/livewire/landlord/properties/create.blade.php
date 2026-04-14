<div class="admin-page">
    <div class="admin-page-inner">
        @if (session('status'))
            <div class="admin-flash-success">
                {{ session('status') }}
            </div>
        @endif

        <x-admin.panel>
            @if (! $canCreateProperties)
                <div class="space-y-6">
                    <div>
                        <p class="admin-eyebrow">Property submission</p>
                        <h2 class="admin-panel-title">Create property</h2>
                        <p class="admin-panel-copy">Property creation is locked until your landlord verification is approved.</p>
                    </div>

                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        {{ $propertyCreationBlockMessage }}
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('landlord.documents') }}" class="admin-button admin-button-primary">
                            Manage Verification Documents
                        </a>
                        <a href="{{ route('landlord.profile') }}" class="admin-button admin-button-secondary">
                            Review Profile
                        </a>
                        <a href="{{ route('landlord.properties') }}" class="admin-action-link">
                            Back to properties
                        </a>
                    </div>
                </div>
            @else
                <form wire:submit="save" class="space-y-8">
                    <div>
                        <p class="admin-eyebrow">Property submission</p>
                        <h2 class="admin-panel-title">Create property</h2>
                        <p class="admin-panel-copy">Submit your property details for VerifyHomes. New properties save in pending review status and stay unpublished for now.</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="admin-subsurface p-5">
                            <p class="admin-eyebrow">Listing purpose</p>
                            <p class="mt-2 text-base font-semibold text-slate-900">{{ $this->listingIntentLabel() }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ $this->listingIntentSummary() }}</p>
                        </div>

                        <div class="admin-subsurface p-5">
                            <p class="admin-eyebrow">Main price label</p>
                            <p class="mt-2 text-base font-semibold text-slate-900">{{ $primaryAmountLabel }}</p>
                            <p class="mt-2 text-sm text-slate-600">The main amount field stays tied to your selected listing purpose.</p>
                        </div>

                        <div class="admin-subsurface p-5">
                            <p class="admin-eyebrow">What happens next</p>
                            <p class="mt-2 text-sm text-slate-600">After save, the listing returns to your landlord queue as pending review, unpublished, and ready for later admin review.</p>
                        </div>

                        <div class="admin-subsurface p-5 md:col-span-3">
                            <p class="admin-eyebrow">Unit inventory foundation</p>
                            <p class="mt-2 text-sm text-slate-600">Set the starting unit count now for listings like self contain, shops, and offices. Units only reduce after successful completed rent payments, not inspection requests, saves, favorites, or early checkout steps.</p>
                        </div>

                        <div
                            class="admin-subsurface p-5 md:col-span-3"
                            data-terms-gate-root
                            data-terms-gate="listing-terms:create"
                            data-terms-gate-modal="listing-terms-create"
                            data-terms-gate-open-url="{{ route('terms-gates.open') }}"
                            data-terms-gate-complete-url="{{ route('terms-gates.complete') }}"
                            data-terms-gate-seconds="{{ config('payments.terms_gate_seconds', 10) }}"
                            data-terms-gate-seconds-remaining="{{ $this->listingTermsSecondsRemaining() }}"
                            data-terms-gate-ready="{{ $this->listingTermsReady() ? 'true' : 'false' }}"
                            data-terms-gate-accepted="{{ $hasAcceptedListingTerms ? 'true' : 'false' }}"
                        >
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <p class="admin-eyebrow">Listing terms</p>
                                    <p class="mt-2 text-sm text-slate-600">Open the modal, review the terms there, and accept them inside the modal before saving this property.</p>
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

                    @include('livewire.landlord.properties.form-fields')

                    <div class="flex flex-col gap-4 border-t border-slate-200 pt-5 md:flex-row md:items-center md:justify-between">
                        <a href="{{ route('landlord.properties') }}" class="admin-action-link">Back to properties</a>

                        <button type="submit" wire:loading.attr="disabled" wire:target="save,images,documents" @disabled(! $hasAcceptedListingTerms) class="admin-button {{ $hasAcceptedListingTerms ? 'admin-button-primary' : 'admin-button-blocked' }}" data-terms-gate-submit-button>
                            <span wire:loading.remove wire:target="save,images,documents">Save Property</span>
                            <span wire:loading wire:target="save,images,documents">Saving...</span>
                        </button>
                    </div>
                </form>
            @endif
        </x-admin.panel>

        <x-modal name="listing-terms-create" maxWidth="2xl">
            <div class="admin-modal-panel" data-terms-gate-modal-content="listing-terms:create">
                <div class="admin-modal-header">
                    <h3 class="text-lg font-semibold text-slate-950">Listing terms</h3>
                    <p class="mt-1 text-sm text-slate-600">Use these terms before saving a property to the landlord workflow.</p>
                </div>
                <div class="admin-modal-body">
                    <p>Listing on VerifyHomes is free. No upfront listing payment is required to create or maintain a property record.</p>
                    <p>For rent listings, VerifyHomes keeps 20% of a successful completed rent payment as the platform fee. That fee is stored on the payment transaction record, not hidden in loose notes.</p>
                    <p>If you choose the tenant-facing listed-rent model, the amount you enter is exactly what the tenant sees and the 20% fee is deducted from that successful completed rent payment.</p>
                    <p>If you choose the landlord-target model, the amount you enter is the amount you want to net after the 20% fee, and the public listed rent is grossed up to include that fee.</p>
                    <p>The rent, sale, or lease amount should stay honest and accurate. Caution fee and service charge are separate property charges and should not be used to disguise the main listing price.</p>
                    <p>Property details, occupancy, images, and uploaded documents must match the real property so review, tenant trust, and later payment handling stay consistent.</p>
                    <p>Bank details for future payout handling should stay current in your landlord profile, even though automated payout is not part of this pass.</p>
                    <div class="admin-callout">
                        <label class="flex items-start gap-3 text-sm text-slate-700">
                            <input type="checkbox" data-terms-gate-checkbox class="admin-checkbox mt-1" />
                            <span>I have read and accept the listing terms for this property submission.</span>
                        </label>
                        <p class="admin-help" data-terms-gate-modal-status>
                            Keep this modal open for {{ config('payments.terms_gate_seconds', 10) }} seconds before the checkbox unlocks.
                        </p>
                        <p data-terms-gate-modal-warning class="mt-3 hidden text-sm text-rose-700 opacity-0 transition-opacity duration-300"></p>
                    </div>
                </div>
                <div class="admin-modal-footer">
                    <p class="text-sm text-slate-600">Accept the checkbox here, then close the modal and continue with the property form.</p>
                    <button type="button" x-data x-on:click="$dispatch('close-modal', 'listing-terms-create')" class="admin-button admin-button-primary">Close</button>
                </div>
            </div>
        </x-modal>
    </div>
</div>
