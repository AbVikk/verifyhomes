<?php

namespace App\Livewire\Landlord\InspectionRequests;

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
            ? InspectionRequest::query()->forLandlord($this->currentUserId())
            : null;

        $openInspectionRequestCount = $inspectionRequestsAvailable
            ? (clone $baseQuery)->open()->count()
            : 0;
        $requestedInspectionRequestCount = $inspectionRequestsAvailable
            ? (clone $baseQuery)->where('status', InspectionRequestOptions::STATUS_REQUESTED)->count()
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

        $inspectionRequests = $inspectionRequestsAvailable
            ? InspectionRequest::query()
                ->forLandlord($this->currentUserId())
                ->with(['property'])
                ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
                ->orderedForCoordination()
                ->paginate(10)
            : $this->emptyPaginator();

        return view('livewire.landlord.inspection-requests.index', [
            'inspectionRequests' => $inspectionRequests,
            'inspectionRequestsAvailable' => $inspectionRequestsAvailable,
            'openInspectionRequestCount' => $openInspectionRequestCount,
            'requestedInspectionRequestCount' => $requestedInspectionRequestCount,
            'scheduledInspectionRequestCount' => $scheduledInspectionRequestCount,
            'closedInspectionRequestCount' => $closedInspectionRequestCount,
            'statuses' => InspectionRequestOptions::statuses(),
        ])->layout('layouts.dashboard-shell', $this->landlordShell('Inspection Requests'));
    }

    public function nextStepSummary(InspectionRequest $inspectionRequest): string
    {
        return match ($inspectionRequest->status) {
            InspectionRequestOptions::STATUS_REQUESTED => 'Add any access or readiness note admin needs.',
            InspectionRequestOptions::STATUS_SCHEDULED => 'Visit booked. Keep access ready.',
            InspectionRequestOptions::STATUS_COMPLETED => 'Review the outcome and follow-up note.',
            InspectionRequestOptions::STATUS_CANCELLED, InspectionRequestOptions::STATUS_REJECTED => 'This request is closed.',
            default => 'Check the latest admin update.',
        };
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
