<div class="admin-page">
    <div class="admin-page-inner">
        <div class="vh-surface">
            <div class="p-6 space-y-5">
                <div>
                    <p class="admin-eyebrow">Browse properties</p>
                    <h2 class="admin-panel-title">Public property listings</h2>
                    <p class="admin-panel-copy">{{ $this->browseResultsCopy() }}</p>
                </div>

                <div>
                    <p class="text-sm font-medium text-gray-700">Browse by listing intent</p>
                    <div class="mt-3 flex flex-wrap gap-3">
                        @foreach ($intentTabs as $tab)
                            <button
                                type="button"
                                wire:click="setListingIntent('{{ $tab['value'] }}')"
                                class="inline-flex items-center rounded-full border px-4 py-2 text-sm font-medium transition {{ $listingIntent === $tab['value'] ? 'border-slate-900 bg-slate-900 text-white' : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400 hover:text-gray-900' }}"
                            >
                                {{ $tab['label'] }}
                            </button>
                        @endforeach
                    </div>
                    <p class="mt-3 text-sm text-gray-600">{{ $listingIntentHelpText }}</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <div class="xl:col-span-2">
                        <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                        <input wire:model.live.debounce.300ms="search" id="search" type="text" placeholder="Search by title, area, city, or landmark" class="vh-control" />
                    </div>

                    <div>
                        <label for="propertyType" class="block text-sm font-medium text-gray-700">Property type</label>
                        <select wire:model.live="propertyType" id="propertyType" class="vh-control">
                            <option value="">All types</option>
                            @foreach ($propertyTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="minPrice" class="block text-sm font-medium text-gray-700">{{ $this->minPriceLabel() }}</label>
                        <input wire:model.live.debounce.300ms="minPrice" id="minPrice" type="number" min="0" class="vh-control" />
                    </div>

                    <div>
                        <label for="maxPrice" class="block text-sm font-medium text-gray-700">{{ $this->maxPriceLabel() }}</label>
                        <input wire:model.live.debounce.300ms="maxPrice" id="maxPrice" type="number" min="0" class="vh-control" />
                    </div>

                    <div>
                        <label for="sort" class="block text-sm font-medium text-gray-700">Sort by</label>
                        <select wire:model.live="sort" id="sort" class="vh-control">
                            <option value="newest">Newest</option>
                            <option value="lowest_price">Lowest price</option>
                            <option value="highest_price">Highest price</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($properties as $property)
                <article class="vh-surface">
                    <div class="aspect-[4/3] bg-gray-100">
                        @if ($property->coverImage)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($property->coverImage->image_path) }}" alt="{{ $property->title }}" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full items-center justify-center text-sm text-gray-500">No image available yet</div>
                        @endif
                    </div>

                    <div class="p-6 space-y-3">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">{{ $property->title }}</h3>
                                <p class="text-sm text-gray-600">{{ $property->listingIntentLabel() }} {{ str($property->property_type)->headline() }} in {{ $property->area }}, {{ $property->city }}</p>
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
                        </div>

                        @if ($property->landmark)
                            <p class="text-sm text-gray-600">Near {{ $property->landmark }}</p>
                        @endif

                        <a href="{{ route('properties.show', $property) }}" class="vh-button vh-button-primary">
                            View Property
                        </a>
                    </div>
                </article>
            @empty
                <div class="vh-surface md:col-span-2 xl:col-span-3">
                    <div class="p-10 text-center text-sm text-gray-600">
                        No approved properties match your current filters yet.
                    </div>
                </div>
            @endforelse
        </div>

        {{ $properties->links() }}
    </div>
</div>
