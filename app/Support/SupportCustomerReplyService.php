<?php

namespace App\Support;

use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SupportCustomerReplyService
{
    public function reply(User $user, SupportRequest $supportRequest, string $body): SupportRequest
    {
        $updated = DB::transaction(function () use ($user, $supportRequest, $body): SupportRequest {
            $locked = SupportRequest::query()->lockForUpdate()->findOrFail($supportRequest->id);

            abort_if($locked->user_id !== $user->id || $locked->status === 'closed', 403);

            $locked->messages()->create([
                'user_id' => $user->id,
                'sender_type' => $locked->role_snapshot,
                'body' => $body,
                'is_internal' => false,
            ]);

            if (in_array($locked->status, ['resolved', 'waiting_for_user'], true)) {
                $locked->update(['status' => 'open', 'resolved_at' => null]);
            }

            $locked->touch();

            return $locked->fresh(['assignedTo']);
        });

        $recipients = $updated->assignedTo ? collect([$updated->assignedTo]) : User::role('admin')->get();

        foreach ($recipients as $recipient) {
            app(WorkflowNotifier::class)->notify(
                $recipient,
                'support-customer-reply:'.$updated->id.':'.$updated->updated_at->timestamp.':'.$recipient->id,
                'New reply on '.$updated->reference,
                $user->name.' replied to a support request.',
                $recipient->isSupportStaff() ? route('support-team.requests.show', $updated) : route('admin.support.show', $updated),
                'support_request',
                'Open request',
            );
        }

        return $updated;
    }
}
