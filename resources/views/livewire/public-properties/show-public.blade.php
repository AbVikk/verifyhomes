<div class="py-12">
    <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-3 px-4 sm:px-0 md:flex-row md:items-end md:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $property->title }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ $property->listingIntentLabel() }} {{ str($property->property_type)->headline() }} in {{ $property->area }}, {{ $property->city }}</p>
            </div>
            <a href="{{ route('properties.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">Back to Properties</a>
        </div>

        @if (session('status'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg shadow-sm">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->has('property'))
            <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-lg shadow-sm">
                {{ $errors->first('property') }}
            </div>
        @endif

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
                            <h3 class="text-lg font-semibold text-gray-900">Request an inspection</h3>
                            <p class="mt-1 text-sm text-gray-600">Send your request here. VerifyHomes will handle scheduling.</p>
                        </div>

                        @guest
                            <div class="space-y-3">
                                <a href="{{ route('login') }}" class="vh-button vh-button-primary w-full">Log in to request</a>
                                <a href="{{ route('register.tenant') }}" class="vh-button vh-button-secondary w-full">Register as Tenant</a>
                            </div>
                        @else
                            <div class="vh-subtle-surface px-4 py-3 text-sm text-slate-700">
                                Inspection requests are available through tenant accounts only.
                            </div>
                        @endguest
                    </div>
                </div>
            </div>
        </div>

        <x-modal name="inspection-terms-public" maxWidth="2xl">
            <div class="vh-modal-panel">
                <div class="vh-modal-header">
                    <h3 class="text-lg font-semibold text-slate-950">Inspection terms</h3>
                    <p class="mt-1 text-sm text-slate-600">Read before you request a visit.</p>
                </div>
                <div class="vh-modal-body">
                    <p>The booking fee is separate from the property price.</p>
                    <p>The fee covers inspection handling.</p>
                    <p>The fee should be treated as non-refundable once checkout starts.</p>
                    <p>Your preferred date is a request, not a confirmed appointment.</p>
                </div>
                <div class="vh-modal-footer">
                    <p class="text-sm text-slate-600">Accept the checkbox on the form before continuing.</p>
                    <button type="button" x-data x-on:click="$dispatch('close-modal', 'inspection-terms-public')" class="vh-button vh-button-primary">Close</button>
                </div>
            </div>
        </x-modal>
    </div>
</div>
