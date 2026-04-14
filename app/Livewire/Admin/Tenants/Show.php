<?php

namespace App\Livewire\Admin\Tenants;

use App\Livewire\Admin\Concerns\HasAdminLayout;
use App\Models\InspectionRequest;
use App\Models\TenantProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class Show extends Component
{
    use HasAdminLayout;

    public ?TenantProfile $tenantProfile = null;

    public function mount(?string $tenantProfileId = null): void
    {
        if (! $this->hasTenantProfilesTable()) {
            return;
        }

        $tenantProfile = TenantProfile::query()
            ->with('user')
            ->find($tenantProfileId);

        abort_if(! $tenantProfile, 404);

        $this->tenantProfile = $tenantProfile;
    }

    public function render(): View
    {
        $tenantProfilesAvailable = $this->hasTenantProfilesTable();
        $inspectionRequestsAvailable = $this->hasInspectionRequestsTable();

        $inspectionRequests = new Collection();
        $inspectionRequestCount = 0;

        if ($tenantProfilesAvailable && $inspectionRequestsAvailable && $this->tenantProfile?->user_id) {
            $inspectionRequests = InspectionRequest::query()
                ->with('property')
                ->where('tenant_id', $this->tenantProfile->user_id)
                ->orderedForCoordination()
                ->take(5)
                ->get();

            $inspectionRequestCount = InspectionRequest::query()
                ->where('tenant_id', $this->tenantProfile->user_id)
                ->count();
        }

        return $this->adminPage(view('livewire.admin.tenants.show', [
            'tenantProfile' => $this->tenantProfile?->loadMissing('user'),
            'tenantProfilesAvailable' => $tenantProfilesAvailable,
            'inspectionRequestsAvailable' => $inspectionRequestsAvailable,
            'inspectionRequests' => $inspectionRequests,
            'inspectionRequestCount' => $inspectionRequestCount,
        ]), 'Tenant Detail', 'Back to Tenants', route('admin.tenants.index'));
    }

    protected function hasTenantProfilesTable(): bool
    {
        return Schema::hasTable('tenant_profiles');
    }

    protected function hasInspectionRequestsTable(): bool
    {
        return Schema::hasTable('inspection_requests');
    }
}
