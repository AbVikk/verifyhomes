<?php

namespace App\Livewire\Landlord\Occupancy;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\Occupancy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    use InteractsWithAuthenticatedUser;
    use InteractsWithRoleShells;

    public function render(): View
    {
        $occupanciesAvailable = Schema::hasTable('occupancies');

        $occupancies = $occupanciesAvailable
            ? Occupancy::query()
                ->whereHas('property', fn ($query) => $query->where('landlord_id', $this->currentUserId()))
                ->with([
                    'property.coverImage',
                    'tenant',
                ])
                ->latest('started_at')
                ->get()
            : new Collection();

        $occupanciesByProperty = $occupanciesAvailable
            ? $occupancies->groupBy('property_id')
            : collect();

        return view('livewire.landlord.occupancy.index', [
            'occupanciesAvailable' => $occupanciesAvailable,
            'occupanciesByProperty' => $occupanciesByProperty,
        ])->layout('layouts.dashboard-shell', $this->landlordShell('Occupants'));
    }
}
