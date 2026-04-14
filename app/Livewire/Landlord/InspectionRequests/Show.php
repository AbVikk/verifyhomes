<?php

namespace App\Livewire\Landlord\InspectionRequests;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\InspectionRequest;
use App\Support\InspectionRequestOptions;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    use InteractsWithAuthenticatedUser;
    use InteractsWithRoleShells;

    public ?InspectionRequest $inspectionRequest = null;

    public ?string $landlordNote = null;

    public function mount(?InspectionRequest $inspectionRequest = null, ?string $inspectionRequestId = null): void
    {
        if (! $this->detailAvailable()) {
            return;
        }

        $inspectionRequest ??= InspectionRequest::query()->find($inspectionRequestId);

        abort_if(! $inspectionRequest, 404);
        abort_unless($inspectionRequest->property()->where('landlord_id', $this->currentUserId())->exists(), 404);

        $this->inspectionRequest = $inspectionRequest;
        $this->landlordNote = $inspectionRequest->landlord_note;
    }

    public function saveLandlordNote(): void
    {
        if (! $this->canPerformActions()) {
            return;
        }

        $this->validate([
            'landlordNote' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->inspectionRequest->update([
            'landlord_note' => $this->landlordNote,
        ]);

        $this->inspectionRequest = $this->inspectionRequest->fresh();

        session()->flash('status', 'Coordination note saved for the VerifyHomes team.');
    }

    public function render(): View
    {
        $inspectionRequest = $this->detailAvailable() && $this->inspectionRequest
            ? $this->inspectionRequest->load([
                'property',
                'statusHistories.changedBy',
                'tenant',
            ])
            : $this->inspectionRequest;

        return view('livewire.landlord.inspection-requests.show', [
            'inspectionRequest' => $inspectionRequest,
            'detailAvailable' => $this->detailAvailable() && $this->inspectionRequest !== null,
            'outcomes' => InspectionRequestOptions::outcomes(),
        ])->layout('layouts.dashboard-shell', $this->landlordShell('Inspection Request'));
    }

    public function nextStepSummary(?InspectionRequest $inspectionRequest = null): string
    {
        $inspectionRequest ??= $this->inspectionRequest;

        if (! $inspectionRequest) {
            return 'Inspection request details are not available yet.';
        }

        return match ($inspectionRequest->status) {
            InspectionRequestOptions::STATUS_REQUESTED => 'Add any access or readiness note admin needs.',
            InspectionRequestOptions::STATUS_SCHEDULED => 'The visit is booked. Keep access ready.',
            InspectionRequestOptions::STATUS_COMPLETED => 'Review the outcome and follow-up note.',
            InspectionRequestOptions::STATUS_CANCELLED, InspectionRequestOptions::STATUS_REJECTED => 'This request is closed.',
            default => 'Check the latest admin update.',
        };
    }

    protected function detailAvailable(): bool
    {
        return $this->hasInspectionRequestsTable() && $this->hasInspectionRequestStatusHistoriesTable();
    }

    protected function canPerformActions(): bool
    {
        if ($this->detailAvailable() && $this->inspectionRequest) {
            return true;
        }

        session()->flash('status', 'Inspection request actions are not available yet in this environment.');

        return false;
    }

    protected function hasInspectionRequestsTable(): bool
    {
        return Schema::hasTable('inspection_requests');
    }

    protected function hasInspectionRequestStatusHistoriesTable(): bool
    {
        return Schema::hasTable('inspection_request_status_histories');
    }

}
