<?php

namespace App\Http\Controllers;

use App\Models\SupportRequest;
use App\Models\SupportRequestAttachment;
use App\Models\SupportRequestEvent;
use App\Models\User;
use App\Support\WorkflowNotifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportOperationsController extends Controller
{
    public function dashboard(Request $request): View
    {
        $staffId = $request->user()->id;

        return view('support-team.dashboard', [
            'summary' => [
                'open' => SupportRequest::whereIn('status', ['open', 'in_progress'])->count(),
                'waiting' => SupportRequest::where('status', 'waiting_for_user')->count(),
                'resolvedToday' => SupportRequest::where('status', 'resolved')->whereDate('resolved_at', today())->count(),
                'assignedToMe' => SupportRequest::where('assigned_to', $staffId)->whereNotIn('status', ['closed'])->count(),
                'unassigned' => SupportRequest::whereNull('assigned_to')->whereNotIn('status', ['closed'])->count(),
                'highUrgent' => SupportRequest::whereIn('priority', ['high', 'urgent'])->whereNotIn('status', ['closed'])->count(),
            ],
            'recentRequests' => $this->baseQuery()->latest('updated_at')->take(6)->get(),
        ]);
    }

    public function supportIndex(Request $request): View
    {
        return view('support-team.requests.index', $this->queueData($request, 'support-team'));
    }

    public function adminIndex(Request $request): View
    {
        return view('admin.support.index', $this->queueData($request, 'admin'));
    }

    public function supportShow(Request $request, SupportRequest $supportRequest): View
    {
        return view('support-team.requests.show', $this->detailData($request, $supportRequest, 'support-team'));
    }

    public function adminShow(Request $request, SupportRequest $supportRequest): View
    {
        return view('admin.support.show', $this->detailData($request, $supportRequest, 'admin'));
    }

    public function assign(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $data = $request->validate(['assigned_to' => ['nullable', 'integer']]);
        $actor = $request->user();

        [$updated, $event, $assignee] = DB::transaction(function () use ($supportRequest, $data, $actor): array {
            $locked = SupportRequest::query()->lockForUpdate()->findOrFail($supportRequest->id);
            $assignee = isset($data['assigned_to']) && $data['assigned_to'] !== null
                ? $this->activeSupportStaff((int) $data['assigned_to'])
                : null;

            if ($locked->assigned_to === ($assignee?->id)) {
                return [$locked, null, $assignee];
            }

            $eventType = $assignee === null
                ? 'unassigned'
                : ($locked->assigned_to === null ? 'assigned' : 'reassigned');
            $previous = $locked->assigned_to;
            $locked->update([
                'assigned_to' => $assignee?->id,
                'assigned_at' => $assignee ? now() : null,
            ]);
            $event = $this->event($locked, $actor, $eventType, $previous, $assignee?->id);

            return [$locked, $event, $assignee];
        });

        if ($event && $assignee) {
            app(WorkflowNotifier::class)->notify(
                $assignee,
                'support-request-assignment:'.$updated->id.':'.$event->id,
                'Support request assigned to you',
                "{$updated->reference}: {$updated->subject}",
                route('support-team.requests.show', $updated),
                'support_request',
                'Open request',
            );
        }

        return back()->with('status', $assignee ? 'Support request assignment updated.' : 'Support request unassigned.');
    }

    public function claim(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $actor = $request->user();

        [$updated, $event] = DB::transaction(function () use ($supportRequest, $actor): array {
            $locked = SupportRequest::query()->lockForUpdate()->findOrFail($supportRequest->id);

            if ($locked->assigned_to !== null) {
                throw ValidationException::withMessages(['assignment' => 'This request has already been assigned to another support staff member.']);
            }

            $locked->update(['assigned_to' => $actor->id, 'assigned_at' => now()]);

            return [$locked, $this->event($locked, $actor, 'claimed', null, $actor->id)];
        });

        app(WorkflowNotifier::class)->notify(
            $actor,
            'support-request-claim:'.$updated->id.':'.$event->id,
            'Support request assigned to you',
            "{$updated->reference}: {$updated->subject}",
            route('support-team.requests.show', $updated),
            'support_request',
            'Open request',
        );

        return back()->with('status', 'This request is now assigned to you.');
    }

    public function changePriority(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $data = $request->validate(['priority' => ['required', Rule::in(SupportRequest::PRIORITIES)]]);
        $this->mutateOwnedRequest($request, $supportRequest, function (SupportRequest $locked, User $actor) use ($data): void {
            if ($locked->priority === $data['priority']) {
                return;
            }

            $previous = $locked->priority;
            $locked->update(['priority' => $data['priority']]);
            $this->event($locked, $actor, 'priority_changed', $previous, $data['priority']);
        });

        return back()->with('status', 'Priority updated.');
    }

    public function changeStatus(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(SupportRequest::STATUSES)]]);
        $isAdminArea = $request->routeIs('admin.support.*');
        $customerNotification = null;

        $this->mutateOwnedRequest($request, $supportRequest, function (SupportRequest $locked, User $actor) use ($data, $isAdminArea, &$customerNotification): void {
            $from = $locked->status;
            $to = $data['status'];

            if ($from === $to) {
                return;
            }

            if ($isAdminArea) {
                if ($from === 'closed' && $to !== 'open') {
                    throw ValidationException::withMessages(['status' => 'A closed request can only be reopened to Open.']);
                }
            } elseif ($from === 'closed') {
                abort(403);
            } elseif (! in_array($to, $this->staffTransitions($from), true)) {
                throw ValidationException::withMessages(['status' => 'This status change is not available for the current request.']);
            }

            $attributes = ['status' => $to];
            if ($to === 'resolved') {
                $attributes['resolved_at'] = $locked->resolved_at ?? now();
            } elseif ($from === 'resolved') {
                $attributes['resolved_at'] = null;
            }
            if ($to === 'closed') {
                $attributes['closed_at'] = now();
            } elseif ($from === 'closed') {
                $attributes['closed_at'] = null;
            }

            $locked->update($attributes);
            $this->event($locked, $actor, 'status_changed', $from, $to);
            if (in_array($to, ['resolved', 'closed'], true)) {
                $customerNotification = [$locked->fresh(), $to];
            }
        }, $isAdminArea);

        if ($customerNotification) {
            [$updated, $status] = $customerNotification;
            $this->notifyCustomerOfStatus($updated, $status);
        }

        return back()->with('status', 'Status updated.');
    }

    public function publicReply(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'status' => ['nullable', Rule::in(['in_progress', 'waiting_for_user', 'resolved'])],
        ]);
        $isAdminArea = $request->routeIs('admin.support.*');
        $actor = $request->user();

        [$updated, $statusChanged] = DB::transaction(function () use ($supportRequest, $data, $actor, $isAdminArea): array {
            $locked = SupportRequest::query()->lockForUpdate()->findOrFail($supportRequest->id);
            if ($locked->status === 'closed' || (! $isAdminArea && $locked->assigned_to !== $actor->id)) {
                abort(403);
            }

            $locked->messages()->create([
                'user_id' => $actor->id,
                'sender_type' => 'support',
                'body' => $data['body'],
                'is_internal' => false,
            ]);
            $this->event($locked, $actor, 'public_reply_sent');
            $statusChanged = false;

            if (! empty($data['status']) && $data['status'] !== $locked->status) {
                $from = $locked->status;
                $to = $data['status'];
                $attributes = ['status' => $to];
                if ($to === 'resolved') {
                    $attributes['resolved_at'] = $locked->resolved_at ?? now();
                } elseif ($from === 'resolved') {
                    $attributes['resolved_at'] = null;
                }
                $locked->update($attributes);
                $this->event($locked, $actor, 'status_changed', $from, $to);
                $statusChanged = $to === 'resolved' ? 'resolved' : false;
            }

            $locked->touch();

            return [$locked->fresh(), $statusChanged];
        });

        $this->notifyCustomerOfReply($updated);
        if ($statusChanged === 'resolved') {
            $this->notifyCustomerOfStatus($updated, 'resolved');
        }

        return back()->with('status', 'Public reply sent.');
    }

    public function internalNote(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:5000']]);
        $isAdminArea = $request->routeIs('admin.support.*');
        $actor = $request->user();

        DB::transaction(function () use ($supportRequest, $data, $actor, $isAdminArea): void {
            $locked = SupportRequest::query()->lockForUpdate()->findOrFail($supportRequest->id);
            if (! $isAdminArea && $locked->assigned_to !== $actor->id) {
                abort(403);
            }
            $locked->messages()->create(['user_id' => $actor->id, 'sender_type' => 'support', 'body' => $data['body'], 'is_internal' => true]);
            $this->event($locked, $actor, 'internal_note_added');
            $locked->touch();
        });

        return back()->with('status', 'Internal note added.');
    }

    public function escalate(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(SupportRequest::ESCALATION_CATEGORIES)],
            'note' => ['required', 'string', 'min:2', 'max:2000'],
        ]);
        $actor = $request->user();

        $updated = DB::transaction(function () use ($supportRequest, $data, $actor): SupportRequest {
            $locked = SupportRequest::query()->lockForUpdate()->findOrFail($supportRequest->id);
            if ($locked->assigned_to !== $actor->id || $locked->status === 'closed') {
                abort(403);
            }
            $locked->update([
                'escalated_at' => now(),
                'escalated_by' => $actor->id,
                'escalation_category' => $data['category'],
                'escalation_note' => $data['note'],
            ]);
            $this->event($locked, $actor, 'escalated_to_admin', null, $data['category']);

            return $locked->fresh();
        });

        foreach (User::role('admin')->get() as $admin) {
            app(WorkflowNotifier::class)->notify($admin, 'support-request-escalated:'.$updated->id.':'.$updated->escalated_at?->timestamp, 'Support request escalated', "{$updated->reference}: {$updated->subject}", route('admin.support.show', $updated), 'support_request', 'Review request');
        }

        return back()->with('status', 'Request escalated to Admin.');
    }

    public function clearEscalation(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $actor = $request->user();

        DB::transaction(function () use ($supportRequest, $actor): void {
            $locked = SupportRequest::query()->lockForUpdate()->findOrFail($supportRequest->id);
            if (! $locked->escalated_at) {
                return;
            }
            $previous = $locked->escalation_category;
            $locked->update(['escalated_at' => null, 'escalated_by' => null, 'escalation_category' => null, 'escalation_note' => null]);
            $this->event($locked, $actor, 'escalation_cleared', $previous);
        });

        return back()->with('status', 'Escalation cleared.');
    }

    public function attachment(SupportRequest $supportRequest, SupportRequestAttachment $attachment, bool $download = false): StreamedResponse
    {
        abort_unless($attachment->support_request_id === $supportRequest->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);
        $name = Str::limit(preg_replace('/[^A-Za-z0-9._ -]/', '_', basename($attachment->original_name)) ?: 'support-attachment', 180, '');

        if ($download) {
            return Storage::disk('local')->download($attachment->file_path, $name);
        }

        return Storage::disk('local')->response($attachment->file_path, $name, ['Cache-Control' => 'private, no-store']);
    }

    private function queueData(Request $request, string $area): array
    {
        $query = $this->baseQuery();
        $status = $request->string('status')->toString();
        $category = $request->string('category')->toString();
        $assignment = $request->string('assignment')->toString();

        if (in_array($status, SupportRequest::STATUSES, true)) {
            $query->where('status', $status);
        }
        if (in_array($category, SupportRequest::CATEGORIES, true)) {
            $query->where('category', $category);
        }
        if ($area === 'support-team' && $assignment === 'mine') {
            $query->where('assigned_to', $request->user()->id);
        }
        if ($assignment === 'unassigned') {
            $query->whereNull('assigned_to');
        }
        if ($area === 'admin' && str_starts_with($assignment, 'staff:')) {
            $staffId = (int) Str::after($assignment, 'staff:');
            if ($this->activeSupportStaffQuery()->whereKey($staffId)->exists()) {
                $query->where('assigned_to', $staffId);
            }
        }
        if ($area === 'admin' && $assignment === 'escalated') {
            $query->whereNotNull('escalated_at');
        }
        if ($search = trim($request->string('search')->toString())) {
            $query->where(fn (Builder $q) => $q
                ->where('reference', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%")
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")));
        }

        return [
            'requests' => $query
                ->orderByRaw("case when status in ('resolved', 'closed') then 1 else 0 end")
                ->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")
                ->orderBy('created_at')
                ->paginate(20)
                ->withQueryString(),
            'area' => $area,
            'statuses' => SupportRequest::STATUSES,
            'categories' => SupportRequest::CATEGORIES,
            'supportStaff' => $area === 'admin' ? $this->activeSupportStaffQuery()->orderBy('name')->get(['id', 'name']) : collect(),
        ];
    }

    private function detailData(Request $request, SupportRequest $supportRequest, string $area): array
    {
        $supportRequest->load([
            'user:id,name,email',
            'assignedTo:id,name,email',
            'escalatedBy:id,name',
            'property:id,title,address_text,area,city',
            'inspectionRequest:id,property_id,status,preferred_date',
            'paymentTransaction:id,reference,transaction_type,status,gross_amount,currency,paid_at',
            'maintenanceRequest:id,title,status',
            'occupancyComplaint:id,category,status,description',
            'messages.user:id,name',
            'attachments.message:id,is_internal',
            'events.actor:id,name',
        ]);
        $user = $request->user();
        $isAdminArea = $area === 'admin';
        $canMutate = $isAdminArea || $supportRequest->assigned_to === $user->id;

        return [
            'supportRequest' => $supportRequest,
            'area' => $area,
            'supportStaff' => $isAdminArea ? $this->activeSupportStaffQuery()->orderBy('name')->get(['id', 'name']) : collect(),
            'canMutate' => $canMutate,
            'canClaim' => ! $isAdminArea && $supportRequest->assigned_to === null,
            'availableStatuses' => $isAdminArea ? SupportRequest::STATUSES : $this->staffTransitions($supportRequest->status),
        ];
    }

    private function baseQuery(): Builder
    {
        return SupportRequest::query()->with(['user:id,name,email', 'assignedTo:id,name,email', 'property:id,title']);
    }

    private function activeSupportStaff(int $id): User
    {
        $staff = $this->activeSupportStaffQuery()->find($id);

        if (! $staff) {
            throw ValidationException::withMessages(['assigned_to' => 'Choose an active support staff member.']);
        }

        return $staff;
    }

    private function activeSupportStaffQuery(): Builder
    {
        return User::role('support_staff')->whereNull('suspended_at');
    }

    private function mutateOwnedRequest(Request $request, SupportRequest $supportRequest, callable $mutation, bool $isAdminArea = false): void
    {
        $actor = $request->user();

        DB::transaction(function () use ($supportRequest, $actor, $mutation, $isAdminArea): void {
            $locked = SupportRequest::query()->lockForUpdate()->findOrFail($supportRequest->id);

            if (! $isAdminArea && $locked->assigned_to !== $actor->id) {
                abort(403);
            }

            $mutation($locked, $actor);
        });
    }

    private function event(SupportRequest $supportRequest, User $actor, string $type, string|int|null $previous = null, string|int|null $new = null): SupportRequestEvent
    {
        return $supportRequest->events()->create([
            'actor_id' => $actor->id,
            'event_type' => $type,
            'previous_value' => $previous,
            'new_value' => $new,
        ]);
    }

    private function notifyCustomerOfReply(SupportRequest $supportRequest): void
    {
        app(WorkflowNotifier::class)->notify(
            $supportRequest->user,
            'support-request-public-reply:'.$supportRequest->id.':'.$supportRequest->updated_at?->timestamp,
            'Support replied to '.$supportRequest->reference,
            'VerifyHomes Support has replied to your request.',
            $this->customerSupportRoute($supportRequest),
            'support_request',
            'View request',
        );
    }

    private function notifyCustomerOfStatus(SupportRequest $supportRequest, string $status): void
    {
        $message = $status === 'resolved'
            ? "Your support request {$supportRequest->reference} has been marked resolved. Reply if you still need help."
            : "Your support request {$supportRequest->reference} has been closed.";

        app(WorkflowNotifier::class)->notify(
            $supportRequest->user,
            'support-request-status:'.$supportRequest->id.':'.$status.':'.$supportRequest->updated_at?->timestamp,
            'Support request '.str($status)->headline(),
            $message,
            $this->customerSupportRoute($supportRequest),
            'support_request',
            'View request',
        );
    }

    private function customerSupportRoute(SupportRequest $supportRequest): string
    {
        return $supportRequest->role_snapshot === 'landlord'
            ? route('landlord.support.show', $supportRequest)
            : route('tenant.support.show', $supportRequest);
    }

    /** @return list<string> */
    private function staffTransitions(string $from): array
    {
        return match ($from) {
            'open' => ['in_progress', 'waiting_for_user', 'resolved'],
            'in_progress' => ['waiting_for_user', 'resolved', 'open'],
            'waiting_for_user' => ['in_progress', 'resolved', 'open'],
            'resolved' => ['open', 'in_progress'],
            default => [],
        };
    }
}
