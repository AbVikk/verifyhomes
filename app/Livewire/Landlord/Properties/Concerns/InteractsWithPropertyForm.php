<?php

namespace App\Livewire\Landlord\Properties\Concerns;

use App\Models\Property;
use App\Models\PropertyDocument;
use App\Models\PropertyImage;
use App\Support\LandlordOptions;
use App\Support\PublicPropertyVisibility;
use App\Support\RentPricingCalculator;
use App\Support\TermsGateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

trait InteractsWithPropertyForm
{
    public bool $hasAcceptedListingTerms = false;

    public string $title = '';

    public string $listingIntent = 'for_rent';

    public string $propertyType = '';

    public ?string $landSize = null;

    public string $landSizeUnit = 'sqm';

    public string $rentAmount = '';

    public string $pricingModel = RentPricingCalculator::MODEL_TENANT_PRICE;

    public ?string $cautionFee = null;

    public ?string $serviceCharge = null;

    public string $totalUnits = '1';

    public ?string $description = null;

    public ?int $bedrooms = null;

    public ?int $bathrooms = null;

    public ?int $toilets = null;

    public string $state = 'Ondo';

    public string $lga = '';

    public string $city = 'Akure';

    public string $area = '';

    public ?string $street = null;

    public ?string $landmark = null;

    public ?string $addressText = null;

    public ?string $youtubeUrl = null;

    public array $images = [];

    public array $documents = [];

    public array $documentBatches = [];

    public string $propertyDocumentType = 'ownership_proof';

    public bool $showsBedroomField = true;

    public bool $showsBathroomField = true;

    public bool $showsToiletField = true;

    public bool $showsLandSizeFields = false;

    public string $propertyTypeHelp = 'Choose the property type first so we can guide the rest of the form more accurately.';

    public string $roomCountHelp = 'Room counts are optional, but they help explain the layout when they apply.';

    public string $landSizeHelp = 'Add the size of the land so buyers can compare parcels accurately.';

    public string $landSizeUnitHelp = 'Pick the unit that best matches how the land is measured locally.';

    public string $primaryAmountLabel = 'Rent amount';

    public string $primaryAmountPlaceholder = '900000.00';

    public string $listingIntentGuidance = 'Choose whether the listing is for rent, sale, or lease before you set the main asking amount.';

    public string $primaryAmountHelp = 'Enter numbers only. Saved listing amounts will display with the Naira sign and match the selected listing purpose.';

    public string $pricingModelHelp = 'Choose whether the entered rent is the final tenant-facing rent or the landlord target amount before the 20% platform fee.';

    public string $cautionFeeHelp = 'Optional. Add this only when a separate caution or damage deposit applies to the listing.';

    public string $serviceChargeHelp = 'Optional. Use this for recurring service or facility charges tied to the property.';

    public string $totalUnitsLabel = 'Total units';

    public string $totalUnitsHelp = 'Single-listing property types usually stay at 1 unit unless you are intentionally marketing multiple units together.';

    public bool $supportsMultiUnitInventory = false;

    protected function propertyRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'listingIntent' => ['required', 'string', 'max:50', Rule::in(LandlordOptions::listingIntentValues())],
            'propertyType' => ['required', 'string', 'max:50', Rule::in(LandlordOptions::propertyTypeValues())],
            'landSize' => [$this->propertyType === 'land' ? 'required' : 'nullable', 'numeric', 'min:0'],
            'landSizeUnit' => [$this->propertyType === 'land' ? 'required' : 'nullable', 'string', Rule::in(LandlordOptions::landSizeUnitValues())],
            'rentAmount' => ['required', 'numeric', 'min:0'],
            'pricingModel' => ['required', 'string', Rule::in(RentPricingCalculator::supportedModels())],
            'cautionFee' => ['nullable', 'numeric', 'min:0'],
            'serviceCharge' => ['nullable', 'numeric', 'min:0'],
            'totalUnits' => ['required', 'integer', 'min:1', 'max:10000'],
            'description' => ['nullable', 'string'],
            'bedrooms' => ['nullable', 'integer', 'min:0'],
            'bathrooms' => ['nullable', 'integer', 'min:0'],
            'toilets' => ['nullable', 'integer', 'min:0'],
            'state' => ['required', 'string', 'max:100'],
            'lga' => ['required', 'string', 'max:100', Rule::in(LandlordOptions::ondoLgaValues())],
            'city' => ['required', 'string', 'max:100'],
            'area' => ['required', 'string', 'max:125'],
            'street' => ['nullable', 'string', 'max:180'],
            'landmark' => ['nullable', 'string', 'max:180'],
            'addressText' => ['nullable', 'string', 'max:255'],
            'youtubeUrl' => ['nullable', 'url', 'max:255'],
            'images' => ['array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'documents' => ['array', 'max:5'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'propertyDocumentType' => ['required', 'string', 'max:50', Rule::in(LandlordOptions::propertyDocumentTypeValues())],
            'hasAcceptedListingTerms' => ['accepted'],
        ];
    }

    protected function propertyValidationMessages(): array
    {
        return [
            'listingIntent.in' => 'Select a valid listing purpose.',
            'propertyType.in' => 'Select a valid property type.',
            'landSize.required' => 'Add the land size for land listings.',
            'landSizeUnit.required' => 'Select a land size unit.',
            'lga.in' => 'Select a valid Ondo State LGA.',
            'propertyDocumentType.in' => 'Select a valid property document type.',
            'images.max' => 'You can upload up to 10 property images at a time.',
            'documents.max' => 'You can upload up to 5 property documents at a time.',
            'hasAcceptedListingTerms.accepted' => 'Accept the listing terms before saving this property.',
        ];
    }

    protected function fillPropertyForm(Property $property): void
    {
        $this->hasAcceptedListingTerms = $this->listingTermsReady($property);
        $this->title = $property->title;
        $this->listingIntent = $property->listing_intent ?: 'for_rent';
        $this->propertyType = $property->property_type;
        $this->landSize = $property->land_size !== null ? (string) $property->land_size : null;
        $this->landSizeUnit = $property->land_size_unit ?: 'sqm';
        $this->rentAmount = (string) $property->rent_amount;
        $this->pricingModel = $property->pricing_model ?: RentPricingCalculator::MODEL_TENANT_PRICE;
        $this->cautionFee = $property->caution_fee !== null ? (string) $property->caution_fee : null;
        $this->serviceCharge = $property->service_charge !== null ? (string) $property->service_charge : null;
        $this->totalUnits = (string) ($property->total_units ?: 1);
        $this->description = $property->description;
        $this->bedrooms = $property->bedrooms;
        $this->bathrooms = $property->bathrooms;
        $this->toilets = $property->toilets;
        $this->state = $property->state;
        $this->lga = $property->lga;
        $this->city = $property->city;
        $this->area = $property->area;
        $this->street = $property->street;
        $this->landmark = $property->landmark;
        $this->addressText = $property->address_text;
        $this->youtubeUrl = $property->youtube_url;

        $this->syncListingIntentContext();
        $this->syncPropertyTypeContext(false);
    }

    protected function persistProperty(Property $property): Property
    {
        $this->commitCurrentDocumentSelectionIfPresent();

        $termsGate = $this->listingTermsGate($property->exists ? $property : null);
        $validated = $this->validate($this->propertyRules(), $this->propertyValidationMessages());
        $this->ensureListingTermsGateIsReady($property);
        $publicFiles = [];
        $privateFiles = [];

        DB::beginTransaction();

        try {
            $property->fill($this->propertyPayload($property, $validated));
            $property->save();

            $this->storePropertyImages($property, $publicFiles);
            $this->storePropertyDocuments($property, $privateFiles);

            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();
            $this->cleanupStoredFiles('public', $publicFiles);
            $this->cleanupStoredFiles('local', $privateFiles);

            throw $throwable;
        }

        $this->resetUploadFields();
        $this->hasAcceptedListingTerms = false;
        app(TermsGateService::class)->clear($termsGate);

        return $property->fresh(['images', 'documents']);
    }

    public function listingIntentLabel(?string $intent = null): string
    {
        return LandlordOptions::listingIntentLabel($intent ?? $this->listingIntent);
    }

    public function listingIntentSummary(?string $intent = null): string
    {
        return match ($intent ?? $this->listingIntent) {
            'for_sale' => 'Use this when you want to advertise the full asking price for selling the property.',
            'for_lease' => 'Use this when the listing is a lease offer and you want the main amount to read as a lease amount.',
            default => 'Use this when the listing is meant for rent and the main amount should read as recurring rent.',
        };
    }

    public function readinessGaps(Property $property): array
    {
        $gaps = [];

        if (($property->images_count ?? $property->images?->count() ?? 0) === 0) {
            $gaps[] = 'Add at least one image so the listing has a cover photo.';
        }

        if (($property->documents_count ?? $property->documents?->count() ?? 0) === 0) {
            $gaps[] = 'Add at least one supporting document so the review file is not empty.';
        }

        if ($property->status === 'pending_review') {
            $gaps[] = 'This listing is still waiting for admin review.';
        }

        if ($property->status === PublicPropertyVisibility::APPROVED_STATUS && $property->is_verified && ! $property->is_published) {
            $gaps[] = 'This listing is approved but not publicly visible yet.';
        }

        if (($property->open_inspection_requests_count ?? 0) > 0) {
            $gaps[] = 'There is open inspection-request activity that still needs follow-through.';
        }

        if ((int) $property->available_units <= 0) {
            $gaps[] = 'No units are currently available in the listing inventory.';
        }

        return $gaps;
    }

    public function propertyVisibilitySummary(Property $property): string
    {
        if ($property->isPubliclyVisible()) {
            return 'Live in public discovery.';
        }

        if ($property->status === PublicPropertyVisibility::APPROVED_STATUS && $property->is_verified && ! $property->is_published) {
            return 'Approved, but not publicly visible yet.';
        }

        return 'Not publicly visible yet.';
    }

    public function propertyNextStepSummary(Property $property): string
    {
        if ((int) $property->available_units <= 0) {
            return 'This listing has no available units right now. The next successful completed rent payment should not reduce occupancy further unless inventory opens up again.';
        }

        if ($property->status === 'pending_review') {
            return 'This listing is waiting for review. Tighten any missing details or uploads before the next review pass.';
        }

        if ($property->status === PublicPropertyVisibility::APPROVED_STATUS && $property->is_verified && ! $property->is_published) {
            return 'This listing is approved but unpublished. Recheck the details so it is ready when visibility changes.';
        }

        if (($property->open_inspection_requests_count ?? 0) > 0) {
            return 'Open request activity already exists. Keep the listing details accurate and review inspection follow-through from the request queue.';
        }

        if ($property->isPubliclyVisible()) {
            return 'This listing is live. Keep the asking amount, media, and inspection follow-through current.';
        }

        return 'Review the listing details and uploads so the record stays ready for the next workflow step.';
    }

    public function unitInventorySummary(Property $property): string
    {
        $totalUnits = (int) $property->total_units;
        $occupiedUnits = (int) $property->occupied_units;
        $availableUnits = (int) $property->available_units;

        return "{$availableUnits} available of {$totalUnits} total unit".($totalUnits === 1 ? '' : 's')." with {$occupiedUnits} occupied.";
    }

    public function unitInventoryGuidance(): string
    {
        if ($this->propertyType === 'land') {
            return 'Use the plot count to match how this land listing should be treated in availability. Successful completed rent payments can reduce availability later, but inspection requests and early checkout steps do not.';
        }

        return $this->supportsMultiUnitInventory
            ? 'Use this listing to represent multiple similar units when that matches the real inventory. Successful completed rent payments can reduce this number later, but inspection requests, saves, and early checkout steps do not.'
            : 'Single-home listings usually stay at 1 unit. Successful completed rent payments can reduce availability later, but inspection requests and early checkout steps do not.';
    }

    protected function propertyPayload(Property $property, array $validated): array
    {
        $pricingBreakdown = $this->pricingBreakdown($validated);

        return [
            'title' => trim($validated['title']),
            'listing_intent' => $validated['listingIntent'],
            'pricing_model' => $pricingBreakdown['pricing_model'],
            'property_type' => $validated['propertyType'],
            'land_size' => $this->normalizeLandSize($validated['landSize'] ?? null),
            'land_size_unit' => $this->normalizeLandSizeUnit($validated['landSizeUnit'] ?? null),
            'rent_amount' => $pricingBreakdown['rent_amount'],
            'pricing_input_amount' => $pricingBreakdown['pricing_input_amount'],
            'landlord_net_amount' => $pricingBreakdown['landlord_net_amount'],
            'platform_fee_percentage' => $pricingBreakdown['platform_fee_percentage'],
            'caution_fee' => $validated['cautionFee'],
            'service_charge' => $validated['serviceCharge'],
            'total_units' => (int) $validated['totalUnits'],
            'occupied_units' => min((int) ($property->occupied_units ?? 0), (int) $validated['totalUnits']),
            'description' => $this->normalizeNullableText($validated['description']),
            'bedrooms' => $this->bedroomValueForPayload($validated['bedrooms']),
            'bathrooms' => $this->bathroomValueForPayload($validated['bathrooms']),
            'toilets' => $this->toiletValueForPayload($validated['toilets']),
            'state' => trim($validated['state']),
            'lga' => $validated['lga'],
            'city' => trim($validated['city']),
            'area' => trim($validated['area']),
            'street' => $this->normalizeNullableText($validated['street']),
            'landmark' => $this->normalizeNullableText($validated['landmark']),
            'address_text' => $this->normalizeNullableText($validated['addressText']),
            'youtube_url' => $this->normalizeNullableText($validated['youtubeUrl']),
            'status' => 'pending_review',
            'is_published' => false,
            'is_verified' => false,
        ];
    }

    protected function storePropertyImages(Property $property, array &$storedFiles): void
    {
        $startingOrder = $property->images()->count();
        $hasCover = $property->images()->where('is_cover', true)->exists();

        foreach ($this->images as $index => $image) {
            $path = $image->store("property-images/{$property->id}", 'public');
            $storedFiles[] = $path;

            PropertyImage::create([
                'property_id' => $property->id,
                'image_path' => $path,
                'sort_order' => $startingOrder + $index,
                'is_cover' => ! $hasCover && $index === 0,
            ]);
        }
    }

    protected function storePropertyDocuments(Property $property, array &$storedFiles): void
    {
        foreach ($this->documentBatches as $batch) {
            foreach ($batch['files'] as $document) {
                $path = $document->store("property-documents/{$property->id}", 'local');
                $storedFiles[] = $path;

                PropertyDocument::create([
                    'property_id' => $property->id,
                    'document_type' => $batch['type'],
                    'file_path' => $path,
                    'original_name' => $document->getClientOriginalName(),
                    'mime_type' => $document->getMimeType() ?? $document->getClientMimeType() ?? 'application/octet-stream',
                    'file_size' => $this->resolveUploadedDocumentSize($document, $path),
                    'review_status' => 'pending',
                ]);
            }
        }
    }

    protected function cleanupStoredFiles(string $disk, array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk($disk)->delete($path);
        }
    }

    protected function resetUploadFields(): void
    {
        $this->images = [];
        $this->documents = [];
        $this->documentBatches = [];
        $this->propertyDocumentType = 'ownership_proof';
    }

    public function updatedPropertyType(): void
    {
        $this->syncPropertyTypeContext();
    }

    public function updatedListingIntent(): void
    {
        $this->syncListingIntentContext();
    }

    public function removeSelectedImage(int $index): void
    {
        if (! array_key_exists($index, $this->images)) {
            return;
        }

        unset($this->images[$index]);
        $this->images = array_values($this->images);
    }

    public function removeSelectedDocument(int $index): void
    {
        if (! array_key_exists($index, $this->documents)) {
            return;
        }

        unset($this->documents[$index]);
        $this->documents = array_values($this->documents);
    }

    public function addDocumentBatch(): void
    {
        $this->appendCurrentDocumentsAsBatch();
    }

    public function removeDocumentBatch(int $batchIndex): void
    {
        if (! array_key_exists($batchIndex, $this->documentBatches)) {
            return;
        }

        unset($this->documentBatches[$batchIndex]);
        $this->documentBatches = array_values($this->documentBatches);
    }

    public function removeDocumentFromBatch(int $batchIndex, int $fileIndex): void
    {
        if (! isset($this->documentBatches[$batchIndex]['files'][$fileIndex])) {
            return;
        }

        unset($this->documentBatches[$batchIndex]['files'][$fileIndex]);
        $this->documentBatches[$batchIndex]['files'] = array_values($this->documentBatches[$batchIndex]['files']);

        if ($this->documentBatches[$batchIndex]['files'] === []) {
            $this->removeDocumentBatch($batchIndex);
        }
    }

    protected function resolveUploadedDocumentSize(mixed $document, string $path): ?int
    {
        try {
            $size = $document->getSize();

            if (is_numeric($size)) {
                return (int) $size;
            }
        } catch (Throwable) {
            // Fall back to the persisted file when temporary upload metadata is unavailable.
        }

        try {
            $size = Storage::disk('local')->size($path);

            return is_numeric($size) ? (int) $size : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function normalizeNullableText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    protected function syncPropertyTypeContext(bool $applyDefaults = true): void
    {
        $profile = LandlordOptions::propertyTypeProfile($this->propertyType);

        $this->showsBedroomField = $profile['shows_bedrooms'];
        $this->showsBathroomField = $profile['shows_bathrooms'];
        $this->showsToiletField = $profile['shows_toilets'];
        $this->supportsMultiUnitInventory = $profile['supports_multi_unit_inventory'];
        $this->showsLandSizeFields = $this->propertyType === 'land';
        $this->propertyTypeHelp = $profile['type_help'];
        $this->roomCountHelp = $profile['room_help'];
        $this->totalUnitsHelp = $profile['unit_help'];
        $this->totalUnitsLabel = $this->propertyType === 'land'
            ? 'Total plots available'
            : ($this->supportsMultiUnitInventory ? 'Total available units at listing start' : 'Total units');

        if ($applyDefaults && blank($this->totalUnits)) {
            $this->totalUnits = (string) ($profile['default_total_units'] ?? 1);
        }

        if (! $this->showsBedroomField) {
            $this->bedrooms = null;
        } elseif ($applyDefaults && $profile['default_bedrooms'] !== null && blank($this->bedrooms)) {
            $this->bedrooms = $profile['default_bedrooms'];
        }

        if (! $this->showsBathroomField) {
            $this->bathrooms = null;
        } elseif ($applyDefaults && $profile['default_bathrooms'] !== null && blank($this->bathrooms)) {
            $this->bathrooms = $profile['default_bathrooms'];
        }

        if (! $this->showsToiletField) {
            $this->toilets = null;
        } elseif ($applyDefaults && $profile['default_toilets'] !== null && blank($this->toilets)) {
            $this->toilets = $profile['default_toilets'];
        }
    }

    protected function syncListingIntentContext(): void
    {
        $this->primaryAmountLabel = LandlordOptions::listingIntentAmountLabel($this->listingIntent);
        $this->primaryAmountPlaceholder = match ($this->listingIntent) {
            'for_sale' => '15000000.00',
            'for_lease' => '2000000.00',
            default => '900000.00',
        };
        $this->listingIntentGuidance = match ($this->listingIntent) {
            'for_sale' => 'This listing will read as a sale listing, so buyers will see the main amount as the full sale price.',
            'for_lease' => 'This listing will read as a lease listing, so the main amount should describe the lease offer clearly.',
            default => 'This listing will read as a rental listing, so the main amount should describe the expected rent clearly.',
        };
        $this->primaryAmountHelp = match ($this->listingIntent) {
            'for_sale' => 'Enter the full sale price only. Saved listing amounts will display with the Naira sign and read as a sale price.',
            'for_lease' => 'Enter the lease amount only. Saved listing amounts will display with the Naira sign and read as a lease amount.',
            default => 'Enter the rent amount using the pricing model below. Saved listing amounts will display with the Naira sign and read as rent.',
        };
        $this->cautionFeeHelp = $this->listingIntent === 'for_sale'
            ? 'Usually optional for sale listings. Add it only if a separate caution or deposit still applies to the workflow.'
            : 'Optional. Add this only when a separate caution or damage deposit applies to the listing.';
        $this->serviceChargeHelp = $this->listingIntent === 'for_sale'
            ? 'Optional. Use this only if the buyer still needs to understand a recurring or attached service charge.'
            : 'Optional. Use this for recurring service or facility charges tied to the property.';
        $this->pricingModelHelp = $this->listingIntent === 'for_rent'
            ? 'Choose whether the amount you enter should stay as the tenant-facing listed rent or whether it should be treated as the landlord target amount before the 20% platform fee.'
            : 'Platform fee pricing is only applied to successful completed rent payments, so non-rent listings keep the entered amount as the primary price.';
    }

    protected function bedroomValueForPayload(?int $bedrooms): ?int
    {
        if (! $this->showsBedroomField) {
            return null;
        }

        $profile = LandlordOptions::propertyTypeProfile($this->propertyType);

        if ($profile['default_bedrooms'] !== null && blank($bedrooms)) {
            return $profile['default_bedrooms'];
        }

        return $bedrooms;
    }

    protected function bathroomValueForPayload(?int $bathrooms): ?int
    {
        if (! $this->showsBathroomField) {
            return null;
        }

        return $bathrooms;
    }

    protected function toiletValueForPayload(?int $toilets): ?int
    {
        if (! $this->showsToiletField) {
            return null;
        }

        return $toilets;
    }

    protected function normalizeLandSize(?string $value): ?float
    {
        if ($this->propertyType !== 'land') {
            return null;
        }

        if ($value === null || trim($value) === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    protected function normalizeLandSizeUnit(?string $value): ?string
    {
        if ($this->propertyType !== 'land') {
            return null;
        }

        $value = $value ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    protected function commitCurrentDocumentSelectionIfPresent(): void
    {
        if ($this->documents === []) {
            return;
        }

        $this->appendCurrentDocumentsAsBatch();
    }

    protected function appendCurrentDocumentsAsBatch(): void
    {
        $this->validate($this->currentDocumentBatchRules(), $this->propertyValidationMessages());

        $incomingCount = count($this->documents);

        if (($this->pendingDocumentCount() + $incomingCount) > 5) {
            throw ValidationException::withMessages([
                'documents' => 'You can upload up to 5 property documents at a time.',
            ]);
        }

        $this->documentBatches[] = [
            'type' => $this->propertyDocumentType,
            'files' => array_values($this->documents),
        ];

        $this->documents = [];
        $this->propertyDocumentType = 'ownership_proof';
    }

    protected function currentDocumentBatchRules(): array
    {
        return [
            'propertyDocumentType' => ['required', 'string', 'max:50', Rule::in(LandlordOptions::propertyDocumentTypeValues())],
            'documents' => ['required', 'array', 'min:1', 'max:5'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    protected function pendingDocumentCount(): int
    {
        return collect($this->documentBatches)
            ->sum(fn (array $batch) => count($batch['files'] ?? []));
    }

    protected function pricingBreakdown(array $validated): array
    {
        if (($validated['listingIntent'] ?? $this->listingIntent) !== 'for_rent') {
            $amount = round((float) $validated['rentAmount'], 2);

            return [
                'pricing_model' => RentPricingCalculator::MODEL_TENANT_PRICE,
                'pricing_input_amount' => $amount,
                'rent_amount' => $amount,
                'landlord_net_amount' => $amount,
                'platform_fee_percentage' => 0,
            ];
        }

        return RentPricingCalculator::breakdown(
            $validated['rentAmount'],
            $validated['pricingModel'] ?? $this->pricingModel,
            (float) config('payments.rent_platform_fee_percentage', 20),
        );
    }

    protected function ensureListingTermsGateIsReady(Property $property): void
    {
        if ($this->listingTermsReady($property->exists ? $property : null)) {
            return;
        }

        throw ValidationException::withMessages([
            'hasAcceptedListingTerms' => 'Please read the listing terms before continuing.',
        ]);
    }

    public function listingTermsGate(?Property $property = null): string
    {
        if ($property?->exists) {
            return 'listing-terms:property:'.$property->getKey();
        }

        return 'listing-terms:create';
    }

    public function listingTermsReady(?Property $property = null): bool
    {
        return app(TermsGateService::class)->isCompleted($this->listingTermsGate($property));
    }

    public function listingTermsSecondsRemaining(?Property $property = null): int
    {
        return app(TermsGateService::class)->secondsRemaining($this->listingTermsGate($property));
    }

    public function pricingModelOptions(): array
    {
        return [
            RentPricingCalculator::MODEL_TENANT_PRICE => 'Entered amount is the final listed rent',
            RentPricingCalculator::MODEL_LANDLORD_NET => 'Entered amount is the landlord target after the 20% fee',
        ];
    }

    public function pricingPreviewAmount(): string
    {
        $pricing = $this->pricingBreakdown([
            'listingIntent' => $this->listingIntent,
            'rentAmount' => $this->rentAmount === '' ? 0 : $this->rentAmount,
            'pricingModel' => $this->pricingModel,
        ]);

        return number_format((float) $pricing['rent_amount'], 2, '.', ',');
    }

    public function landlordNetPreviewAmount(): string
    {
        $pricing = $this->pricingBreakdown([
            'listingIntent' => $this->listingIntent,
            'rentAmount' => $this->rentAmount === '' ? 0 : $this->rentAmount,
            'pricingModel' => $this->pricingModel,
        ]);

        return number_format((float) $pricing['landlord_net_amount'], 2, '.', ',');
    }
}
