<?php

namespace App\Livewire\Admin\Audit;

use App\Livewire\Admin\Concerns\HasAdminLayout;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
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
    public string $actionFilter = 'all';

    #[Url(except: 'all')]
    public string $targetTypeFilter = 'all';

    #[Url(except: '')]
    public string $fromDate = '';

    #[Url(except: '')]
    public string $toDate = '';

    #[Url(except: 'desc')]
    public string $sortDirection = 'desc';

    #[Url(except: '10')]
    public string $perPage = '10';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingActionFilter(): void
    {
        $this->resetPage();
    }

    public function updatingTargetTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingFromDate(): void
    {
        $this->resetPage();
    }

    public function updatedFromDate(): void
    {
        $this->normalizeDateRange();
    }

    public function updatingToDate(): void
    {
        $this->resetPage();
    }

    public function updatedToDate(): void
    {
        $this->normalizeDateRange();
    }

    public function updatingSortDirection(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->actionFilter = 'all';
        $this->targetTypeFilter = 'all';
        $this->fromDate = '';
        $this->toDate = '';
        $this->sortDirection = 'desc';
        $this->perPage = '10';
        $this->resetPage();
    }

    public function render(): View
    {
        $auditAvailable = $this->hasAuditLogsTable();
        $dateFilterAvailable = $auditAvailable && $this->hasAuditColumn('created_at');
        $totalAuditEntries = $auditAvailable ? DB::table('audit_logs')->count() : 0;

        if ($dateFilterAvailable) {
            $this->normalizeDateRange();
        }

        $auditLogs = $auditAvailable
            ? $this->auditLogsQuery()->paginate($this->normalizedPerPage())
            : $this->emptyPaginator();

        return $this->adminPage(view('livewire.admin.audit.index', [
            'auditAvailable' => $auditAvailable,
            'auditLogs' => $auditLogs,
            'totalAuditEntries' => $totalAuditEntries,
            'actionOptions' => $auditAvailable ? $this->auditActionOptions() : [],
            'targetTypeOptions' => $auditAvailable ? $this->auditTargetTypeOptions() : [],
            'dateFilterAvailable' => $dateFilterAvailable,
        ]), 'Audit');
    }

    protected function auditLogsQuery()
    {
        $query = DB::table('audit_logs');

        if ($this->actionFilter !== 'all' && $this->hasAuditColumn('action')) {
            $query->where('action', $this->actionFilter);
        }

        if ($this->targetTypeFilter !== 'all' && $this->hasAuditColumn('target_type')) {
            $query->where('target_type', $this->targetTypeFilter);
        }

        if ($this->hasAuditColumn('created_at')) {
            if ($this->fromDate !== '') {
                $query->whereDate('created_at', '>=', $this->fromDate);
            }

            if ($this->toDate !== '') {
                $query->whereDate('created_at', '<=', $this->toDate);
            }
        }

        if ($this->hasAuditColumn('created_at')) {
            $query->orderBy('created_at', $this->normalizedSortDirection());
        } elseif ($this->hasAuditColumn('id')) {
            $query->orderByDesc('id');
        }

        if ($this->search !== '') {
            $searchTerm = '%'.$this->search.'%';
            $searchableColumns = $this->searchableAuditColumns();

            if ($searchableColumns !== []) {
                $query->where(function ($query) use ($searchTerm, $searchableColumns): void {
                    foreach ($searchableColumns as $index => $column) {
                        if ($index === 0) {
                            $query->where($column, 'like', $searchTerm);
                        } else {
                            $query->orWhere($column, 'like', $searchTerm);
                        }
                    }
                });
            }
        }

        return $query;
    }

    protected function auditActionOptions(): array
    {
        if (! $this->hasAuditColumn('action')) {
            return [];
        }

        return DB::table('audit_logs')
            ->select('action')
            ->whereNotNull('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->mapWithKeys(fn ($action) => [$action => str($action)->headline()->toString()])
            ->all();
    }

    protected function auditTargetTypeOptions(): array
    {
        if (! $this->hasAuditColumn('target_type')) {
            return [];
        }

        return DB::table('audit_logs')
            ->select('target_type')
            ->whereNotNull('target_type')
            ->distinct()
            ->orderBy('target_type')
            ->pluck('target_type')
            ->mapWithKeys(fn ($targetType) => [$targetType => $this->targetTypeLabel($targetType)])
            ->all();
    }

    protected function searchableAuditColumns(): array
    {
        return collect([
            'action',
            'event',
            'label',
            'actor_name',
            'actor_email',
            'user_name',
            'user_email',
            'target',
            'target_label',
            'target_type',
            'entity',
            'entity_label',
            'description',
        ])->filter(fn (string $column) => $this->hasAuditColumn($column))
            ->values()
            ->all();
    }

    protected function hasAuditColumn(string $column): bool
    {
        return $this->hasAuditLogsTable() && Schema::hasColumn('audit_logs', $column);
    }

    protected function normalizedSortDirection(): string
    {
        return $this->sortDirection === 'asc' ? 'asc' : 'desc';
    }

    protected function normalizedPerPage(): int
    {
        return match ($this->perPage) {
            '25' => 25,
            '50' => 50,
            default => 10,
        };
    }

    protected function normalizeDateRange(): void
    {
        if ($this->fromDate === '' || $this->toDate === '') {
            return;
        }

        if ($this->fromDate <= $this->toDate) {
            return;
        }

        [$this->fromDate, $this->toDate] = [$this->toDate, $this->fromDate];
    }

    protected function emptyPaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            items: new Collection(),
            total: 0,
            perPage: $this->normalizedPerPage(),
            currentPage: 1,
            options: [
                'path' => request()->url(),
                'pageName' => 'page',
            ],
        );
    }

    protected function targetTypeLabel(string $targetType): string
    {
        if ($targetType === 'string') {
            return 'General';
        }

        return str(class_basename($targetType))->headline()->toString();
    }

    private function hasAuditLogsTable(): bool
    {
        return Schema::hasTable('audit_logs');
    }
}
