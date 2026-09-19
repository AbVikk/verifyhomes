<?php

namespace App\Livewire\Support;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\SupportRequest;
use App\Support\SupportCustomerReplyService;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    use InteractsWithAuthenticatedUser;
    use InteractsWithRoleShells;

    public SupportRequest $supportRequest;
    public string $reply = '';

    public function mount(SupportRequest $supportRequest): void
    {
        $this->supportRequest = $supportRequest;
        abort_unless($supportRequest->user_id === $this->currentUserId(), 404);
    }

    public function reply(SupportCustomerReplyService $replyService): void
    {
        $this->validate(['reply' => ['required', 'string', 'min:2', 'max:5000']]);
        $replyService->reply($this->currentUser(), $this->supportRequest, $this->reply);

        $this->reset('reply');
    }

    public function render(): View
    {
        $this->supportRequest->load([
            'publicMessages.user',
            'publicAttachments',
            'property',
            'inspectionRequest.property',
            'paymentTransaction.property',
            'maintenanceRequest.property',
            'occupancyComplaint.occupancy.property',
        ]);
        $user = $this->currentUser();

        return view('livewire.support.show', [
            'supportRoutePrefix' => $user->isLandlord() ? 'landlord' : 'tenant',
        ])->layout('layouts.dashboard-shell', $user->isLandlord()
            ? $this->landlordShell('Support request')
            : $this->tenantShell('Support request'));
    }
}
