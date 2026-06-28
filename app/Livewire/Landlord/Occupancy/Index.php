<?php

namespace App\Livewire\Landlord\Occupancy;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\Occupancy;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    use InteractsWithAuthenticatedUser;
    use InteractsWithRoleShells;

    #[Url(except: '')]
    public string $tenant = '';

    public function render(): View
    {
        $occupanciesAvailable = Schema::hasTable('occupancies');
        $tenantId = $this->tenant !== '' ? (int) $this->tenant : null;
        $tenantProfile = $tenantId
            ? User::query()->whereKey($tenantId)->first()
            : null;

        $occupancies = $occupanciesAvailable
            ? Occupancy::query()
                ->whereHas('property', fn ($query) => $query->where('landlord_id', $this->currentUserId()))
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
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
            'tenantProfile' => $tenantProfile,
        ])->layout('layouts.dashboard-shell', $this->landlordShell('Occupants'));
    }
}
