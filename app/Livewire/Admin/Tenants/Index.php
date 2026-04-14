<?php

namespace App\Livewire\Admin\Tenants;

use App\Livewire\Admin\Concerns\HasAdminLayout;
use App\Models\InspectionRequest;
use App\Models\TenantProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use HasAdminLayout;
    use WithPagination;

    #[Url]
    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $tenantProfilesAvailable = $this->hasTenantProfilesTable();
        $inspectionRequestsAvailable = $this->hasInspectionRequestsTable();

        $tenantProfiles = $tenantProfilesAvailable
            ? $this->filteredTenantProfilesQuery()
                ->with('user')
                ->paginate(10)
            : $this->emptyPaginator();

        if ($tenantProfilesAvailable) {
            $this->attachInspectionCounts($tenantProfiles, $inspectionRequestsAvailable);
        }

        return $this->adminPage(view('livewire.admin.tenants.index', [
            'tenantProfiles' => $tenantProfiles,
            'tenantProfilesAvailable' => $tenantProfilesAvailable,
            'inspectionRequestsAvailable' => $inspectionRequestsAvailable,
        ]), 'Tenants');
    }

    protected function filteredTenantProfilesQuery(): Builder
    {
        return TenantProfile::query()
            ->when($this->search !== '', function ($query): void {
                $searchTerm = '%'.$this->search.'%';

                $query->whereHas('user', function ($query) use ($searchTerm): void {
                    $query->where('name', 'like', $searchTerm)
                        ->orWhere('email', 'like', $searchTerm);
                });
            })
            ->latest('created_at');
    }

    protected function attachInspectionCounts(LengthAwarePaginator $tenantProfiles, bool $inspectionRequestsAvailable): void
    {
        $profiles = $tenantProfiles->getCollection();

        if (! $inspectionRequestsAvailable || $profiles->isEmpty()) {
            $profiles->each(fn (TenantProfile $tenantProfile) => $tenantProfile->setAttribute('inspection_requests_count', 0));

            return;
        }

        $counts = InspectionRequest::query()
            ->selectRaw('tenant_id, COUNT(*) as aggregate')
            ->whereIn('tenant_id', $profiles->pluck('user_id'))
            ->groupBy('tenant_id')
            ->pluck('aggregate', 'tenant_id');

        $profiles->each(function (TenantProfile $tenantProfile) use ($counts): void {
            $tenantProfile->setAttribute('inspection_requests_count', (int) ($counts[$tenantProfile->user_id] ?? 0));
        });
    }

    protected function hasTenantProfilesTable(): bool
    {
        return Schema::hasTable('tenant_profiles');
    }

    protected function hasInspectionRequestsTable(): bool
    {
        return Schema::hasTable('inspection_requests');
    }

    protected function emptyPaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            items: new Collection(),
            total: 0,
            perPage: 10,
            currentPage: 1,
            options: [
                'path' => request()->url(),
                'pageName' => 'page',
            ],
        );
    }
}
