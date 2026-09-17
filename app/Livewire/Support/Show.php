<?php

namespace App\Livewire\Support;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\SupportRequest;
use App\Models\User;
use App\Support\WorkflowNotifier;
use Illuminate\Support\Facades\DB;
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

    public function reply(): void
    {
        $this->validate(['reply' => ['required', 'string', 'min:2', 'max:5000']]);
        abort_if($this->supportRequest->status === 'closed', 403, 'This support request is closed.');

        $updated = DB::transaction(function (): SupportRequest {
            $locked = SupportRequest::query()->lockForUpdate()->findOrFail($this->supportRequest->id);
            abort_if($locked->user_id !== $this->currentUserId() || $locked->status === 'closed', 403);
            $locked->messages()->create(['user_id' => $this->currentUserId(), 'sender_type' => $locked->role_snapshot, 'body' => $this->reply, 'is_internal' => false]);
            if (in_array($locked->status, ['resolved', 'waiting_for_user'], true)) {
                $locked->update(['status' => 'open', 'resolved_at' => null]);
            }
            $locked->touch();

            return $locked->fresh(['assignedTo']);
        });

        $recipients = $updated->assignedTo ? collect([$updated->assignedTo]) : User::role('admin')->get();
        foreach ($recipients as $recipient) {
            app(WorkflowNotifier::class)->notify($recipient, 'support-customer-reply:'.$updated->id.':'.$updated->updated_at->timestamp.':'.$recipient->id, 'New reply on '.$updated->reference, $this->currentUser()->name.' replied to a support request.', $recipient->isSupportStaff() ? route('support-team.requests.show', $updated) : route('admin.support.show', $updated), 'support_request', 'Open request');
        }

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
