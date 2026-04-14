<div class="admin-page">
    <div class="admin-page-inner">
        @if (session('status'))
            <x-admin.alert>
                {{ session('status') }}
            </x-admin.alert>
        @endif

        @if ($errors->has('property'))
            <x-admin.alert tone="danger">
                {{ $errors->first('property') }}
            </x-admin.alert>
        @endif

        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="admin-eyebrow">Public property listing</p>
                <h2 class="admin-panel-title">{{ $property->title }}</h2>
                <p class="admin-panel-copy">{{ $property->listingIntentLabel() }} {{ str($property->property_type)->headline() }} in {{ $property->area }}, {{ $property->city }}</p>
            </div>
            <a href="{{ route('properties.index') }}" class="admin-action-link">Back to properties</a>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
            <div class="space-y-6">
                <div class="vh-surface">
                    <div class="p-6 space-y-4">
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            @forelse ($property->images as $image)
                                <div class="overflow-hidden rounded-lg border border-gray-200 bg-gray-100">
                                    <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->image_path) }}" target="_blank" rel="noopener noreferrer" class="block">
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->image_path) }}" alt="{{ $property->title }} image" class="h-64 w-full object-cover transition hover:opacity-95">
                                    </a>
                                </div>
                            @empty
                                <div class="md:col-span-2 xl:col-span-3 rounded-lg border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500">
                                    Photos will appear here once they are available.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="vh-surface">
                    <div class="p-6 space-y-5">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Property details</h3>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 text-sm text-gray-700">
                            <div><p class="font-medium text-gray-500">Listing intent</p><p class="mt-1">{{ $property->listingIntentLabel() }}</p></div>
                            <div><p class="font-medium text-gray-500">{{ $property->primaryPriceLabel() }}</p><p class="mt-1">{{ $property->formattedPrimaryPrice() }}</p></div>
                            @if ($property->property_type === 'land')
                                <div><p class="font-medium text-gray-500">Land size</p><p class="mt-1">{{ $property->landSizeLabel() ?? 'Not listed' }}</p></div>
                            @else
                                <div><p class="font-medium text-gray-500">Bedrooms</p><p class="mt-1">{{ $property->bedrooms ?? 'Not listed' }}</p></div>
                                <div><p class="font-medium text-gray-500">Bathrooms</p><p class="mt-1">{{ $property->bathrooms ?? 'Not listed' }}</p></div>
                                <div><p class="font-medium text-gray-500">Toilets</p><p class="mt-1">{{ $property->toilets ?? 'Not listed' }}</p></div>
                            @endif
                            <div><p class="font-medium text-gray-500">City</p><p class="mt-1">{{ $property->city }}</p></div>
                            <div><p class="font-medium text-gray-500">Area</p><p class="mt-1">{{ $property->area }}</p></div>
                            <div><p class="font-medium text-gray-500">LGA</p><p class="mt-1">{{ $property->lga }}</p></div>
                            <div><p class="font-medium text-gray-500">Landmark</p><p class="mt-1">{{ $property->landmark ?: 'Not listed' }}</p></div>
                            <div><p class="font-medium text-gray-500">Property type</p><p class="mt-1">{{ str($property->property_type)->headline() }}</p></div>
                            <div><p class="font-medium text-gray-500">Availability</p><p class="mt-1">{{ $property->availabilityDetail() }}</p></div>
                        </div>

                        <div class="vh-subtle-surface px-4 py-3">
                            <p class="text-sm font-semibold text-slate-900">{{ $property->availabilityLabel() }}</p>
                            <p class="mt-1 text-sm text-gray-600">{{ $property->availabilityDetail() }}</p>
                        </div>

                        <div>
                            <h4 class="text-sm font-medium text-gray-500">Description</h4>
                            <p class="mt-2 text-sm leading-6 text-gray-700">{{ $property->description ?: 'No additional description provided yet.' }}</p>
                        </div>

                        @if ($property->youtube_url)
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">Video tour</h4>
                                <a href="{{ $property->youtube_url }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex items-center text-sm font-medium text-slate-700 hover:text-slate-900">
                                    Watch on YouTube
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="vh-surface">
                    <div class="p-6 space-y-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Inspection access</h3>
                            <p class="mt-1 text-sm text-gray-600">Inspection requests stay on tenant accounts. This page shows the public listing exactly as it appears to the market.</p>
                        </div>

                        <div class="vh-subtle-surface px-4 py-3 text-sm text-slate-700">
                            Inspection requests are available through tenant accounts only.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
