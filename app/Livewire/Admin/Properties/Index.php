<?php

namespace App\Livewire\Admin\Properties;

use App\Livewire\Admin\Concerns\HasAdminLayout;
use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Models\Property;
use App\Models\PropertyStatusHistory;
use App\Support\AuditLogger;
use Illuminate\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use HasAdminLayout;
    use InteractsWithAuthenticatedUser;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url(except: 'all')]
    public string $statusFilter = 'all';

    #[Url(except: 'all')]
    public string $publishFilter = 'all';

    public array $selectedPropertyIds = [];

    public bool $selectPage = false;

    public function updatingSearch(): void
    {
        $this->resetPageAndSelection();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPageAndSelection();
    }

    public function updatingPublishFilter(): void
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
            $this->selectedPropertyIds = [];

            return;
        }

        $this->selectedPropertyIds = $this->currentPagePropertyIds();
    }

    public function updatedSelectedPropertyIds(): void
    {
        $this->selectedPropertyIds = collect($this->selectedPropertyIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $currentPageIds = $this->currentPagePropertyIds();

        $this->selectPage = $currentPageIds !== []
            && count(array_intersect($currentPageIds, $this->selectedPropertyIds)) === count($currentPageIds);
    }

    public function clearSelection(): void
    {
        $this->selectedPropertyIds = [];
        $this->selectPage = false;
    }

    public function bulkApprove(): void
    {
        $this->runBulkStatusUpdate(
            status: 'approved',
            successMessage: 'Selected properties were approved successfully.',
            noChangeMessage: 'Selected properties already had that review status.',
        );
    }

    public function bulkReject(): void
    {
        $this->runBulkStatusUpdate(
            status: 'rejected',
            successMessage: 'Selected properties were rejected successfully.',
            noChangeMessage: 'Selected properties already had that review status.',
        );
    }

    public function bulkUnpublish(): void
    {
        $properties = $this->selectedPropertiesQuery()
            ->where('is_published', true)
            ->get(['id', 'is_published']);

        if ($properties->isEmpty()) {
            $this->flashBulkMessage('No selected published properties were available to unpublish.', 'warning');
            $this->clearSelection();

            return;
        }

        DB::transaction(function () use ($properties): void {
            Property::query()
                ->whereKey($properties->pluck('id'))
                ->update([
                    'is_published' => false,
                ]);

            foreach ($properties as $property) {
                AuditLogger::log(
                    action: 'property_unpublished',
                    actor: $this->currentUser(),
                    target: $property,
                    description: 'Bulk-unpublished property from the property review queue.',
                    metadata: [
                        'property_id' => $property->getKey(),
                        'source' => 'bulk_queue',
                    ],
                );
            }
        });

        $this->flashBulkMessage('Selected properties were unpublished successfully.');
        $this->clearSelection();
    }

    public function render(): View
    {
        $properties = $this->filteredPropertiesQuery()
            ->with(['landlord'])
            ->withCount(['images', 'documents'])
            ->paginate(10);

        return $this->adminPage(view('livewire.admin.properties.index', [
            'properties' => $properties,
        ]), 'Property Reviews');
    }

    protected function filteredPropertiesQuery(): Builder
    {
        return Property::query()
            ->when($this->search !== '', function ($query): void {
                $searchTerm = '%'.$this->search.'%';

                $query->where(function ($query) use ($searchTerm): void {
                    $query->where('title', 'like', $searchTerm)
                        ->orWhere('city', 'like', $searchTerm)
                        ->orWhere('area', 'like', $searchTerm)
                        ->orWhere('landmark', 'like', $searchTerm)
                        ->orWhereHas('landlord', fn ($query) => $query->where('name', 'like', $searchTerm));
                });
            })
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->publishFilter !== 'all', function ($query): void {
                match ($this->publishFilter) {
                    'published' => $query->where('is_published', true),
                    'approved_unpublished' => $query
                        ->where('status', 'approved')
                        ->where('is_verified', true)
                        ->where('is_published', false),
                    'not_eligible' => $query->where(function ($query): void {
                        $query->where('status', '!=', 'approved')
                            ->orWhere('is_verified', false);
                    }),
                    default => null,
                };
            })
            ->orderByRaw("CASE status WHEN 'pending_review' THEN 0 WHEN 'rejected' THEN 1 WHEN 'suspended' THEN 2 WHEN 'approved' THEN 3 ELSE 4 END")
            ->latest('updated_at');
    }

    protected function currentPagePropertyIds(): array
    {
        return $this->filteredPropertiesQuery()
            ->forPage($this->getPage(), 10)
            ->pluck('properties.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function selectedPropertiesQuery(): Builder
    {
        return $this->filteredPropertiesQuery()->whereKey($this->selectedPropertyIds);
    }

    protected function runBulkStatusUpdate(string $status, string $successMessage, string $noChangeMessage): void
    {
        if (! $this->hasPropertyStatusHistoriesTable()) {
            $this->flashBulkMessage('Property bulk actions are unavailable until property history data is available in this environment.', 'warning');
            $this->clearSelection();

            return;
        }

        $properties = $this->selectedPropertiesQuery()->get([
            'id',
            'status',
            'is_verified',
            'is_published',
            'verified_at',
            'verified_by',
        ]);

        if ($properties->isEmpty()) {
            $this->flashBulkMessage('Select at least one property before running a bulk action.', 'warning');
            $this->clearSelection();

            return;
        }

        $propertiesToUpdate = $properties->filter(fn (Property $property) => $property->status !== $status)->values();

        if ($propertiesToUpdate->isEmpty()) {
            $this->flashBulkMessage($noChangeMessage, 'info');
            $this->clearSelection();

            return;
        }

        DB::transaction(function () use ($propertiesToUpdate, $status): void {
            $timestamp = now();
            $adminId = $this->currentUserId();
            $approved = $status === 'approved';

            foreach ($propertiesToUpdate as $property) {
                $fromStatus = $property->status;

                $property->update([
                    'status' => $status,
                    'is_verified' => $approved,
                    'is_published' => false,
                    'verified_at' => $approved ? $timestamp : null,
                    'verified_by' => $approved ? $adminId : null,
                ]);

                PropertyStatusHistory::create([
                    'property_id' => $property->id,
                    'from_status' => $fromStatus,
                    'to_status' => $status,
                    'changed_by' => $adminId,
                    'notes' => 'Bulk action from property review queue.',
                ]);

                AuditLogger::log(
                    action: 'property_status_changed',
                    actor: $this->currentUser(),
                    target: $property,
                    description: 'Bulk-updated property review status from '.str($fromStatus)->headline().' to '.str($status)->headline().'.',
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

    protected function hasPropertyStatusHistoriesTable(): bool
    {
        return Schema::hasTable('property_status_histories');
    }
}
