@php
    use Illuminate\Support\Facades\Storage;
@endphp

<div class="admin-page">
    <div class="admin-page-inner">
        @if (session('status'))
            <x-admin.alert>
                {{ session('status') }}
            </x-admin.alert>
        @endif

        <div class="grid gap-6 md:grid-cols-3">
            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Saved listings</p>
                    <p class="text-3xl font-semibold text-slate-950">{{ $savedProperties->count() }}</p>
                    <p class="text-sm text-slate-600">Properties you have kept in your shortlist.</p>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Publicly listed</p>
                    <p class="text-3xl font-semibold text-slate-950">{{ $currentlyAvailableCount }}</p>
                    <p class="text-sm text-slate-600">Saved listings that are still live on the public property pages, regardless of current unit occupancy.</p>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Next step</p>
                    <p class="text-sm text-slate-600">Open a listing, jump into an active inspection request, or review payment history for any saved property that already moved forward.</p>
                </div>
            </x-admin.panel>
        </div>

        <x-admin.panel>
            <div class="space-y-4">
                <div>
                    <p class="admin-eyebrow">Saved listings</p>
                    <h3 class="admin-panel-title">Your shortlisted properties</h3>
                    <p class="admin-panel-copy">Keep track of properties you want to revisit, compare, or book for inspection later.</p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('properties.index') }}" class="admin-button admin-button-primary">Browse More Properties</a>
                    <a href="{{ route('tenant.dashboard') }}" class="admin-button admin-button-secondary">Back to Dashboard</a>
                </div>
            </div>
        </x-admin.panel>

        @if (! $savedPropertiesAvailable)
            <x-admin.panel>
                <x-admin.empty-state
                    title="Saved listings are not available yet."
                    copy="This page will populate automatically after the saved listings table is available in this environment."
                />
            </x-admin.panel>
        @elseif ($savedProperties->isEmpty())
            <x-admin.panel>
                <x-admin.empty-state
                    title="You have not saved any listings yet."
                    copy="Use the Save Listing button on property pages to build your shortlist here."
                />
            </x-admin.panel>
        @else
            <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($savedProperties as $property)
                    @php
                        $openInspectionRequest = $openInspectionRequestsByProperty->get($property->id);
                        $latestPaidTransaction = $latestPaidTransactionsByProperty->get($property->id);
                    @endphp
                    <x-admin.panel class="overflow-hidden">
                        <div class="-m-6">
                            <div class="aspect-[4/3] border-b border-slate-200 bg-slate-100">
                                @if ($property->coverImage)
                                    <a href="{{ Storage::disk('public')->url($property->coverImage->image_path) }}" target="_blank" rel="noopener noreferrer" class="block h-full w-full">
                                        <img src="{{ Storage::disk('public')->url($property->coverImage->image_path) }}" alt="{{ $property->title }}" class="h-full w-full object-cover transition hover:opacity-95">
                                    </a>
                                @else
                                    <div class="flex h-full items-center justify-center text-sm text-slate-500">No image available yet</div>
                                @endif
                            </div>

                            <div class="space-y-4 p-6">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 class="text-lg font-semibold text-slate-900">{{ $property->title }}</h3>
                                        <p class="text-sm text-slate-600">{{ $property->listingIntentLabel() }} {{ str($property->property_type)->headline() }} in {{ $property->area }}, {{ $property->city }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $property->primaryPriceLabel() }}</p>
                                        <p class="text-sm font-semibold text-slate-900">{{ $property->formattedPrimaryPrice() }}</p>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
                                        {{ $property->listingIntentLabel() }}
                                    </span>
                                    @if ($property->isPubliclyVisible() && ! $property->isFullyOccupied())
                                        <span class="inline-flex items-center rounded-full bg-sky-50 px-3 py-1 text-xs font-medium text-sky-700">
                                            Public listing live
                                        </span>
                                    @elseif (! $property->isPubliclyVisible())
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                                            No longer public
                                        </span>
                                    @endif
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium {{ $property->isFullyOccupied() ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' }}">
                                        {{ $property->availabilityLabel() }}
                                    </span>

                                    @if ($openInspectionRequest)
                                        <span class="inline-flex items-center rounded-full bg-sky-50 px-3 py-1 text-xs font-medium text-sky-700">{{ str($openInspectionRequest->status)->headline() }} request active</span>
                                    @endif

                                    @if ($latestPaidTransaction)
                                        <span class="inline-flex items-center rounded-full bg-violet-50 px-3 py-1 text-xs font-medium text-violet-700">Payment verified</span>
                                    @endif
                                </div>

                                @if ($property->landmark)
                                    <p class="text-sm text-slate-600">Near {{ $property->landmark }}</p>
                                @endif

                                @if ($property->pivot?->created_at)
                                    <p class="text-sm text-slate-500">Saved {{ $property->pivot->created_at->diffForHumans() }}</p>
                                @endif

                                @if ($latestPaidTransaction)
                                    <p class="text-sm text-slate-600">Latest verified payment reference: <span class="font-mono text-xs">{{ $latestPaidTransaction->reference }}</span></p>
                                @endif

                                <div class="flex flex-wrap gap-3">
                                    @if ($property->isPubliclyVisible())
                                        <a href="{{ route('properties.show', $property) }}" class="admin-button admin-button-primary">View Property</a>
                                    @endif

                                    @if ($openInspectionRequest)
                                        <a href="{{ route('tenant.inspection-requests.show', ['inspectionRequestId' => $openInspectionRequest->getKey()]) }}" class="admin-button admin-button-secondary">View Request</a>
                                    @elseif ($property->isPubliclyVisible())
                                        <a href="{{ route('properties.show', $property) }}#inspection-request" class="admin-button admin-button-secondary">Request Inspection</a>
                                    @endif

                                    @if ($latestPaidTransaction)
                                        <a href="{{ route('tenant.payments.index', ['reference' => $latestPaidTransaction->reference]) }}" class="admin-action-link">View Payment</a>
                                    @endif

                                    <x-admin.button wire:click="removeSavedProperty({{ $property->id }})" wire:loading.attr="disabled" wire:target="removeSavedProperty" variant="secondary">
                                        <span wire:loading.remove wire:target="removeSavedProperty">Remove</span>
                                        <span wire:loading wire:target="removeSavedProperty">Removing...</span>
                                    </x-admin.button>
                                </div>

                                @if (! $property->isPubliclyVisible())
                                    <p class="text-sm text-slate-600">This listing is no longer available on the public property pages, but your saved history remains here until you remove it.</p>
                                @endif
                            </div>
                        </div>
                    </x-admin.panel>
                @endforeach
            </div>
        @endif
    </div>
</div>
