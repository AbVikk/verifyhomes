<?php

namespace App\Livewire\Landlord\Tenants;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\Occupancy;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    use InteractsWithAuthenticatedUser, InteractsWithRoleShells;

    public User $tenant;

    public function mount(User $tenant): void
    {
        abort_unless(Schema::hasTable('occupancies') && Occupancy::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereHas('property', fn ($query) => $query->where('landlord_id', $this->currentUserId()))
            ->exists(), 404);

        $this->tenant = $tenant;
    }

    public function render(): View
    {
        $occupancies = Occupancy::query()
            ->where('tenant_id', $this->tenant->getKey())
            ->whereHas('property', fn ($query) => $query->where('landlord_id', $this->currentUserId()))
            ->with('property')
            ->latest('started_at')
            ->get();

        return view('livewire.landlord.tenants.show', [
            'tenant' => $this->tenant->loadMissing('tenantProfile'),
            'occupancies' => $occupancies,
        ])->layout('layouts.dashboard-shell', $this->landlordShell('Tenant Profile', [
            'pageActionLabel' => 'Back to occupants',
            'pageActionHref' => route('landlord.occupancy.index'),
        ]));
    }
}
