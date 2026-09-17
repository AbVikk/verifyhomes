<?php

namespace App\Livewire\Landlord\TenancyAgreements;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\TenancyAgreement;
use App\Support\TenancyAgreementService;
use App\Support\WorkflowNotifier;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    use InteractsWithAuthenticatedUser;
    use InteractsWithRoleShells;

    public TenancyAgreement $agreement;
    public bool $accepted = false;

    public function mount(TenancyAgreement $agreement): void
    {
        abort_unless($agreement->landlord_id === $this->currentUserId(), 404);

        $this->agreement = $agreement->loadMissing(['tenant', 'landlord', 'property', 'occupancy']);
    }

    public function acceptAgreement(): void
    {
        $this->validate([
            'accepted' => ['accepted'],
        ], [
            'accepted.accepted' => 'Confirm that you have read and agree to the tenancy agreement.',
        ]);

        [$agreement, $wasAccepted, $completedNow] = app(TenancyAgreementService::class)
            ->acceptForLandlord($this->agreement, $this->currentUser());

        $this->agreement = $agreement->loadMissing(['tenant', 'landlord', 'property', 'occupancy']);

        if ($completedNow) {
            if ($agreement->tenant) {
                app(WorkflowNotifier::class)->notify(
                    $agreement->tenant,
                    'tenancy-agreement-completed:'.$agreement->getKey(),
                    'Your tenancy agreement has been completed',
                    'Both parties have accepted the tenancy agreement for '.($agreement->property?->title ?? 'your rental property').'.',
                    route('tenant.agreements.show', $agreement),
                    'tenancy_agreement',
                    'View agreement',
                );
            }

            app(WorkflowNotifier::class)->notify(
                $this->currentUser(),
                'tenancy-agreement-completed:'.$agreement->getKey().':landlord',
                'Tenancy agreement completed',
                'Both parties have accepted the tenancy agreement for '.($agreement->property?->title ?? 'the rental property').'.',
                route('landlord.agreements.show', $agreement),
                'tenancy_agreement',
                'View agreement',
            );
        }

        session()->flash('status', $wasAccepted
            ? ($completedNow ? 'Your acceptance has been recorded and the agreement is complete.' : 'Your acceptance has been recorded. The agreement awaits the tenant.')
            : 'Your acceptance was already recorded.');
    }

    public function render(): View
    {
        return view('livewire.landlord.tenancy-agreements.show', [
            'agreement' => $this->agreement,
        ])->layout('layouts.dashboard-shell', $this->landlordShell('Tenancy Agreement', [
            'pageActionLabel' => 'Back to Occupants',
            'pageActionHref' => route('landlord.occupancy.index'),
        ]));
    }
}
