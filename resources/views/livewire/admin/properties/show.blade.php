<div class="admin-page">
    <div class="admin-page-inner">
            @if (session('status'))
                <x-admin.alert>
                    {{ session('status') }}
                </x-admin.alert>
            @endif

            @if (! $historyAvailable)
                <x-admin.empty-state
                    title="Property review detail is not fully available yet."
                    copy="This page will show property review history and actions again once property history data is available in this environment."
                />
            @else
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,0.95fr)]">
                <div class="space-y-6">
                    <x-admin.panel>
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-950">Property summary</h3>
                                <p class="mt-1 text-sm text-slate-600">Review property details, landlord information, and uploaded evidence.</p>
                            </div>

                            <dl class="grid gap-4 md:grid-cols-2 text-sm text-slate-700">
                                <div><dt class="font-medium text-slate-500">Title</dt><dd class="mt-1">{{ $property->title }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Landlord</dt><dd class="mt-1">{{ $property->landlord?->name }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Landlord email</dt><dd class="mt-1">{{ $property->landlord?->email ?: 'Not provided' }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Landlord phone</dt><dd class="mt-1">{{ $property->landlord?->phone ?: 'Not provided' }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Listing intent</dt><dd class="mt-1">{{ $property->listingIntentLabel() }}</dd></div>
                                <div><dt class="font-medium text-slate-500">{{ $property->primaryPriceLabel() }}</dt><dd class="mt-1">{{ $property->formattedPrimaryPrice() }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Caution fee</dt><dd class="mt-1">{{ $property->caution_fee !== null ? \App\Support\Currency::format($property->caution_fee) : 'Not provided' }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Service charge</dt><dd class="mt-1">{{ $property->service_charge !== null ? \App\Support\Currency::format($property->service_charge) : 'Not provided' }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Availability</dt><dd class="mt-1">{{ $property->availabilityLabel() }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Total units</dt><dd class="mt-1">{{ $property->total_units }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Occupied units</dt><dd class="mt-1">{{ $property->occupied_units }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Available units</dt><dd class="mt-1">{{ $property->available_units }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Property type</dt><dd class="mt-1">{{ str($property->property_type)->headline() }}</dd></div>
                                <div><dt class="font-medium text-slate-500">City</dt><dd class="mt-1">{{ $property->city }}</dd></div>
                                <div><dt class="font-medium text-slate-500">LGA</dt><dd class="mt-1">{{ $property->lga }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Area</dt><dd class="mt-1">{{ $property->area }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Landmark</dt><dd class="mt-1">{{ $property->landmark ?: 'Not provided' }}</dd></div>
                                <div class="md:col-span-2"><dt class="font-medium text-slate-500">Address details</dt><dd class="mt-1">{{ $property->address_text ?: ($property->street ?: 'Not provided') }}</dd></div>
                                <div class="md:col-span-2"><dt class="font-medium text-slate-500">Description</dt><dd class="mt-1">{{ $property->description ?: 'No description provided' }}</dd></div>
                            </dl>
                        </div>
                    </x-admin.panel>

                    <x-admin.panel>
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-950">Uploaded images</h3>
                                <p class="mt-1 text-sm text-slate-600">Images remain private to the landlord workflow and admin review for now.</p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                @forelse ($property->images as $image)
                                    <div class="admin-data-box overflow-hidden p-0">
                                        <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->image_path) }}" target="_blank" rel="noopener noreferrer" class="block">
                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->image_path) }}" alt="Property image" class="h-48 w-full object-cover transition hover:opacity-90">
                                        </a>
                                    </div>
                                @empty
                                    <x-admin.empty-state title="No property images uploaded yet." />
                                @endforelse
                            </div>
                        </div>
                    </x-admin.panel>

                    <x-admin.panel>
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-950">Private property documents</h3>
                                <p class="mt-1 text-sm text-slate-600">Use the secure download links below for review.</p>
                            </div>

                            <div class="space-y-3">
                                @forelse ($property->documents as $document)
                                    <div class="admin-data-box flex items-start justify-between gap-4">
                                        <div>
                                            <p class="font-medium text-slate-900">{{ str($document->document_type)->headline() }}</p>
                                            <p class="text-sm text-slate-600">{{ $document->original_name ?: 'Property document' }}</p>
                                            <p class="mt-1 text-xs text-slate-500">Status: {{ str($document->review_status)->headline() }}</p>
                                        </div>
                                        <x-admin.button tag="a" variant="secondary" size="sm" href="{{ route('admin.properties.documents.download', [$property, $document]) }}">Download</x-admin.button>
                                    </div>
                                @empty
                                    <x-admin.empty-state title="No property documents uploaded yet." />
                                @endforelse
                            </div>
                        </div>
                    </x-admin.panel>
                </div>

                <div class="space-y-6">
                    <x-admin.panel>
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-950">Occupancy adjustment</h3>
                                <p class="mt-1 text-sm text-slate-600">Use this manual control to reflect real occupancy safely until a fuller completion workflow exists later.</p>
                            </div>

                            <div class="admin-data-box">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-medium text-slate-900">Current inventory summary</p>
                                        <p class="mt-1 text-sm text-slate-700">{{ $property->availabilityDetail() }}</p>
                                    </div>
                                    <x-admin.badge :tone="$property->isFullyOccupied() ? 'danger' : 'success'">
                                        {{ $property->availabilityLabel() }}
                                    </x-admin.badge>
                                </div>
                            </div>

                            <div>
                                <x-admin.label for="occupiedUnits">Occupied units</x-admin.label>
                                <x-admin.input wire:model.defer="occupiedUnits" id="occupiedUnits" type="number" min="0" max="{{ $property->total_units }}" />
                                <p class="mt-2 text-sm text-slate-600">Inspection requests, saves, and early payments do not change inventory automatically. Update occupancy here only when a real occupancy outcome needs to be reflected manually.</p>
                                <x-admin.error for="occupiedUnits" />
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <x-admin.button wire:click="updateOccupancy" wire:loading.attr="disabled" wire:target="updateOccupancy" variant="secondary">
                                    <span wire:loading.remove wire:target="updateOccupancy">Update Occupancy</span>
                                    <span wire:loading wire:target="updateOccupancy">Updating...</span>
                                </x-admin.button>
                            </div>
                        </div>
                    </x-admin.panel>

                    <x-admin.panel>
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-950">Review status</h3>
                                <p class="mt-1 text-sm text-slate-600">Approval confirms the listing passed internal review. Publishing is a separate step that makes it visible on the public property pages.</p>
                            </div>

                            <div class="admin-data-box">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-medium text-slate-900">Current review status</p>
                                        <p class="mt-1 text-sm text-slate-700">{{ str($property->status)->headline() }}</p>
                                    </div>
                                    <x-admin.badge>
                                        {{ $property->is_verified ? 'Verified' : 'Not verified' }}
                                    </x-admin.badge>
                                </div>
                            </div>

                            <div>
                                <x-admin.label for="reviewNotes">Review notes</x-admin.label>
                                <x-admin.textarea wire:model.defer="reviewNotes" id="reviewNotes" rows="4" />
                                <x-admin.error for="reviewNotes" />
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <x-admin.button wire:click="changeStatus('approved')" wire:loading.attr="disabled" wire:target="changeStatus" variant="success">
                                    <span wire:loading.remove wire:target="changeStatus">Approve</span>
                                    <span wire:loading wire:target="changeStatus">Processing...</span>
                                </x-admin.button>
                                <x-admin.button wire:click="changeStatus('rejected')" wire:loading.attr="disabled" wire:target="changeStatus" variant="danger">
                                    <span wire:loading.remove wire:target="changeStatus">Reject</span>
                                    <span wire:loading wire:target="changeStatus">Processing...</span>
                                </x-admin.button>
                                <x-admin.button wire:click="changeStatus('suspended')" wire:loading.attr="disabled" wire:target="changeStatus" variant="warning">
                                    <span wire:loading.remove wire:target="changeStatus">Suspend</span>
                                    <span wire:loading wire:target="changeStatus">Processing...</span>
                                </x-admin.button>
                                <x-admin.button wire:click="changeStatus('pending_review')" wire:loading.attr="disabled" wire:target="changeStatus" variant="secondary">
                                    <span wire:loading.remove wire:target="changeStatus">Return to Pending Review</span>
                                    <span wire:loading wire:target="changeStatus">Processing...</span>
                                </x-admin.button>
                            </div>
                        </div>
                    </x-admin.panel>

                    <x-admin.panel>
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-950">Publish state</h3>
                                <p class="mt-1 text-sm text-slate-600">Published properties appear in public discovery. Approved but unpublished properties stay internal until staff make them live.</p>
                            </div>

                            <div class="admin-data-box">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-medium text-slate-900">Current publish state</p>
                                        <p class="mt-1 text-sm text-slate-700">{{ $property->is_published ? 'Published publicly' : 'Not published publicly' }}</p>
                                    </div>
                                    <x-admin.badge :tone="$property->is_published ? 'success' : 'warning'">
                                        {{ $property->is_published ? 'Live' : 'Unpublished' }}
                                    </x-admin.badge>
                                </div>
                            </div>

                            <x-admin.error for="publish" />

                            <div class="flex flex-wrap gap-3">
                                @if ($property->is_published)
                                    <x-admin.button wire:click="unpublish" wire:loading.attr="disabled" wire:target="unpublish">
                                        <span wire:loading.remove wire:target="unpublish">Unpublish</span>
                                        <span wire:loading wire:target="unpublish">Processing...</span>
                                    </x-admin.button>
                                @else
                                    <x-admin.button wire:click="publish" wire:loading.attr="disabled" wire:target="publish" variant="success" :disabled="! $canBePublished">
                                        <span wire:loading.remove wire:target="publish">Publish</span>
                                        <span wire:loading wire:target="publish">Processing...</span>
                                    </x-admin.button>
                                @endif
                            </div>
                        </div>
                    </x-admin.panel>

                    <x-admin.partials.status-history-card
                        title="Status history"
                        description="Every admin or staff property review change is recorded here."
                        :histories="$property->statusHistories"
                    />
                </div>
            </div>
            @endif
    </div>
</div>
