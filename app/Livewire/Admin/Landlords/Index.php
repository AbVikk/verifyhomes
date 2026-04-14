<?php

namespace App\Livewire\Admin\Landlords;

use App\Livewire\Admin\Concerns\HasAdminLayout;
use App\Models\LandlordProfile;
use App\Models\LandlordStatusHistory;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use HasAdminLayout;
    use WithPagination;

    #[Url]     public string $search = '';

    #[Url(except: 'all')]
    public string $statusFilter = 'all';

    public array $selectedLandlordIds = [];

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
            $this->selectedLandlordIds = [];

            return;
        }

        $this->selectedLandlordIds = $this->currentPageLandlordIds();
    }

    public function updatedSelectedLandlordIds(): void
    {
        $this->selectedLandlordIds = collect($this->selectedLandlordIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $currentPageIds = $this->currentPageLandlordIds();

        $this->selectPage = $currentPageIds !== []
            && count(array_intersect($currentPageIds, $this->selectedLandlordIds)) === count($currentPageIds);
    }

    public function clearSelection(): void
    {
        $this->selectedLandlordIds = [];
        $this->selectPage = false;
    }

    public function bulkApprove(): void
    {
        $this->runBulkStatusUpdate(
            status: 'approved',
            successMessage: 'Selected landlords were approved successfully.',
            noChangeMessage: 'Selected landlords already had that verification status.',
        );
    }

    public function bulkReject(): void
    {
        $this->runBulkStatusUpdate(
            status: 'rejected',
            successMessage: 'Selected landlords were rejected successfully.',
            noChangeMessage: 'Selected landlords already had that verification status.',
        );
    }

    public function bulkMarkUnderReview(): void
    {
        $this->runBulkStatusUpdate(
            status: 'under_review',
            successMessage: 'Selected landlords were moved to under review successfully.',
            noChangeMessage: 'Selected landlords already had that verification status.',
        );
    }

    public function render(): View
    {
        $landlords = $this->filteredLandlordsQuery()
            ->with(['user'])
            ->withCount('documents')
            ->paginate(10);

        return $this->adminPage(view('livewire.admin.landlords.index', [
            'landlords' => $landlords,
        ]), 'Landlord Reviews');
    }

    protected function filteredLandlordsQuery(): Builder
    {
        $query = LandlordProfile::query();

        $query->when($this->search !== '', function (Builder $query): void {
                $searchTerm = '%'.$this->search.'%';

                $query->where(function (Builder $query) use ($searchTerm): void {
                    $query->where('business_name', 'like', $searchTerm)
                        ->orWhereHas('user', function (Builder $query) use ($searchTerm): void {
                            $query->where('name', 'like', $searchTerm)
                                ->orWhere('email', 'like', $searchTerm)
                                ->orWhere('phone', 'like', $searchTerm);
                        });
                });
            })
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('verification_status', $this->statusFilter))
            ->orderByRaw("CASE verification_status WHEN 'pending' THEN 0 WHEN 'under_review' THEN 1 WHEN 'rejected' THEN 2 WHEN 'suspended' THEN 3 WHEN 'approved' THEN 4 ELSE 5 END")
            ->latest('updated_at');

        return $query;
    }

    protected function currentPageLandlordIds(): array
    {
        return $this->filteredLandlordsQuery()
            ->forPage($this->getPage(), 10)
            ->pluck('landlord_profiles.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function selectedLandlordsQuery(): Builder
    {
        return $this->filteredLandlordsQuery()->whereKey($this->selectedLandlordIds);
    }

    protected function runBulkStatusUpdate(string $status, string $successMessage, string $noChangeMessage): void
    {
        if (! $this->hasLandlordStatusHistoriesTable()) {
            $this->flashBulkMessage('Landlord bulk actions are unavailable until landlord history data is available in this environment.', 'warning');
            $this->clearSelection();

            return;
        }

        $landlords = $this->selectedLandlordsQuery()->get([
            'id',
            'verification_status',
            'verified_at',
            'verified_by',
        ]);

        if ($landlords->isEmpty()) {
            $this->flashBulkMessage('Select at least one landlord before running a bulk action.', 'warning');
            $this->clearSelection();

            return;
        }

        $landlordsToUpdate = $landlords
            ->filter(fn (LandlordProfile $landlord) => $landlord->verification_status !== $status)
            ->values();

        if ($landlordsToUpdate->isEmpty()) {
            $this->flashBulkMessage($noChangeMessage, 'info');
            $this->clearSelection();

            return;
        }

        DB::transaction(function () use ($landlordsToUpdate, $status): void {
            $timestamp = now();
            $adminId = Auth::id();
            $approved = $status === 'approved';

            foreach ($landlordsToUpdate as $landlord) {
                $fromStatus = $landlord->verification_status;

                $landlord->update([
                    'verification_status' => $status,
                    'verified_at' => $approved ? $timestamp : null,
                    'verified_by' => $approved ? $adminId : null,
                ]);

                LandlordStatusHistory::create([
                    'landlord_profile_id' => $landlord->id,
                    'from_status' => $fromStatus,
                    'to_status' => $status,
                    'changed_by' => $adminId,
                    'notes' => 'Bulk action from landlord review queue.',
                ]);

                AuditLogger::log(
                    action: 'landlord_status_changed',
                    actor: Auth::user(),
                    target: $landlord->loadMissing('user'),
                    description: 'Bulk-updated landlord verification status from '.str($fromStatus)->headline().' to '.str($status)->headline().'.',
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

    protected function hasLandlordStatusHistoriesTable(): bool
    {
        return Schema::hasTable('landlord_status_histories');
    }
}
