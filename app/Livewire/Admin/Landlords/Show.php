<?php

namespace App\Livewire\Admin\Landlords;

use App\Livewire\Admin\Concerns\HasAdminLayout;
use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Models\LandlordProfile;
use App\Models\LandlordStatusHistory;
use App\Support\AuditLogger;
use App\Support\ReviewStatusOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    use HasAdminLayout;
    use InteractsWithAuthenticatedUser;

    public LandlordProfile $landlordProfile;

    public ?string $reviewNotes = null;

    public function mount(LandlordProfile $landlordProfile): void
    {
        $this->landlordProfile = $landlordProfile;
    }

    public function changeStatus(string $status): void
    {
        if (! $this->hasLandlordStatusHistoriesTable()) {
            session()->flash('status', 'Landlord review actions are unavailable until landlord history data is available in this environment.');

            return;
        }

        validator(
            ['status' => $status, 'reviewNotes' => $this->reviewNotes],
            [
                'status' => ['required', Rule::in(ReviewStatusOptions::landlordStatusValues())],
                'reviewNotes' => ['nullable', 'string', 'max:2000'],
            ],
            [
                'status.in' => 'Select a valid landlord review status.',
            ],
        )->validate();

        $profile = $this->landlordProfile->fresh();
        $fromStatus = $profile->verification_status;

        if ($fromStatus === $status) {
            session()->flash('status', 'Landlord already has that verification status.');

            return;
        }

        DB::transaction(function () use ($profile, $fromStatus, $status): void {
            $profile->update([
                'verification_status' => $status,
                'verified_at' => $status === 'approved' ? now() : null,
                'verified_by' => $status === 'approved' ? $this->currentUserId() : null,
                'admin_notes' => $this->reviewNotes,
            ]);

            LandlordStatusHistory::create([
                'landlord_profile_id' => $profile->id,
                'from_status' => $fromStatus,
                'to_status' => $status,
                'changed_by' => $this->currentUserId(),
                'notes' => $this->reviewNotes,
            ]);

            AuditLogger::log(
                action: 'landlord_status_changed',
                actor: $this->currentUser(),
                target: $profile->loadMissing('user'),
                description: 'Changed landlord verification status from '.str($fromStatus)->headline().' to '.str($status)->headline().'.',
                metadata: [
                    'from_status' => $fromStatus,
                    'to_status' => $status,
                    'notes' => $this->reviewNotes,
                ],
            );
        });

        $this->landlordProfile = $this->landlordProfile->fresh();
        $this->reviewNotes = null;

        session()->flash('status', 'Landlord verification status updated successfully.');
    }

    public function render(): View
    {
        $historyAvailable = $this->hasLandlordStatusHistoriesTable();
        $landlordProfile = $this->landlordProfile->load(
            $historyAvailable
                ? ['user', 'documents.reviewer', 'statusHistories.changedBy']
                : ['user', 'documents.reviewer']
        );

        return $this->adminPage(view('livewire.admin.landlords.show', [
            'landlordProfile' => $landlordProfile,
            'historyAvailable' => $historyAvailable,
        ]), 'Landlord Review', 'Back to Landlords', route('admin.landlords.index'));
    }

    protected function hasLandlordStatusHistoriesTable(): bool
    {
        return Schema::hasTable('landlord_status_histories');
    }
}
