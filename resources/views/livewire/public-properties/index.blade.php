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
                    <p class="admin-eyebrow">Listings in results</p>
                    <p class="text-3xl font-semibold text-slate-950">{{ $properties->total() }}</p>
                    <p class="text-sm text-slate-600">Approved and verified properties that currently match your filters.</p>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Saved listings</p>
                    <p class="text-3xl font-semibold text-slate-950">{{ $savedListingsCount }}</p>
                    <p class="text-sm text-slate-600">Listings already saved to your tenant shortlist.</p>
                    <a href="{{ route('tenant.saved-listings.index') }}" class="admin-inline-link">Open saved listings</a>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Browse workflow</p>
                    <p class="text-sm text-slate-600">Open a listing, save it for later, or jump straight into an inspection request when a property already looks right.</p>
                </div>
            </x-admin.panel>
        </div>

        <div class="flex flex-col gap-4">
            <div>
                <p class="admin-eyebrow">Browse properties</p>
                <h2 class="admin-panel-title">Verified listings</h2>
                <p class="admin-panel-copy">{{ $this->browseResultsCopy() }}</p>
            </div>

            <x-admin.panel>
                <div class="space-y-5">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Browse by listing intent</p>
                        <div class="mt-3 flex flex-wrap gap-3">
                            @foreach ($intentTabs as $tab)
                                <button
                                    type="button"
                                    wire:click="setListingIntent('{{ $tab['value'] }}')"
                                    class="inline-flex items-center rounded-full border px-4 py-2 text-sm font-medium transition {{ $listingIntent === $tab['value'] ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:border-slate-400 hover:text-slate-900' }}"
                                >
                                    {{ $tab['label'] }}
                                </button>
                            @endforeach
                        </div>
                        <p class="mt-3 text-sm text-slate-600">{{ $listingIntentHelpText }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                        <div class="xl:col-span-2">
                            <label for="search" class="admin-label">Search</label>
                            <input wire:model.live.debounce.300ms="search" id="search" type="text" placeholder="Search by title, area, city, or landmark" class="admin-control" />
                        </div>

                        <div>
                            <label for="propertyType" class="admin-label">Property type</label>
                            <select wire:model.live="propertyType" id="propertyType" class="admin-control admin-control-select">
                                <option value="">All types</option>
                                @foreach ($propertyTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="minPrice" class="admin-label">{{ $this->minPriceLabel() }}</label>
                            <input wire:model.live.debounce.300ms="minPrice" id="minPrice" type="number" min="0" class="admin-control" />
                        </div>

                        <div>
                            <label for="maxPrice" class="admin-label">{{ $this->maxPriceLabel() }}</label>
                            <input wire:model.live.debounce.300ms="maxPrice" id="maxPrice" type="number" min="0" class="admin-control" />
                        </div>

                        <div>
                            <label for="sort" class="admin-label">Sort by</label>
                            <select wire:model.live="sort" id="sort" class="admin-control admin-control-select">
                                <option value="newest">Newest</option>
                                <option value="lowest_price">Lowest price</option>
                                <option value="highest_price">Highest price</option>
                            </select>
                        </div>
                    </div>
                </div>
            </x-admin.panel>
        </div>

        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($properties as $property)
                @php
                    $isSaved = in_array($property->id, $savedPropertyIds, true);
                    $openInspectionRequest = $openInspectionRequestsByProperty->get($property->id);
                @endphp
                <x-admin.panel class="overflow-hidden">
                    <div class="-m-6">
                            <div class="aspect-[4/3] border-b border-slate-200 bg-slate-100">
                                @if ($property->coverImage)
                                    <a href="{{ route('properties.show', $property) }}" class="block h-full w-full">
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($property->coverImage->image_path) }}" alt="{{ $property->title }}" class="h-full w-full object-cover">
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
                                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium {{ $property->isFullyOccupied() ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' }}">
                                    {{ $property->availabilityLabel() }}
                                </span>
                                @if ($isSaved)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">Saved</span>
                                @endif
                                @if ($openInspectionRequest)
                                    <span class="inline-flex items-center rounded-full bg-sky-50 px-3 py-1 text-xs font-medium text-sky-700">{{ str($openInspectionRequest->status)->headline() }} request active</span>
                                @endif
                            </div>

                            @if ($property->landmark)
                                <p class="text-sm text-slate-600">Near {{ $property->landmark }}</p>
                            @endif

                            <div class="flex flex-wrap gap-3">
                                <a href="{{ route('properties.show', $property) }}" class="admin-button admin-button-primary">
                                    View Details
                                </a>

                                <button type="button" wire:click="toggleSavedProperty({{ $property->id }})" wire:loading.attr="disabled" wire:target="toggleSavedProperty" class="admin-button admin-button-secondary">
                                    <span wire:loading.remove wire:target="toggleSavedProperty">{{ $isSaved ? 'Remove Saved' : 'Save Listing' }}</span>
                                    <span wire:loading wire:target="toggleSavedProperty">Processing...</span>
                                </button>

                                @if ($openInspectionRequest)
                                    <a href="{{ route('tenant.inspection-requests.show', ['inspectionRequestId' => $openInspectionRequest->getKey()]) }}" class="admin-action-link">
                                        View Request
                                    </a>
                                @else
                                    <a href="{{ route('properties.show', $property) }}#inspection-request" class="admin-action-link">
                                        Request Inspection
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </x-admin.panel>
            @empty
                <div class="md:col-span-2 xl:col-span-3">
                    <x-admin.empty-state
                        title="No approved properties match your current filters yet."
                        copy="Try widening your search or switching to a different listing-intent tab."
                    />
                </div>
            @endforelse
        </div>

        {{ $properties->links() }}
    </div>
</div>
