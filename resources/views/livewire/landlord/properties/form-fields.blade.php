@php
    use App\Support\LandlordOptions;
@endphp

<div class="space-y-8">
    <div class="admin-subsurface p-5 space-y-4">
        <div>
            <h3 class="text-base font-semibold text-slate-900">Listing basics</h3>
            <p class="admin-help">Start with the listing purpose, property type, and core description so the rest of the form reads clearly.</p>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label for="title" class="admin-label">Property title</label>
                <input wire:model.defer="title" id="title" type="text" class="admin-control" placeholder="Example: Clean Two Bedroom Apartment in Alagbaka" />
                <p class="admin-help">Use a clear landlord-facing title that makes the property easy to recognize later in your queue.</p>
                @error('title') <p class="admin-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="listingIntent" class="admin-label">Listing purpose</label>
                <select wire:model.live="listingIntent" id="listingIntent" class="admin-control admin-control-select">
                    @foreach (LandlordOptions::listingIntents() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="admin-help">{{ $listingIntentGuidance }}</p>
                @error('listingIntent') <p class="admin-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="propertyType" class="admin-label">Property type</label>
                <select wire:model.live="propertyType" id="propertyType" class="admin-control admin-control-select">
                    <option value="">Select type</option>
                    @foreach (LandlordOptions::propertyTypes() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="admin-help">{{ $propertyTypeHelp }}</p>
                @error('propertyType') <p class="admin-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="description" class="admin-label">Description</label>
                <textarea wire:model.defer="description" id="description" rows="5" class="admin-control admin-control-textarea"></textarea>
                <p class="admin-help">Describe the layout, condition, and anything a reviewer or later tenant should understand quickly.</p>
                @error('description') <p class="admin-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div class="admin-subsurface p-5 space-y-4">
        <div>
            <h3 class="text-base font-semibold text-slate-900">Pricing and layout</h3>
            <p class="admin-help">The main amount label stays tied to the selected listing purpose so the pricing meaning stays consistent. Rental listings also show how the 20% platform fee affects the final listed rent and landlord net.</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            <span class="font-medium text-slate-900">{{ $this->listingIntentLabel() }}:</span>
            {{ $this->listingIntentSummary() }}
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            @if ($listingIntent === 'for_rent')
                <div>
                    <label for="pricingModel" class="admin-label">Rent pricing model</label>
                    <select wire:model.live="pricingModel" id="pricingModel" class="admin-control admin-control-select">
                        @foreach ($this->pricingModelOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="admin-help">{{ $pricingModelHelp }}</p>
                    @error('pricingModel') <p class="admin-error">{{ $message }}</p> @enderror
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pricing preview</p>
                    <p class="mt-2 text-sm text-slate-700">Tenant-facing listed rent: <span class="font-semibold text-slate-900">&#8358;{{ $this->pricingPreviewAmount() }}</span></p>
                    <p class="mt-2 text-sm text-slate-700">Estimated landlord net after 20% fee: <span class="font-semibold text-slate-900">&#8358;{{ $this->landlordNetPreviewAmount() }}</span></p>
                    <p class="mt-2 text-sm text-slate-600">The server recalculates this on save, so the stored listing price and landlord net stay auditable.</p>
                </div>
            @endif

            <div>
                <label for="rentAmount" class="admin-label">{{ $primaryAmountLabel }}</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm font-medium text-slate-500">&#8358;</span>
                    <input wire:model.defer="rentAmount" id="rentAmount" type="number" min="0" step="0.01" inputmode="decimal" class="admin-control pl-8" placeholder="{{ $primaryAmountPlaceholder }}" />
                </div>
                <p class="admin-help">{{ $primaryAmountHelp }}</p>
                @error('rentAmount') <p class="admin-error">{{ $message }}</p> @enderror
            </div>

            @if ($showsLandSizeFields)
                <div>
                    <label for="landSize" class="admin-label">Land size</label>
                    <input wire:model.defer="landSize" id="landSize" type="number" min="0" step="0.01" inputmode="decimal" class="admin-control" placeholder="650" />
                    <p class="admin-help">{{ $landSizeHelp }}</p>
                    @error('landSize') <p class="admin-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="landSizeUnit" class="admin-label">Land size unit</label>
                    <select wire:model.defer="landSizeUnit" id="landSizeUnit" class="admin-control admin-control-select">
                        @foreach (LandlordOptions::landSizeUnits() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="admin-help">{{ $landSizeUnitHelp }}</p>
                    @error('landSizeUnit') <p class="admin-error">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label for="cautionFee" class="admin-label">Caution fee</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm font-medium text-slate-500">&#8358;</span>
                    <input wire:model.defer="cautionFee" id="cautionFee" type="number" min="0" step="0.01" inputmode="decimal" class="admin-control pl-8" placeholder="150000.00" />
                </div>
                <p class="admin-help">{{ $cautionFeeHelp }}</p>
                @error('cautionFee') <p class="admin-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="serviceCharge" class="admin-label">Service charge</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm font-medium text-slate-500">&#8358;</span>
                    <input wire:model.defer="serviceCharge" id="serviceCharge" type="number" min="0" step="0.01" inputmode="decimal" class="admin-control pl-8" placeholder="25000.00" />
                </div>
                <p class="admin-help">{{ $serviceChargeHelp }}</p>
                @error('serviceCharge') <p class="admin-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="totalUnits" class="admin-label">{{ $totalUnitsLabel }}</label>
                <input wire:model.defer="totalUnits" id="totalUnits" type="number" min="1" step="1" class="admin-control" />
                <p class="admin-help">{{ $totalUnitsHelp }}</p>
                <p class="admin-help">{{ $this->unitInventoryGuidance() }}</p>
                @error('totalUnits') <p class="admin-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-3">
            @if ($showsBedroomField)
                <div>
                    <label for="bedrooms" class="admin-label">Bedrooms</label>
                    <input wire:model.defer="bedrooms" id="bedrooms" type="number" min="0" class="admin-control" />
                    <p class="admin-help">{{ $roomCountHelp }}</p>
                    @error('bedrooms') <p class="admin-error">{{ $message }}</p> @enderror
                </div>
            @endif

            @if ($showsBathroomField)
                <div>
                    <label for="bathrooms" class="admin-label">Bathrooms</label>
                    <input wire:model.defer="bathrooms" id="bathrooms" type="number" min="0" class="admin-control" />
                    <p class="admin-help">Include this where it helps explain the practical layout.</p>
                    @error('bathrooms') <p class="admin-error">{{ $message }}</p> @enderror
                </div>
            @endif

            @if ($showsToiletField)
                <div>
                    <label for="toilets" class="admin-label">Toilets</label>
                    <input wire:model.defer="toilets" id="toilets" type="number" min="0" class="admin-control" />
                    <p class="admin-help">Include this where it helps explain the practical layout.</p>
                    @error('toilets') <p class="admin-error">{{ $message }}</p> @enderror
                </div>
            @endif
        </div>
    </div>

    <div class="admin-subsurface p-5 space-y-4">
        <div>
            <h3 class="text-base font-semibold text-slate-900">Location details</h3>
            <p class="admin-help">Use recognizable location details so the listing is easier to review and easier to understand later in the queue.</p>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label for="city" class="admin-label">City or town</label>
                <input wire:model.defer="city" id="city" type="text" class="admin-control" placeholder="Example: Akure" />
                @error('city') <p class="admin-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="lga" class="admin-label">LGA</label>
                <select wire:model.defer="lga" id="lga" class="admin-control admin-control-select">
                    <option value="">Select an Ondo State LGA</option>
                    @foreach (LandlordOptions::ondoLgas() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="admin-help">Use the exact local government area for the listing location.</p>
                @error('lga') <p class="admin-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="area" class="admin-label">Area</label>
                <input wire:model.defer="area" id="area" type="text" class="admin-control" placeholder="Example: Alagbaka" />
                <p class="admin-help">Keep this specific to the neighborhood or district buyers will recognize.</p>
                @error('area') <p class="admin-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="landmark" class="admin-label">Landmark</label>
                <input wire:model.defer="landmark" id="landmark" type="text" class="admin-control" placeholder="Example: Near Shoprite" />
                <p class="admin-help">Optional, but useful when a well-known nearby point helps with orientation.</p>
                @error('landmark') <p class="admin-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="street" class="admin-label">Street</label>
                <input wire:model.defer="street" id="street" type="text" class="admin-control" placeholder="Example: Oda Road" />
                <p class="admin-help">Optional street detail for internal accuracy and later review.</p>
                @error('street') <p class="admin-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="state" class="admin-label">State</label>
                <input wire:model.defer="state" id="state" type="text" class="admin-control" />
                <p class="admin-help">This stays available for completeness even though the current landlord flow centers on Ondo State LGAs.</p>
                @error('state') <p class="admin-error">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label for="addressText" class="admin-label">Additional address details</label>
                <input wire:model.defer="addressText" id="addressText" type="text" class="admin-control" />
                <p class="admin-help">Optional extra address text for review accuracy and internal coordination.</p>
                @error('addressText') <p class="admin-error">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label for="youtubeUrl" class="admin-label">YouTube video URL</label>
                <input wire:model.defer="youtubeUrl" id="youtubeUrl" type="url" class="admin-control" />
                <p class="admin-help">Optional. Add a video tour only if it genuinely helps explain the property better.</p>
                @error('youtubeUrl') <p class="admin-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div class="admin-subsurface p-5 space-y-4">
        <div>
            <h3 class="text-base font-semibold text-slate-900">Media and review uploads</h3>
            <p class="admin-help">Images help the listing read clearly. Private documents help the later review file stay complete.</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="space-y-4">
                <div>
                    <h4 class="text-sm font-semibold text-slate-900">Property images</h4>
                    <p class="admin-help">
                        Images are stored for landlord-side preview now. The first uploaded image becomes the cover image. You can upload up to 10 images at a time.
                    </p>
                </div>

                <div>
                    <input wire:model="images" type="file" multiple class="admin-control file:mr-4 file:border-0 file:bg-transparent file:px-0 file:py-0 file:text-sm file:font-medium" />
                    <div wire:loading wire:target="images" class="admin-help">Uploading images...</div>
                    @error('images') <p class="admin-error">{{ $message }}</p> @enderror
                    @error('images.*') <p class="admin-error">{{ $message }}</p> @enderror
                </div>

                @if ($images)
                    <div class="space-y-2">
                        @foreach ($images as $index => $image)
                            <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600">
                                <span class="truncate">{{ $image->getClientOriginalName() }}</span>
                                <button wire:click="removeSelectedImage({{ $index }})" type="button" class="admin-action-link shrink-0">
                                    Remove
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="space-y-4">
                <div>
                    <h4 class="text-sm font-semibold text-slate-900">Property documents</h4>
                    <p class="admin-help">
                        Sensitive property documents are stored privately and are ready for later admin review. Add one document batch at a time so every selected file is saved under the intended document type.
                    </p>
                </div>

                <div>
                    <label for="propertyDocumentType" class="admin-label">Document type</label>
                    <select wire:model.defer="propertyDocumentType" id="propertyDocumentType" class="admin-control admin-control-select">
                        @foreach (LandlordOptions::propertyDocumentTypes() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="admin-help">All files in the current batch will save under this selected document type.</p>
                    @error('propertyDocumentType') <p class="admin-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <input wire:model="documents" type="file" multiple class="admin-control file:mr-4 file:border-0 file:bg-transparent file:px-0 file:py-0 file:text-sm file:font-medium" />
                    <div wire:loading wire:target="documents" class="admin-help">Uploading documents...</div>
                    @error('documents') <p class="admin-error">{{ $message }}</p> @enderror
                    @error('documents.*') <p class="admin-error">{{ $message }}</p> @enderror
                </div>

                @if ($documents)
                    <div class="space-y-2">
                        @foreach ($documents as $index => $document)
                            <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600">
                                <span class="truncate">{{ $document->getClientOriginalName() }}</span>
                                <button wire:click="removeSelectedDocument({{ $index }})" type="button" class="admin-action-link shrink-0">
                                    Remove
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="flex items-center justify-between gap-3 rounded-lg border border-dashed border-slate-300 px-4 py-3">
                    <p class="text-sm text-slate-600">Add the current files as one typed batch before save, or leave them and they will be added automatically on submit.</p>
                    <button wire:click="addDocumentBatch" type="button" class="admin-button admin-button-secondary">
                        Add Document Batch
                    </button>
                </div>

                @if ($documentBatches)
                    <div class="space-y-3">
                        @foreach ($documentBatches as $batchIndex => $batch)
                            <div class="rounded-lg border border-slate-200 p-4">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <span class="admin-badge admin-badge-info">{{ LandlordOptions::propertyDocumentTypes()[$batch['type']] ?? str($batch['type'])->headline() }}</span>
                                        <span class="text-sm text-slate-500">{{ count($batch['files']) }} file(s)</span>
                                    </div>
                                    <button wire:click="removeDocumentBatch({{ $batchIndex }})" type="button" class="admin-action-link">
                                        Remove Batch
                                    </button>
                                </div>

                                <div class="space-y-2">
                                    @foreach ($batch['files'] as $fileIndex => $document)
                                        <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600">
                                            <span class="truncate">{{ $document->getClientOriginalName() }}</span>
                                            <button wire:click="removeDocumentFromBatch({{ $batchIndex }}, {{ $fileIndex }})" type="button" class="admin-action-link shrink-0">
                                                Remove
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
