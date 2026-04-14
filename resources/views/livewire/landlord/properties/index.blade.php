@php
    use App\Support\LandlordOptions;
@endphp

<div class="admin-page">
    <div class="admin-page-inner">
        @if (session('status'))
            <div class="admin-flash-success">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="admin-eyebrow">Property workspace</p>
                <h2 class="admin-panel-title">Submitted properties</h2>
                <p class="admin-panel-copy">Use this queue to understand listing state quickly and move into the right next landlord action.</p>
            </div>

            @if ($canCreateProperties)
                <a href="{{ route('landlord.properties.create') }}" class="admin-button admin-button-primary">
                    Create Property
                </a>
            @else
                <a href="{{ route('landlord.documents') }}" class="admin-button admin-button-secondary">
                    Complete Verification
                </a>
            @endif
        </div>

        @unless ($canCreateProperties)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                {{ $propertyCreationBlockMessage }}
            </div>
        @endunless

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <x-admin.panel class="h-full">
                <p class="admin-eyebrow">Total listings</p>
                <p class="mt-3 text-3xl font-semibold text-slate-950">{{ $totalPropertiesCount }}</p>
                <p class="admin-help">All properties currently in your landlord workspace.</p>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <p class="admin-eyebrow">Needs attention</p>
                <p class="mt-3 text-3xl font-semibold text-slate-950">{{ $needsAttentionPropertiesCount }}</p>
                <p class="admin-help">Listings that currently need review, follow-through, or missing-file checks.</p>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <p class="admin-eyebrow">Pending review</p>
                <p class="mt-3 text-3xl font-semibold text-slate-950">{{ $pendingReviewPropertiesCount }}</p>
                <p class="admin-help">Listings still waiting for an admin review decision.</p>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <p class="admin-eyebrow">Approved, unpublished</p>
                <p class="mt-3 text-3xl font-semibold text-slate-950">{{ $approvedUnpublishedPropertiesCount }}</p>
                <p class="admin-help">Approved inventory that is not live in public discovery yet.</p>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <p class="admin-eyebrow">Live published</p>
                <p class="mt-3 text-3xl font-semibold text-slate-950">{{ $livePublishedPropertiesCount }}</p>
                <p class="admin-help">Listings currently visible on the public property pages.</p>
            </x-admin.panel>
        </div>

        <x-admin.panel>
            <div class="space-y-4">
                @forelse ($properties as $property)
                    <div class="admin-subsurface p-5">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                            <div class="space-y-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('landlord.properties.edit', $property) }}" class="text-base font-semibold text-slate-900 transition hover:text-sky-800">
                                        {{ $property->title }}
                                    </a>
                                    <span class="admin-badge admin-badge-info">
                                        {{ LandlordOptions::listingIntentLabel($property->listing_intent) }}
                                    </span>
                                    <span class="admin-badge admin-badge-neutral">
                                        {{ str($property->status)->headline() }}
                                    </span>
                                    @if ($property->isPubliclyVisible())
                                        <span class="admin-badge admin-badge-success">Live</span>
                                    @elseif ($property->status === 'approved' && $property->is_verified && ! $property->is_published)
                                        <span class="admin-badge admin-badge-info">Approved, unpublished</span>
                                    @endif
                                    @if ($property->open_inspection_requests_count > 0)
                                        <span class="admin-badge admin-badge-info">{{ $property->open_inspection_requests_count }} open request{{ $property->open_inspection_requests_count > 1 ? 's' : '' }}</span>
                                    @endif
                                    @if ($property->scheduled_inspection_requests_count > 0)
                                        <span class="admin-badge admin-badge-success">{{ $property->scheduled_inspection_requests_count }} scheduled visit{{ $property->scheduled_inspection_requests_count > 1 ? 's' : '' }}</span>
                                    @endif
                                    @if ($property->listing_intent !== 'for_rent' && $property->purchase_count > 0)
                                        <span class="admin-badge admin-badge-success">Purchase recorded</span>
                                    @endif
                                    @if ($property->listing_intent !== 'for_rent' && $property->available_units <= 0)
                                        <span class="admin-badge admin-badge-neutral">Sold out</span>
                                    @endif
                                    @if ($property->images_count === 0)
                                        <span class="admin-badge admin-badge-warning">No images yet</span>
                                    @endif
                                    @if ($property->documents_count === 0)
                                        <span class="admin-badge admin-badge-warning">No documents yet</span>
                                    @endif
                                </div>

                                <p class="text-sm text-slate-600">{{ str($property->property_type)->headline() }} in {{ $property->area }}, {{ $property->city }}</p>
                                <p class="text-sm text-slate-600">{{ LandlordOptions::listingIntentAmountLabel($property->listing_intent) }}: {{ $this->formatMoney($property->rent_amount) }}</p>
                                <p class="text-xs text-slate-500">{{ $property->images_count }} image(s) and {{ $property->documents_count }} document(s)</p>
                                @if ($property->listing_intent !== 'for_rent')
                                    <p class="text-xs text-slate-500">Purchases recorded: {{ $property->purchase_count }}</p>
                                @endif
                                <p class="text-xs text-slate-500">Updated {{ $property->updated_at?->diffForHumans() ?? 'recently' }}</p>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Next step</p>
                                    <p class="mt-1 text-sm text-slate-700">{{ $this->nextStepSummary($property) }}</p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-3 xl:justify-end">
                                <a href="{{ route('landlord.properties.edit', $property) }}" class="admin-button admin-button-secondary">
                                    Edit Property
                                </a>
                                @if ($property->open_inspection_requests_count > 0)
                                    <a href="{{ route('landlord.inspection-requests.index') }}" class="admin-button admin-button-secondary">
                                        View Inspection Requests
                                    </a>
                                @endif
                                @if ($property->isPubliclyVisible())
                                    <a href="{{ route('properties.show', $property) }}" class="admin-button admin-button-success">
                                        View Public Listing
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <x-admin.empty-state
                        title="You have not created any properties yet."
                        copy="Create your first listing to start the landlord review workflow."
                    />
                @endforelse
            </div>
        </x-admin.panel>
    </div>
</div>
