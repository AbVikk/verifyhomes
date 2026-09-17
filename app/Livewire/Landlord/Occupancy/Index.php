<?php

namespace App\Livewire\Landlord\Occupancy;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\Occupancy;
use App\Models\User;
use App\Support\MoveInConditionReportService;
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
        $agreementsAvailable = Schema::hasTable('tenancy_agreements');
        $moveInReportsAvailable = Schema::hasTable('move_in_condition_reports');
        $tenantId = $this->tenant !== '' ? (int) $this->tenant : null;
        $tenantProfile = $tenantId && $occupanciesAvailable
            ? User::query()->whereKey($tenantId)->whereHas('occupancies.property', fn ($query) => $query->where('landlord_id', $this->currentUserId()))->first()
            : null;

        $occupancies = $occupanciesAvailable
            ? Occupancy::query()
                ->whereHas('property', fn ($query) => $query->where('landlord_id', $this->currentUserId()))
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->with([
                    'property.coverImage',
                    'tenant',
                    'tenancyAgreement',
                    'moveInConditionReport',
                ])
                ->withCount(['maintenanceRequests as maintenance_open_count' => fn ($query) => $query->where('status', '!=', 'closed')])
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
            'agreementsAvailable' => $agreementsAvailable,
            'moveInReportsAvailable' => $moveInReportsAvailable,
        ])->layout('layouts.dashboard-shell', $this->landlordShell('Occupants'));
    }

    public function startMoveInReport(int $occupancyId): void
    {
        abort_unless(Schema::hasTable('move_in_condition_reports'), 404);
        $occupancy = Occupancy::query()->whereHas('property', fn ($query) => $query->where('landlord_id', $this->currentUserId()))->with(['property', 'tenancyAgreement'])->findOrFail($occupancyId);
        app(MoveInConditionReportService::class)->createForOccupancy($occupancy);
        $this->redirect(route('landlord.move-in-reports.edit', $occupancy));
    }
}
