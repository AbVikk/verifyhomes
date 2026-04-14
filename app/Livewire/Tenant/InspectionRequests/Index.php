<?php

namespace App\Livewire\Tenant\InspectionRequests;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\InspectionRequest;
use App\Support\InspectionRequestOptions;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use InteractsWithAuthenticatedUser;
    use InteractsWithRoleShells;
    use WithPagination;

    public string $statusFilter = 'all';

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $inspectionRequestsAvailable = $this->hasInspectionRequestsTable();
        $baseQuery = $inspectionRequestsAvailable
            ? InspectionRequest::query()->forTenant($this->currentUserId())
            : null;

        $openInspectionRequestCount = $inspectionRequestsAvailable
            ? (clone $baseQuery)->open()->count()
            : 0;
        $scheduledInspectionRequestCount = $inspectionRequestsAvailable
            ? (clone $baseQuery)->where('status', InspectionRequestOptions::STATUS_SCHEDULED)->count()
            : 0;
        $closedInspectionRequestCount = $inspectionRequestsAvailable
            ? (clone $baseQuery)->whereIn('status', [
                InspectionRequestOptions::STATUS_COMPLETED,
                InspectionRequestOptions::STATUS_CANCELLED,
                InspectionRequestOptions::STATUS_REJECTED,
            ])->count()
            : 0;
        $upcomingInspectionRequest = $inspectionRequestsAvailable
            ? (clone $baseQuery)
                ->with('property')
                ->where('status', InspectionRequestOptions::STATUS_SCHEDULED)
                ->whereNotNull('scheduled_at')
                ->orderBy('scheduled_at')
                ->first()
            : null;

        $inspectionRequests = $inspectionRequestsAvailable
            ? InspectionRequest::query()
                ->forTenant($this->currentUserId())
                ->with(['property'])
                ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
                ->orderedForCoordination()
                ->paginate(10)
            : $this->emptyPaginator();

        return view('livewire.tenant.inspection-requests.index', [
            'inspectionRequests' => $inspectionRequests,
            'inspectionRequestsAvailable' => $inspectionRequestsAvailable,
            'openInspectionRequestCount' => $openInspectionRequestCount,
            'scheduledInspectionRequestCount' => $scheduledInspectionRequestCount,
            'closedInspectionRequestCount' => $closedInspectionRequestCount,
            'upcomingInspectionRequest' => $upcomingInspectionRequest,
            'statuses' => InspectionRequestOptions::statuses(),
            'outcomes' => InspectionRequestOptions::outcomes(),
        ])->layout('layouts.dashboard-shell', $this->tenantShell('Inspection Requests'));
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
