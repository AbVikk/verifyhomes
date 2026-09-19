<?php

namespace App\Livewire\Support;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Models\SupportRequest;
use App\Models\User;
use App\Support\SupportCustomerReplyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Component;

class ActiveDrawer extends Component
{
    use InteractsWithAuthenticatedUser;

    /** @var array<int, string> */
    private const ACTIVE_STATUSES = ['open', 'in_progress', 'waiting_for_user'];

    public ?int $selectedRequestId = null;
    public string $replyBody = '';

    public function selectRequest(int $requestId): void
    {
        $this->selectedRequestId = (int) $this->activeRequestsQuery()->findOrFail($requestId)->id;
        $this->resetValidation('replyBody');
    }

    public function sendReply(SupportCustomerReplyService $replyService): void
    {
        $this->validate(['replyBody' => ['required', 'string', 'min:2', 'max:5000']]);

        $supportRequest = $this->ownedRequestsQuery()->findOrFail($this->selectedRequestId);
        $replyService->reply($this->currentUser(), $supportRequest, $this->replyBody);

        $this->reset('replyBody');
    }

    public function render(): View
    {
        $user = $this->currentUser();
        abort_unless($user->isTenant() || $user->isLandlord(), 404);

        $requests = $this->activeRequestsQuery()
            ->orderByRaw("case status when 'waiting_for_user' then 0 when 'in_progress' then 1 else 2 end")
            ->orderByDesc('updated_at')
            ->get();

        if ($requests->isEmpty()) {
            $this->selectedRequestId = null;
        } elseif (! $requests->contains('id', $this->selectedRequestId)) {
            $this->selectedRequestId = (int) $requests->first()->id;
        }

        $selectedRequest = $this->selectedRequestId
            ? $this->ownedRequestsQuery()
                ->with(['publicMessages.user:id,name', 'publicAttachments'])
                ->find($this->selectedRequestId)
            : null;

        return view('livewire.support.active-drawer', [
            'requests' => $requests,
            'selectedRequest' => $selectedRequest,
            'routePrefix' => $user->isLandlord() ? 'landlord' : 'tenant',
        ]);
    }

    private function ownedRequestsQuery(): Builder
    {
        return SupportRequest::query()->where('user_id', $this->currentUserId());
    }

    private function activeRequestsQuery(): Builder
    {
        return $this->ownedRequestsQuery()->whereIn('status', self::ACTIVE_STATUSES);
    }
}
