<?php

namespace App\Livewire\Tenant\TenancyAgreements;

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
        abort_unless($agreement->tenant_id === $this->currentUserId(), 404);

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
            ->acceptForTenant($this->agreement, $this->currentUser());

        $this->agreement = $agreement->loadMissing(['tenant', 'landlord', 'property', 'occupancy']);

        if ($completedNow) {
            app(WorkflowNotifier::class)->notify(
                $this->currentUser(),
                'tenancy-agreement-completed:'.$agreement->getKey(),
                'Your tenancy agreement has been completed',
                'Both parties have accepted the tenancy agreement for '.($agreement->property?->title ?? 'your rental property').'.',
                route('tenant.agreements.show', $agreement),
                'tenancy_agreement',
                'View agreement',
            );
        } elseif ($wasAccepted && $agreement->landlord) {
            app(WorkflowNotifier::class)->notify(
                $agreement->landlord,
                'tenancy-agreement-tenant-accepted:'.$agreement->getKey(),
                'Your tenant has accepted the tenancy agreement',
                'Please review and accept the tenancy agreement for '.($agreement->property?->title ?? 'the rental property').'.',
                route('landlord.agreements.show', $agreement),
                'tenancy_agreement',
                'Review agreement',
            );
        }

        session()->flash('status', $wasAccepted
            ? 'Your acceptance has been recorded. We will notify you when the landlord accepts.'
            : 'Your acceptance was already recorded.');
    }

    public function render(): View
    {
        return view('livewire.tenant.tenancy-agreements.show', [
            'agreement' => $this->agreement,
        ])->layout('layouts.dashboard-shell', $this->tenantShell('Tenancy Agreement', [
            'pageActionLabel' => 'Back to My Stays',
            'pageActionHref' => route('tenant.occupancy.index'),
        ]));
    }
}
