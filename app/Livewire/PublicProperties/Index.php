<?php

namespace App\Livewire\PublicProperties;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\InspectionRequest;
use App\Models\Property;
use App\Support\Currency;
use App\Support\LandlordOptions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use InteractsWithAuthenticatedUser;
    use InteractsWithRoleShells;
    use WithPagination;

    public string $listingIntent = '';

    public string $search = '';

    public string $propertyType = '';

    public ?string $minPrice = null;

    public ?string $maxPrice = null;

    public string $sort = 'newest';

    public bool $savedPropertiesAvailable = false;

    public bool $inspectionRequestsAvailable = false;

    public function mount(): void
    {
        $this->savedPropertiesAvailable = Schema::hasTable('saved_properties');
        $this->inspectionRequestsAvailable = Schema::hasTable('inspection_requests');
    }

    public function setListingIntent(string $listingIntent = ''): void
    {
        $allowedIntents = array_merge([''], LandlordOptions::listingIntentValues());

        if (! in_array($listingIntent, $allowedIntents, true)) {
            return;
        }

        $this->listingIntent = $listingIntent;
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPropertyType(): void
    {
        $this->resetPage();
    }

    public function updatingMinPrice(): void
    {
        $this->resetPage();
    }

    public function updatingMaxPrice(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function toggleSavedProperty(int $propertyId): void
    {
        abort_unless(Auth::check() && $this->currentUser()->isTenant(), 403);

        if (! $this->savedPropertiesAvailable) {
            session()->flash('status', 'Saved listings are not available yet in this environment.');

            return;
        }

        $property = Property::query()->publiclyVisible()->findOrFail($propertyId);
        $savedProperties = $this->currentUser()->savedProperties();
        $isAlreadySaved = $savedProperties->whereKey($property->getKey())->exists();

        if ($isAlreadySaved) {
            $savedProperties->detach($property->getKey());
            session()->flash('status', 'Listing removed from your saved properties.');

            return;
        }

        $savedProperties->syncWithoutDetaching([$property->getKey()]);
        session()->flash('status', 'Listing saved successfully.');
    }

    public function render(): View
    {
        $properties = Property::query()
            ->publiclyVisible()
            ->with(['coverImage'])
            ->when(filled($this->listingIntent), fn ($query) => $query->where('listing_intent', $this->listingIntent))
            ->when(filled($this->search), function ($query): void {
                $search = trim($this->search);

                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('area', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('landmark', 'like', "%{$search}%");
                });
            })
            ->when(filled($this->propertyType), fn ($query) => $query->where('property_type', $this->propertyType))
            ->when(filled($this->minPrice), fn ($query) => $query->where('rent_amount', '>=', (float) $this->minPrice))
            ->when(filled($this->maxPrice), fn ($query) => $query->where('rent_amount', '<=', (float) $this->maxPrice))
            ->when($this->sort === 'lowest_price', fn ($query) => $query->orderBy('rent_amount'))
            ->when($this->sort === 'highest_price', fn ($query) => $query->orderByDesc('rent_amount'))
            ->when($this->sort === 'newest', fn ($query) => $query->latest())
            ->paginate(12);

        $intentTabs = $this->listingIntentTabs();
        $listingIntentHelpText = $this->listingIntentHelpText();

        if (Auth::check() && $this->currentUser()->isTenant()) {
            $savedPropertyIds = $this->savedPropertiesAvailable
                ? $this->currentUser()->savedProperties()->pluck('properties.id')->map(fn ($id) => (int) $id)->all()
                : [];

            $openInspectionRequestsByProperty = $this->inspectionRequestsAvailable
                ? InspectionRequest::query()
                    ->forTenant($this->currentUserId())
                    ->open()
                    ->whereIn('property_id', $properties->pluck('id'))
                    ->latest('created_at')
                    ->get()
                    ->groupBy('property_id')
                    ->map(fn ($requests) => $requests->first())
                : collect();

            return view('livewire.public-properties.index', [
                'properties' => $properties,
                'propertyTypes' => LandlordOptions::propertyTypes(),
                'savedPropertyIds' => $savedPropertyIds,
                'openInspectionRequestsByProperty' => $openInspectionRequestsByProperty,
                'savedListingsCount' => $this->savedPropertiesAvailable ? count($savedPropertyIds) : 0,
                'intentTabs' => $intentTabs,
                'listingIntentHelpText' => $listingIntentHelpText,
            ])->layout('layouts.dashboard-shell', $this->tenantShell('Browse Properties'));
        }

        if (Auth::check() && $this->currentUser()->isLandlord()) {
            return view('livewire.public-properties.index-workspace', [
                'properties' => $properties,
                'propertyTypes' => LandlordOptions::propertyTypes(),
                'intentTabs' => $intentTabs,
                'listingIntentHelpText' => $listingIntentHelpText,
            ])->layout('layouts.dashboard-shell', $this->landlordShell('Browse Properties'));
        }

        if (Auth::check() && $this->currentUser()->hasAnyRole(['admin', 'staff'])) {
            return view('livewire.public-properties.index-workspace', [
                'properties' => $properties,
                'propertyTypes' => LandlordOptions::propertyTypes(),
                'intentTabs' => $intentTabs,
                'listingIntentHelpText' => $listingIntentHelpText,
            ])->layout('components.admin-layout', [
                'pageHeading' => 'Browse Properties',
            ]);
        }

        return view('livewire.public-properties.index-public', [
            'properties' => $properties,
            'propertyTypes' => LandlordOptions::propertyTypes(),
            'intentTabs' => $intentTabs,
            'listingIntentHelpText' => $listingIntentHelpText,
        ])->layout('layouts.app');
    }

    public function formatMoney(float|int|string|null $amount): string
    {
        return Currency::format($amount);
    }

    public function minPriceLabel(): string
    {
        return match ($this->listingIntent) {
            'for_sale' => 'Min sale price',
            'for_lease' => 'Min lease amount',
            'for_rent' => 'Min rent',
            default => 'Min price',
        };
    }

    public function maxPriceLabel(): string
    {
        return match ($this->listingIntent) {
            'for_sale' => 'Max sale price',
            'for_lease' => 'Max lease amount',
            'for_rent' => 'Max rent',
            default => 'Max price',
        };
    }

    public function browseResultsCopy(): string
    {
        return match ($this->listingIntent) {
            'for_sale' => 'Discover approved, verified sale listings that are ready for property review and inspection requests.',
            'for_lease' => 'Discover approved, verified lease listings that are ready for property review and inspection requests.',
            'for_rent' => 'Discover approved, verified rental listings that are ready for tenant inspection requests.',
            default => 'Discover approved, verified listings and use the listing-intent tabs to narrow the results quickly.',
        };
    }

    public function listingIntentTabs(): array
    {
        return [
            ['value' => '', 'label' => 'All'],
            ['value' => 'for_rent', 'label' => 'For Rent'],
            ['value' => 'for_sale', 'label' => 'For Sale'],
            ['value' => 'for_lease', 'label' => 'For Lease'],
        ];
    }

    public function listingIntentHelpText(): string
    {
        return match ($this->listingIntent) {
            'for_sale' => 'Sale listings use sale-price wording in the filters and listing cards so the asking price is easier to read correctly.',
            'for_lease' => 'For Lease stays visible because the current listing-intent values already support lease listings in the workspace.',
            'for_rent' => 'Rental listings keep rent-specific wording in the filters so the amount meaning stays clear.',
            default => 'Use the tabs as the main quick filter, then tighten the results further with search, type, and price. The price labels adapt to the selected listing intent.',
        };
    }
}
