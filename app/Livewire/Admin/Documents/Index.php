<?php

namespace App\Livewire\Admin\Documents;

use App\Livewire\Admin\Concerns\HasAdminLayout;
use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Models\LandlordDocument;
use App\Models\PropertyDocument;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use HasAdminLayout;
    use InteractsWithAuthenticatedUser;
    use WithPagination;

    protected const REVIEW_STATUSES = ['pending', 'approved', 'rejected'];

    #[Url]
    public string $search = '';

    #[Url(except: 'all')]
    public string $sourceFilter = 'all';

    #[Url(except: 'all')]
    public string $statusFilter = 'all';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSourceFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updateDocumentStatus(string $sourceType, int $documentId, string $status): void
    {
        if (! in_array($status, self::REVIEW_STATUSES, true)) {
            $this->flashStatusMessage('That document review action is not available.', 'warning');

            return;
        }

        if (! $this->sourceTableAvailable($sourceType)) {
            $this->flashStatusMessage(
                $sourceType === 'landlord'
                    ? 'Landlord document actions are not available in this environment right now.'
                    : 'Property document actions are not available in this environment right now.',
                'warning',
            );

            return;
        }

        $document = $this->findDocumentForSource($sourceType, $documentId);

        if (! $document) {
            $this->flashStatusMessage('That document is no longer available in the current queue.', 'warning');

            return;
        }

        if ($document->review_status === $status) {
            $this->flashStatusMessage('That document already has the requested review status.', 'info');

            return;
        }

        $previousStatus = $document->review_status;

        $document->update($this->documentReviewUpdatePayloadForSource($sourceType, $status));

        AuditLogger::log(
            action: 'document_status_changed',
            actor: $this->currentUser(),
            target: $document,
            description: 'Changed '.str($sourceType)->headline().' document review status from '.str($previousStatus)->headline().' to '.str($status)->headline().'.',
            metadata: [
                'source_type' => $sourceType,
                'from_status' => $previousStatus,
                'to_status' => $status,
            ],
        );

        $this->flashStatusMessage('Document review status updated successfully.');
    }

    public function render(): View
    {
        $landlordDocumentsAvailable = $this->landlordDocumentsAvailable();
        $propertyDocumentsAvailable = $this->propertyDocumentsAvailable();
        $documentsAvailable = $landlordDocumentsAvailable || $propertyDocumentsAvailable;

        $documents = $documentsAvailable
            ? $this->paginatedDocumentsCollection($landlordDocumentsAvailable, $propertyDocumentsAvailable)
            : $this->emptyPaginator();

        return $this->adminPage(view('livewire.admin.documents.index', [
            'documents' => $documents,
            'documentsAvailable' => $documentsAvailable,
            'landlordDocumentsAvailable' => $landlordDocumentsAvailable,
            'propertyDocumentsAvailable' => $propertyDocumentsAvailable,
            'reviewStatusOptions' => $this->reviewStatusOptions($landlordDocumentsAvailable, $propertyDocumentsAvailable),
        ]), 'Documents');
    }

    protected function paginatedDocumentsCollection(bool $landlordDocumentsAvailable, bool $propertyDocumentsAvailable): LengthAwarePaginator
    {
        $documents = $this->combinedDocumentsCollection($landlordDocumentsAvailable, $propertyDocumentsAvailable)
            ->sortByDesc(fn ($document) => $document->uploaded_at?->timestamp ?? 0)
            ->values();

        $perPage = 10;
        $currentPage = $this->getPage();

        return new LengthAwarePaginator(
            items: $documents->forPage($currentPage, $perPage)->values(),
            total: $documents->count(),
            perPage: $perPage,
            currentPage: $currentPage,
            options: [
                'path' => request()->url(),
                'pageName' => 'page',
            ],
        );
    }

    protected function combinedDocumentsCollection(bool $landlordDocumentsAvailable, bool $propertyDocumentsAvailable): Collection
    {
        return collect()
            ->when($landlordDocumentsAvailable && in_array($this->sourceFilter, ['all', 'landlord'], true), function (Collection $documents): Collection {
                return $documents->concat($this->landlordDocumentsCollection());
            })
            ->when($propertyDocumentsAvailable && in_array($this->sourceFilter, ['all', 'property'], true), function (Collection $documents): Collection {
                return $documents->concat($this->propertyDocumentsCollection());
            });
    }

    protected function landlordDocumentsCollection(): Collection
    {
        return LandlordDocument::query()
            ->with(['landlordProfile.user'])
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('review_status', $this->statusFilter))
            ->when($this->search !== '', function ($query): void {
                $searchTerm = '%'.$this->search.'%';

                $query->where(function ($query) use ($searchTerm): void {
                    $query->where('original_name', 'like', $searchTerm)
                        ->orWhere('document_type', 'like', $searchTerm)
                        ->orWhereHas('landlordProfile', function ($query) use ($searchTerm): void {
                            $query->where('business_name', 'like', $searchTerm)
                                ->orWhereHas('user', function ($query) use ($searchTerm): void {
                                    $query->where('name', 'like', $searchTerm)
                                        ->orWhere('email', 'like', $searchTerm);
                                });
                        });
                });
            })
            ->latest('created_at')
            ->get()
            ->map(function (LandlordDocument $document) {
                $landlordProfile = $document->landlordProfile;
                $user = $landlordProfile?->user;

                return (object) [
                    'id' => $document->id,
                    'source_type' => 'landlord',
                    'source_label' => 'Landlord',
                    'document_type' => $document->document_type,
                    'original_name' => $document->original_name,
                    'entity_label' => $landlordProfile?->business_name ?: ($user?->name ?: 'Landlord profile'),
                    'owner_name' => $user?->name ?: 'Unknown landlord',
                    'owner_email' => $user?->email,
                    'review_status' => $document->review_status,
                    'uploaded_at' => $document->created_at,
                    'review_href' => $landlordProfile ? route('admin.landlords.show', $landlordProfile) : null,
                    'download_href' => $landlordProfile ? route('admin.landlords.documents.download', [$landlordProfile, $document]) : null,
                ];
            });
    }

    protected function propertyDocumentsCollection(): Collection
    {
        return PropertyDocument::query()
            ->with(['property.landlord'])
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('review_status', $this->statusFilter))
            ->when($this->search !== '', function ($query): void {
                $searchTerm = '%'.$this->search.'%';

                $query->where(function ($query) use ($searchTerm): void {
                    $query->where('original_name', 'like', $searchTerm)
                        ->orWhere('document_type', 'like', $searchTerm)
                        ->orWhereHas('property', function ($query) use ($searchTerm): void {
                            $query->where('title', 'like', $searchTerm)
                                ->orWhereHas('landlord', function ($query) use ($searchTerm): void {
                                    $query->where('name', 'like', $searchTerm)
                                        ->orWhere('email', 'like', $searchTerm);
                                });
                        });
                });
            })
            ->latest('created_at')
            ->get()
            ->map(function (PropertyDocument $document) {
                $property = $document->property;
                $landlord = $property?->landlord;

                return (object) [
                    'id' => $document->id,
                    'source_type' => 'property',
                    'source_label' => 'Property',
                    'document_type' => $document->document_type,
                    'original_name' => $document->original_name,
                    'entity_label' => $property?->title ?: 'Property record',
                    'owner_name' => $landlord?->name ?: 'Unknown landlord',
                    'owner_email' => $landlord?->email,
                    'review_status' => $document->review_status,
                    'uploaded_at' => $document->created_at,
                    'review_href' => $property ? route('admin.properties.show', $property) : null,
                    'download_href' => $property ? route('admin.properties.documents.download', [$property, $document]) : null,
                ];
            });
    }

    protected function reviewStatusOptions(bool $landlordDocumentsAvailable, bool $propertyDocumentsAvailable): array
    {
        $statuses = collect();

        if ($landlordDocumentsAvailable) {
            $statuses = $statuses->concat(
                LandlordDocument::query()
                    ->select('review_status')
                    ->distinct()
                    ->pluck('review_status')
            );
        }

        if ($propertyDocumentsAvailable) {
            $statuses = $statuses->concat(
                PropertyDocument::query()
                    ->select('review_status')
                    ->distinct()
                    ->pluck('review_status')
            );
        }

        return $statuses
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->mapWithKeys(fn ($status) => [$status => str($status)->headline()->toString()])
            ->all();
    }

    protected function landlordDocumentsAvailable(): bool
    {
        return $this->tablesExist([
            'landlord_documents',
            'landlord_profiles',
            'users',
        ]);
    }

    protected function propertyDocumentsAvailable(): bool
    {
        return $this->tablesExist([
            'property_documents',
            'properties',
            'users',
        ]);
    }

    protected function sourceTableAvailable(string $sourceType): bool
    {
        return match ($sourceType) {
            'landlord' => $this->landlordDocumentsAvailable(),
            'property' => $this->propertyDocumentsAvailable(),
            default => false,
        };
    }

    protected function findDocumentForSource(string $sourceType, int $documentId): ?Model
    {
        return match ($sourceType) {
            'landlord' => LandlordDocument::query()->find($documentId),
            'property' => PropertyDocument::query()->find($documentId),
            default => null,
        };
    }

    protected function documentReviewUpdatePayload(string $status): array
    {
        return [
            'review_status' => $status,
        ];
    }

    protected function documentReviewUpdatePayloadForSource(string $sourceType, string $status): array
    {
        $payload = $this->documentReviewUpdatePayload($status);
        $table = $this->documentTableForSource($sourceType);

        if (! $table) {
            return $payload;
        }

        if (Schema::hasColumn($table, 'reviewed_by')) {
            $payload['reviewed_by'] = $status === 'pending' ? null : $this->currentUserId();
        }

        if (Schema::hasColumn($table, 'reviewed_at')) {
            $payload['reviewed_at'] = $status === 'pending' ? null : now();
        }

        return $payload;
    }

    protected function flashStatusMessage(string $message, string $tone = 'success'): void
    {
        session()->flash('status', $message);
        session()->flash('statusTone', $tone);
    }

    protected function documentTableForSource(string $sourceType): ?string
    {
        return match ($sourceType) {
            'landlord' => 'landlord_documents',
            'property' => 'property_documents',
            default => null,
        };
    }

    protected function tablesExist(array $tables): bool
    {
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
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
