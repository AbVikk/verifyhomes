<div class="admin-page">
    <div class="admin-page-inner">
        @if (session('status'))
            <div class="admin-flash-success">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->has('property'))
            <div class="admin-alert admin-alert-error">
                {{ $errors->first('property') }}
            </div>
        @endif

        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="admin-eyebrow">Browse properties</p>
                <h2 class="admin-panel-title">{{ $property->title }}</h2>
                <p class="admin-panel-copy">{{ $property->listingIntentLabel() }} {{ str($property->property_type)->headline() }} in {{ $property->area }}, {{ $property->city }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                @if ($savedPropertiesAvailable)
                    <button type="button" wire:click="toggleSavedProperty" wire:loading.attr="disabled" wire:target="toggleSavedProperty" class="admin-button admin-button-secondary">
                        <span wire:loading.remove wire:target="toggleSavedProperty">{{ $isSavedByCurrentTenant ? 'Remove from saved' : 'Save listing' }}</span>
                        <span wire:loading wire:target="toggleSavedProperty">Processing...</span>
                    </button>
                @endif
                @if ($hasOpenInspectionRequest && $latestInspectionRequest)
                    <a href="{{ route('tenant.inspection-requests.show', ['inspectionRequestId' => $latestInspectionRequest->getKey()]) }}" class="admin-button admin-button-secondary">
                        View request
                    </a>
                @endif
                <a href="{{ route('properties.index') }}" class="admin-action-link">Back to Properties</a>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-3">
            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">{{ $property->primaryPriceLabel() }}</p>
                    <p class="text-2xl font-semibold text-slate-950">{{ $property->formattedPrimaryPrice() }}</p>
                    <p class="text-sm text-slate-600">Main listing price.</p>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Availability</p>
                    <p class="text-lg font-semibold text-slate-950">{{ $this->availabilityMessage() }}</p>
                    <p class="text-sm text-slate-600">{{ $this->availabilityDetail() }}</p>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Saved status</p>
                    <p class="text-lg font-semibold text-slate-950">{{ $isSavedByCurrentTenant ? 'Saved to your shortlist' : 'Not saved yet' }}</p>
                    <p class="text-sm text-slate-600">Save this listing so it is easy to find later.</p>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Next step</p>
                    <p class="text-sm font-medium text-slate-900">{{ $this->inspectionActionCopy() }}</p>
                    @if ($latestInspectionPaymentTransaction)
                        <p class="text-sm text-slate-600">Inspection payment: {{ str($latestInspectionPaymentTransaction->status)->headline() }} via {{ $this->providerLabel($latestInspectionPaymentTransaction->provider) }}.</p>
                    @else
                        <p class="text-sm text-slate-600">{{ $this->inspectionPaymentStatusCopy() }}</p>
                    @endif
                </div>
            </x-admin.panel>

            @if ($property->listing_intent === 'for_rent')
                <x-admin.panel class="h-full">
                    <div class="space-y-2">
                        <p class="admin-eyebrow">Rent payment</p>
                        <p class="text-sm font-medium text-slate-900">{{ $this->rentPaymentActionCopy() }}</p>
                        @if ($latestRentPaymentTransaction)
                            <p class="text-sm text-slate-600">Rent payment: {{ str($latestRentPaymentTransaction->status)->headline() }} via {{ $this->providerLabel($latestRentPaymentTransaction->provider) }}.</p>
                        @else
                            <p class="text-sm text-slate-600">{{ $this->rentPaymentStatusCopy() }}</p>
                        @endif
                        @if ($latestInspectionRequest)
                            <p class="text-sm text-slate-600">Inspection status: {{ str($latestInspectionRequest->status)->headline() }}@if($latestInspectionRequest->outcomeLabel()) - {{ $latestInspectionRequest->outcomeLabel() }}@endif.</p>
                        @endif
                    </div>
                </x-admin.panel>
            @elseif ($property->listing_intent === 'for_sale')
                <x-admin.panel class="h-full">
                    <div class="space-y-2">
                        <p class="admin-eyebrow">Purchase payment</p>
                        <p class="text-sm font-medium text-slate-900">{{ $this->purchasePaymentActionCopy() }}</p>
                        <p class="text-sm text-slate-600">{{ $this->purchasePaymentStatusCopy() }}</p>
                        @if ($latestInspectionRequest)
                            <p class="text-sm text-slate-600">Inspection status: {{ str($latestInspectionRequest->status)->headline() }}@if($latestInspectionRequest->outcomeLabel()) - {{ $latestInspectionRequest->outcomeLabel() }}@endif.</p>
                        @endif
                    </div>
                </x-admin.panel>
            @else
                <x-admin.panel class="h-full">
                    <div class="space-y-2">
                        <p class="admin-eyebrow">Lease coordination</p>
                        <p class="text-sm font-medium text-slate-900">{{ $this->leaseActionCopy() }}</p>
                        <p class="text-sm text-slate-600">{{ $this->leaseStatusCopy() }}</p>
                        @if ($latestInspectionRequest)
                            <p class="text-sm text-slate-600">Inspection status: {{ str($latestInspectionRequest->status)->headline() }}@if($latestInspectionRequest->outcomeLabel()) - {{ $latestInspectionRequest->outcomeLabel() }}@endif.</p>
                        @endif
                    </div>
                </x-admin.panel>
            @endif
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
            <div class="space-y-6">
                <x-admin.panel>
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @forelse ($property->images as $image)
                            <div class="overflow-hidden border border-slate-200 bg-slate-100">
                                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->image_path) }}" target="_blank" rel="noopener noreferrer" class="block">
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->image_path) }}" alt="{{ $property->title }} image" class="h-64 w-full object-cover transition hover:opacity-95">
                                </a>
                            </div>
                        @empty
                            <div class="md:col-span-2 xl:col-span-3">
                                <x-admin.empty-state
                                    title="Photos will appear here."
                                    copy="Photos for this listing will show here."
                                />
                            </div>
                        @endforelse
                    </div>
                </x-admin.panel>

                <x-admin.panel>
                    <div class="space-y-5">
                        <div>
                            <p class="admin-eyebrow">Property details</p>
                            <h3 class="admin-panel-title">Verified listing summary</h3>
                        </div>

                        <div class="grid gap-4 text-sm text-slate-700 md:grid-cols-2 xl:grid-cols-3">
                            <div><p class="font-medium text-slate-500">Listing intent</p><p class="mt-1">{{ $property->listingIntentLabel() }}</p></div>
                            <div><p class="font-medium text-slate-500">{{ $property->primaryPriceLabel() }}</p><p class="mt-1">{{ $property->formattedPrimaryPrice() }}</p></div>
                            @if ($property->property_type === 'land')
                                <div><p class="font-medium text-slate-500">Land size</p><p class="mt-1">{{ $property->landSizeLabel() ?? 'Not listed' }}</p></div>
                            @else
                                <div><p class="font-medium text-slate-500">Bedrooms</p><p class="mt-1">{{ $property->bedrooms ?? 'Not listed' }}</p></div>
                                <div><p class="font-medium text-slate-500">Bathrooms</p><p class="mt-1">{{ $property->bathrooms ?? 'Not listed' }}</p></div>
                                <div><p class="font-medium text-slate-500">Toilets</p><p class="mt-1">{{ $property->toilets ?? 'Not listed' }}</p></div>
                            @endif
                            <div><p class="font-medium text-slate-500">City</p><p class="mt-1">{{ $property->city }}</p></div>
                            <div><p class="font-medium text-slate-500">Area</p><p class="mt-1">{{ $property->area }}</p></div>
                            <div><p class="font-medium text-slate-500">LGA</p><p class="mt-1">{{ $property->lga }}</p></div>
                            <div><p class="font-medium text-slate-500">Landmark</p><p class="mt-1">{{ $property->landmark ?: 'Not listed' }}</p></div>
                            <div><p class="font-medium text-slate-500">Property type</p><p class="mt-1">{{ str($property->property_type)->headline() }}</p></div>
                            <div><p class="font-medium text-slate-500">Availability</p><p class="mt-1">{{ $this->availabilityDetail() }}</p></div>
                        </div>

                        <div>
                            <h4 class="text-sm font-medium text-slate-500">Description</h4>
                            <p class="mt-2 text-sm leading-6 text-slate-700">{{ $property->description ?: 'No additional description provided yet.' }}</p>
                        </div>

                        @if ($property->youtube_url)
                            <div>
                                <h4 class="text-sm font-medium text-slate-500">Video tour</h4>
                                <a href="{{ $property->youtube_url }}" target="_blank" rel="noopener noreferrer" class="admin-inline-link">
                                    Watch on YouTube
                                </a>
                            </div>
                        @endif
                    </div>
                </x-admin.panel>
            </div>

            <div class="space-y-6">
                <x-admin.panel>
                    <div class="space-y-4">
                        <div>
                            <p class="admin-eyebrow">
                                {{ $property->listing_intent === 'for_rent' ? 'Rent payment' : ($property->listing_intent === 'for_sale' ? 'Purchase payment' : 'Lease coordination') }}
                            </p>
                            <h3 class="admin-panel-title">
                                {{ $property->listing_intent === 'for_rent'
                                    ? 'Pay rent for this listing'
                                    : ($property->listing_intent === 'for_sale' ? 'Purchase payment for this listing' : 'Lease coordination for this listing') }}
                            </h3>
                            <p class="admin-panel-copy">
                                {{ $property->listing_intent === 'for_rent'
                                    ? 'This is separate from inspection booking. Use this flow only when you are ready to pay the listed rent.'
                                    : ($property->listing_intent === 'for_sale'
                                        ? 'This listing is for sale. VerifyHomes will guide purchase payment after inspection completion.'
                                        : 'This listing is for lease. VerifyHomes will guide the lease coordination after inspection completion.') }}
                            </p>
                        </div>

                        @if ($property->listing_intent === 'for_rent')
                            <dl class="space-y-4 text-sm text-slate-700">
                                <div>
                                    <dt class="font-medium text-slate-500">Listed rent</dt>
                                    <dd class="mt-1">{{ $property->formattedPrimaryPrice() }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-slate-500">Rent payment unlock rule</dt>
                                    <dd class="mt-1">Rent payment becomes available only after your inspection request is completed with an outcome that allows rent progression.</dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-slate-500">Availability impact</dt>
                                    <dd class="mt-1">A verified paid rent transaction reduces listing availability by 1 unit. Incomplete or failed checkout does not change occupancy.</dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-slate-500">Current status</dt>
                                    <dd class="mt-1">{{ $this->rentPaymentStatusCopy() }}</dd>
                                </div>
                                @if ($latestRentPaymentTransaction)
                                    <div>
                                        <dt class="font-medium text-slate-500">Latest rent reference</dt>
                                        <dd class="mt-1 font-mono text-xs text-slate-700">{{ $latestRentPaymentTransaction->reference }}</dd>
                                    </div>
                                @endif
                                @if ($latestInspectionRequest)
                                    <div>
                                        <dt class="font-medium text-slate-500">Inspection progression</dt>
                                        <dd class="mt-1">
                                            {{ str($latestInspectionRequest->status)->headline() }}
                                            @if ($latestInspectionRequest->outcomeLabel())
                                                - {{ $latestInspectionRequest->outcomeLabel() }}
                                            @endif
                                        </dd>
                                    </div>
                                @endif
                            </dl>

                            <div class="flex flex-wrap gap-3">
                                @if ($this->canStartRentPayment())
                                    <form method="POST" action="{{ route('tenant.properties.rent-payments.store', $property) }}" data-processing-form>
                                        @csrf
                                        <button type="submit" class="admin-button admin-button-primary" data-processing-button>
                                            <span data-button-idle>Pay rent</span>
                                            <span data-button-processing class="hidden">Starting checkout...</span>
                                        </button>
                                    </form>
                                @endif

                                @if ($this->canContinueCheckout($latestRentPaymentTransaction))
                                    <a href="{{ data_get($latestRentPaymentTransaction, 'metadata.checkout_url') }}" target="_blank" rel="noopener noreferrer" class="admin-button admin-button-secondary">Continue checkout</a>
                                @endif

                                @if ($latestRentPaymentTransaction?->status === 'paid')
                                    <span class="admin-button admin-button-secondary">Rent paid</span>
                                @endif

                                @if ($latestRentPaymentTransaction)
                                    <a href="{{ route('tenant.payments.index', ['reference' => $latestRentPaymentTransaction->reference]) }}" class="admin-button admin-button-secondary">View rent payment</a>
                                @endif
                            </div>
                        @elseif ($property->listing_intent === 'for_sale')
                            <dl class="space-y-4 text-sm text-slate-700">
                                <div>
                                    <dt class="font-medium text-slate-500">Listing price</dt>
                                    <dd class="mt-1">{{ $property->formattedPrimaryPrice() }}</dd>
                                </div>
                                @if ($property->property_type === 'land' && $property->available_units > 1)
                                    <div>
                                        <dt class="font-medium text-slate-500">Available land units</dt>
                                        <dd class="mt-1">{{ $property->available_units }} unit{{ $property->available_units === 1 ? '' : 's' }} available.</dd>
                                    </div>
                                @endif
                                <div>
                                    <dt class="font-medium text-slate-500">Purchase flow status</dt>
                                    <dd class="mt-1">{{ $this->purchasePaymentStatusCopy() }}</dd>
                                </div>
                                @if ($latestPurchasePaymentTransaction)
                                    <div>
                                        <dt class="font-medium text-slate-500">Purchase payment status</dt>
                                        <dd class="mt-1">{{ str($latestPurchasePaymentTransaction->status)->headline() }} via {{ $this->providerLabel($latestPurchasePaymentTransaction->provider) }}.</dd>
                                    </div>
                                @endif
                                @if ($latestInspectionRequest)
                                    <div>
                                        <dt class="font-medium text-slate-500">Inspection progression</dt>
                                        <dd class="mt-1">
                                            {{ str($latestInspectionRequest->status)->headline() }}
                                            @if ($latestInspectionRequest->outcomeLabel())
                                                - {{ $latestInspectionRequest->outcomeLabel() }}
                                            @endif
                                        </dd>
                                    </div>
                                @endif
                            </dl>

                            <div class="flex flex-wrap gap-3">
                                @if ($this->canStartPurchasePayment())
                                    <form method="POST" action="{{ route('tenant.properties.purchase-payments.store', $property) }}" data-processing-form class="space-y-3">
                                        @csrf
                                        @if ($property->property_type === 'land' && $property->available_units > 1)
                                            <div>
                                                <label class="admin-label" for="purchase_units">Purchase quantity</label>
                                                <select id="purchase_units" name="purchase_units" class="admin-control admin-control-select">
                                                    @for ($unit = 1; $unit <= $property->available_units; $unit++)
                                                        <option value="{{ $unit }}" @selected((int) old('purchase_units', 1) === $unit)>
                                                            {{ $unit }} unit{{ $unit === 1 ? '' : 's' }}
                                                        </option>
                                                    @endfor
                                                </select>
                                                <p class="admin-help">Total charge will be the listing price multiplied by the selected land units.</p>
                                                @error('purchase_units') <p class="admin-error">{{ $message }}</p> @enderror
                                            </div>
                                        @endif
                                        <button type="submit" class="admin-button admin-button-primary" data-processing-button>
                                            <span data-button-idle>Pay purchase price</span>
                                            <span data-button-processing class="hidden">Starting checkout...</span>
                                        </button>
                                    </form>
                                @endif

                                @if ($this->canContinueCheckout($latestPurchasePaymentTransaction))
                                    <a href="{{ data_get($latestPurchasePaymentTransaction, 'metadata.checkout_url') }}" target="_blank" rel="noopener noreferrer" class="admin-button admin-button-secondary">Continue checkout</a>
                                @endif

                                @if ($latestPurchasePaymentTransaction?->status === 'paid')
                                    <span class="admin-button admin-button-secondary">Purchase confirmed</span>
                                @endif

                                @if ($latestPurchasePaymentTransaction)
                                    <a href="{{ route('tenant.payments.index', ['reference' => $latestPurchasePaymentTransaction->reference]) }}" class="admin-button admin-button-secondary">View purchase payment</a>
                                @endif
                            </div>
                        @else
                            <dl class="space-y-4 text-sm text-slate-700">
                                <div>
                                    <dt class="font-medium text-slate-500">Lease amount</dt>
                                    <dd class="mt-1">{{ $property->formattedPrimaryPrice() }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-slate-500">Lease status</dt>
                                    <dd class="mt-1">{{ $this->leaseStatusCopy() }}</dd>
                                </div>
                                @if ($latestInspectionRequest)
                                    <div>
                                        <dt class="font-medium text-slate-500">Inspection progression</dt>
                                        <dd class="mt-1">
                                            {{ str($latestInspectionRequest->status)->headline() }}
                                            @if ($latestInspectionRequest->outcomeLabel())
                                                - {{ $latestInspectionRequest->outcomeLabel() }}
                                            @endif
                                        </dd>
                                    </div>
                                @endif
                            </dl>
                        @endif
                    </div>
                </x-admin.panel>

                <x-admin.panel>
                    <div class="space-y-4">
                        <div>
                            <p class="admin-eyebrow">Inspection request</p>
                            <h3 class="admin-panel-title">Request an inspection</h3>
                            <p class="admin-panel-copy">Send your request here. VerifyHomes will handle scheduling.</p>
                        </div>

                        @if (! $inspectionRequestsAvailable)
                            <x-admin.empty-state
                                title="Inspection requests are not available yet."
                                copy="This section will appear when inspection requests are available."
                            />
                        @elseif ($hasOpenInspectionRequest && $latestInspectionRequest)
                            <div class="space-y-3">
                                <div class="admin-alert admin-alert-warning">
                                    You already have an active request for this property.
                                </div>
                                <a href="{{ route('tenant.inspection-requests.show', ['inspectionRequestId' => $latestInspectionRequest->getKey()]) }}" class="admin-button admin-button-primary w-full text-center">
                                    View request
                                </a>
                            </div>
                        @else
                            <form id="inspection-request" method="POST" action="{{ route('inspection-requests.store', $property) }}" class="space-y-4" data-processing-form>
                                @csrf

                                <div
                                    class="admin-callout"
                                    data-terms-gate-root
                                    data-terms-gate="{{ $this->inspectionTermsGate() }}"
                                    data-terms-gate-modal="inspection-terms-tenant"
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
                                            <p class="mt-1 text-sm text-slate-600">Review and accept the terms inside the modal before sending this request.</p>
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

                                <div>
                                    <label for="preferred_date" class="admin-label">Preferred date</label>
                                    <input id="preferred_date" name="preferred_date" type="date" value="{{ old('preferred_date') }}" class="admin-control" />
                                    @error('preferred_date') <p class="admin-error">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="preferred_time_note" class="admin-label">Preferred time note</label>
                                    <input id="preferred_time_note" name="preferred_time_note" type="text" value="{{ old('preferred_time_note') }}" placeholder="Example: After 4pm on weekdays" class="admin-control" />
                                    @error('preferred_time_note') <p class="admin-error">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="message" class="admin-label">Message</label>
                                    <textarea id="message" name="message" rows="4" class="admin-control admin-control-textarea" placeholder="Share anything helpful about your preferred visit window.">{{ old('message') }}</textarea>
                                    @error('message') <p class="admin-error">{{ $message }}</p> @enderror
                                </div>

                                <button type="submit" class="admin-button admin-button-primary w-full" data-processing-button data-terms-gate-submit-button>
                                    <span data-button-idle>Send request</span>
                                    <span data-button-processing class="hidden">Submitting...</span>
                                </button>
                            </form>
                        @endif
                    </div>
                </x-admin.panel>

                <x-admin.panel>
                    <div class="space-y-4">
                        <div>
                            <p class="admin-eyebrow">Your status</p>
                            <h3 class="admin-panel-title">Your status</h3>
                        </div>

                        <dl class="space-y-4 text-sm text-slate-700">
                            <div>
                                <dt class="font-medium text-slate-500">Saved listing</dt>
                                <dd class="mt-1">{{ $isSavedByCurrentTenant ? 'Saved to your shortlist.' : 'Not saved yet.' }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium text-slate-500">Inspection request</dt>
                                <dd class="mt-1">
                                    @if ($latestInspectionRequest)
                                        {{ str($latestInspectionRequest->status)->headline() }}
                                    @else
                                        Not submitted yet.
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="font-medium text-slate-500">Payment status</dt>
                                <dd class="mt-1">{{ $this->inspectionPaymentStatusCopy() }}</dd>
                            </div>
                            @if ($latestInspectionPaymentTransaction)
                                <div>
                                    <dt class="font-medium text-slate-500">Latest payment reference</dt>
                                    <dd class="mt-1 font-mono text-xs text-slate-700">{{ $latestInspectionPaymentTransaction->reference }}</dd>
                                </div>
                            @endif
                        </dl>

                        <div class="flex flex-wrap gap-3">
                            <a href="{{ route('tenant.saved-listings.index') }}" class="admin-button admin-button-secondary">Saved listings</a>
                            @if ($latestInspectionRequest)
                                <a href="{{ route('tenant.inspection-requests.show', ['inspectionRequestId' => $latestInspectionRequest->getKey()]) }}" class="admin-button admin-button-secondary">View request</a>
                            @endif
                            @if ($latestInspectionPaymentTransaction)
                                <a href="{{ route('tenant.payments.index', ['reference' => $latestInspectionPaymentTransaction->reference]) }}" class="admin-button admin-button-secondary">Inspection payment history</a>
                            @endif
                            @if ($latestRentPaymentTransaction)
                                <a href="{{ route('tenant.payments.index', ['reference' => $latestRentPaymentTransaction->reference]) }}" class="admin-button admin-button-secondary">Rent payment history</a>
                            @endif
                        </div>
                    </div>
                </x-admin.panel>
            </div>
        </div>

        <x-modal name="inspection-terms-tenant" maxWidth="2xl">
            <div class="admin-modal-panel" data-terms-gate-modal-content="{{ $this->inspectionTermsGate() }}">
                <div class="admin-modal-header">
                    <h3 class="text-lg font-semibold text-slate-950">Inspection terms</h3>
                    <p class="mt-1 text-sm text-slate-600">Read before you request a visit.</p>
                </div>
                <div class="admin-modal-body">
                    <p>The booking fee is separate from the property price.</p>
                    <p>The fee covers inspection handling and platform coordination for the visit.</p>
                    <p>The fee should be treated as non-refundable once checkout starts.</p>
                    <p>Your preferred date is a request, not a confirmed appointment.</p>
                    <p>Keep this modal open and read it fully before you accept the checkbox on the form.</p>
                    <div class="admin-callout">
                        <label class="flex items-start gap-3 text-sm text-slate-700">
                            <input type="checkbox" data-terms-gate-checkbox class="admin-checkbox mt-1" />
                            <span>I have read and accept the inspection terms before sending this request.</span>
                        </label>
                        <p class="admin-help" data-terms-gate-modal-status>
                            Keep this modal open for {{ config('payments.terms_gate_seconds', 10) }} seconds before the checkbox unlocks.
                        </p>
                        <p data-terms-gate-modal-warning class="mt-3 hidden text-sm text-rose-700 opacity-0 transition-opacity duration-300"></p>
                    </div>
                </div>
                <div class="admin-modal-footer">
                    <p class="text-sm text-slate-600">Accept the checkbox here, then close the modal and continue with the form.</p>
                    <button type="button" x-data x-on:click="$dispatch('close-modal', 'inspection-terms-tenant')" class="admin-button admin-button-primary">Close</button>
                </div>
            </div>
        </x-modal>
    </div>
</div>
