<?php

namespace App\Livewire\Admin\InspectionRequests;

use App\Livewire\Admin\Concerns\HasAdminLayout;
use App\Models\InspectionRequest;
use App\Models\InspectionRequestStatusHistory;
use App\Support\AuditLogger;
use App\Support\InspectionRequestOptions;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    #[Url(except: 'all')]
    public string $statusFilter = 'all';

    public array $selectedInspectionRequestIds = [];

    public bool $selectPage = false;

    public function updatingSearch(): void
    {
        $this->resetPageAndSelection();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPageAndSelection();
    }

    public function updatedPage(): void
    {
        $this->clearSelection();
    }

    public function updatedSelectPage(bool $value): void
    {
        if (! $value) {
            $this->selectedInspectionRequestIds = [];

            return;
        }

        $this->selectedInspectionRequestIds = $this->currentPageInspectionRequestIds();
    }

    public function updatedSelectedInspectionRequestIds(): void
    {
        $this->selectedInspectionRequestIds = collect($this->selectedInspectionRequestIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $currentPageIds = $this->currentPageInspectionRequestIds();

        $this->selectPage = $currentPageIds !== []
            && count(array_intersect($currentPageIds, $this->selectedInspectionRequestIds)) === count($currentPageIds);
    }

    public function clearSelection(): void
    {
        $this->selectedInspectionRequestIds = [];
        $this->selectPage = false;
    }

    public function bulkSchedule(): void
    {
        $this->flashBulkMessage(
            'Bulk scheduling is not available here because each request still requires its own scheduled date and time.',
            'warning',
        );
    }

    public function bulkComplete(): void
    {
        $this->flashBulkMessage(
            'Bulk completion is not available here because each request still requires its own inspection outcome.',
            'warning',
        );
    }

    public function bulkReject(): void
    {
        $this->runBulkStatusUpdate(
            status: InspectionRequestOptions::STATUS_REJECTED,
            successMessage: 'Selected inspection requests were rejected successfully.',
            noChangeMessage: 'Selected inspection requests already had that status.',
        );
    }

    public function bulkCancel(): void
    {
        $this->runBulkStatusUpdate(
            status: InspectionRequestOptions::STATUS_CANCELLED,
            successMessage: 'Selected inspection requests were cancelled successfully.',
            noChangeMessage: 'Selected inspection requests already had that status.',
        );
    }

    public function render(): View
    {
        $inspectionRequestsAvailable = $this->hasInspectionRequestsTable();
        $inspectionHistoryAvailable = $this->hasInspectionRequestStatusHistoriesTable();

        $inspectionRequests = $inspectionRequestsAvailable
            ? $this->filteredInspectionRequestsQuery()
                ->with(['property', 'tenant'])
                ->paginate(10)
            : $this->emptyPaginator();

        return $this->adminPage(view('livewire.admin.inspection-requests.index', [
            'inspectionRequests' => $inspectionRequests,
            'inspectionRequestsAvailable' => $inspectionRequestsAvailable,
            'inspectionHistoryAvailable' => $inspectionHistoryAvailable,
            'bulkActionsAvailable' => $inspectionRequestsAvailable && $inspectionHistoryAvailable,
            'statuses' => InspectionRequestOptions::statuses(),
        ]), 'Inspection Requests');
    }

    protected function filteredInspectionRequestsQuery(): Builder
    {
        return InspectionRequest::query()
            ->when($this->search !== '', function ($query): void {
                $searchTerm = '%'.$this->search.'%';

                $query->where(function ($query) use ($searchTerm): void {
                    $query->whereHas('property', function ($query) use ($searchTerm): void {
                        $query->where('title', 'like', $searchTerm)
                            ->orWhere('city', 'like', $searchTerm)
                            ->orWhere('area', 'like', $searchTerm);
                    })->orWhereHas('tenant', function ($query) use ($searchTerm): void {
                        $query->where('name', 'like', $searchTerm)
                            ->orWhere('email', 'like', $searchTerm);
                    });
                });
            })
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderedForCoordination();
    }

    protected function currentPageInspectionRequestIds(): array
    {
        if (! $this->hasInspectionRequestsTable()) {
            return [];
        }

        return $this->filteredInspectionRequestsQuery()
            ->forPage($this->getPage(), 10)
            ->pluck('inspection_requests.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function selectedInspectionRequestsQuery(): Builder
    {
        return $this->filteredInspectionRequestsQuery()->whereKey($this->selectedInspectionRequestIds);
    }

    protected function runBulkStatusUpdate(string $status, string $successMessage, string $noChangeMessage): void
    {
        if (! $this->canPerformBulkActions()) {
            return;
        }

        $inspectionRequests = $this->selectedInspectionRequestsQuery()->get([
            'id',
            'status',
            'scheduled_at',
            'outcome_type',
            'outcome_notes',
        ]);

        if ($inspectionRequests->isEmpty()) {
            $this->flashBulkMessage('Select at least one inspection request before running a bulk action.', 'warning');
            $this->clearSelection();

            return;
        }

        $inspectionRequestsToUpdate = $inspectionRequests
            ->filter(fn (InspectionRequest $inspectionRequest) => $inspectionRequest->status !== $status)
            ->values();

        if ($inspectionRequestsToUpdate->isEmpty()) {
            $this->flashBulkMessage($noChangeMessage, 'info');
            $this->clearSelection();

            return;
        }

        DB::transaction(function () use ($inspectionRequestsToUpdate, $status): void {
            $adminId = Auth::id();

            foreach ($inspectionRequestsToUpdate as $inspectionRequest) {
                $fromStatus = $inspectionRequest->status;

                $inspectionRequest->update([
                    'status' => $status,
                    ...$this->outcomeDataForStatus($status),
                ]);

                InspectionRequestStatusHistory::create([
                    'inspection_request_id' => $inspectionRequest->id,
                    'from_status' => $fromStatus,
                    'to_status' => $status,
                    'changed_by' => $adminId,
                    'notes' => 'Bulk action from inspection request queue.',
                ]);

                AuditLogger::log(
                    action: 'inspection_request_status_changed',
                    actor: Auth::user(),
                    target: $inspectionRequest->loadMissing('property'),
                    description: 'Bulk-updated inspection request status from '.str($fromStatus)->headline().' to '.str($status)->headline().'.',
                    metadata: [
                        'from_status' => $fromStatus,
                        'to_status' => $status,
                        'source' => 'bulk_queue',
                    ],
                );
            }
        });

        $this->flashBulkMessage($successMessage);
        $this->clearSelection();
    }

    protected function outcomeDataForStatus(string $status): array
    {
        if (! InspectionRequestOptions::requiresOutcomeType($status)) {
            return [
                'outcome_type' => null,
                'outcome_notes' => null,
            ];
        }

        return [];
    }

    protected function canPerformBulkActions(): bool
    {
        if (! $this->hasInspectionRequestsTable()) {
            $this->flashBulkMessage('Inspection request bulk actions are not available yet in this environment.', 'warning');
            $this->clearSelection();

            return false;
        }

        if (! $this->hasInspectionRequestStatusHistoriesTable()) {
            $this->flashBulkMessage('Inspection request bulk actions are unavailable until inspection history data is available in this environment.', 'warning');
            $this->clearSelection();

            return false;
        }

        return true;
    }

    protected function flashBulkMessage(string $message, string $tone = 'success'): void
    {
        session()->flash('status', $message);
        session()->flash('statusTone', $tone);
    }

    protected function resetPageAndSelection(): void
    {
        $this->resetPage();
        $this->clearSelection();
    }

    private function hasInspectionRequestsTable(): bool
    {
        return Schema::hasTable('inspection_requests');
    }

    private function hasInspectionRequestStatusHistoriesTable(): bool
    {
        return Schema::hasTable('inspection_request_status_histories');
    }

    private function emptyPaginator(): LengthAwarePaginator
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
