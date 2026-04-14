<?php

namespace App\Livewire\Tenant\SavedListings;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\InspectionRequest;
use App\Models\PaymentTransaction;
use App\Support\Currency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    use InteractsWithAuthenticatedUser;
    use InteractsWithRoleShells;

    public function removeSavedProperty(int $propertyId): void
    {
        if (! $this->savedPropertiesAvailable()) {
            session()->flash('status', 'Saved listings are not available yet in this environment.');

            return;
        }

        $removed = $this->currentUser()->savedProperties()->detach($propertyId);

        session()->flash(
            'status',
            $removed > 0
                ? 'Listing removed from your saved properties.'
                : 'That listing is no longer in your saved properties.',
        );
    }

    public function render(): View
    {
        $savedPropertiesAvailable = $this->savedPropertiesAvailable();
        $inspectionRequestsAvailable = Schema::hasTable('inspection_requests');
        $paymentTransactionsAvailable = Schema::hasTable('payment_transactions');
        $savedProperties = $savedPropertiesAvailable
            ? $this->currentUser()
                ->savedProperties()
                ->with(['coverImage'])
                ->orderByPivot('created_at', 'desc')
                ->get()
            : new Collection();

        $propertyIds = $savedProperties->pluck('id');

        $openInspectionRequestsByProperty = $inspectionRequestsAvailable && $propertyIds->isNotEmpty()
            ? InspectionRequest::query()
                ->forTenant($this->currentUserId())
                ->open()
                ->whereIn('property_id', $propertyIds)
                ->latest('created_at')
                ->get()
                ->groupBy('property_id')
                ->map(fn ($requests) => $requests->first())
            : collect();

        $latestPaidTransactionsByProperty = $paymentTransactionsAvailable && $propertyIds->isNotEmpty()
            ? PaymentTransaction::query()
                ->where('payer_id', $this->currentUserId())
                ->whereIn('property_id', $propertyIds)
                ->where('status', 'paid')
                ->latest('created_at')
                ->get()
                ->groupBy('property_id')
                ->map(fn ($transactions) => $transactions->first())
            : collect();

        $currentlyAvailableCount = $savedProperties->filter(fn ($property) => $property->isPubliclyVisible())->count();

        return view('livewire.tenant.saved-listings.index', [
            'savedPropertiesAvailable' => $savedPropertiesAvailable,
            'savedProperties' => $savedProperties,
            'openInspectionRequestsByProperty' => $openInspectionRequestsByProperty,
            'latestPaidTransactionsByProperty' => $latestPaidTransactionsByProperty,
            'currentlyAvailableCount' => $currentlyAvailableCount,
        ])->layout('layouts.dashboard-shell', $this->tenantShell('Saved Listings'));
    }

    public function formatMoney(float|int|string|null $amount): string
    {
        return Currency::format($amount);
    }

    protected function savedPropertiesAvailable(): bool
    {
        return Schema::hasTable('saved_properties');
    }
}
