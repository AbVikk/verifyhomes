@php
    $priorityClasses = ['low' => 'bg-slate-100 text-slate-700', 'normal' => 'bg-sky-50 text-sky-800', 'high' => 'bg-amber-50 text-amber-800', 'urgent' => 'bg-rose-50 text-rose-800'];
    $prefix = $area === 'admin' ? 'admin.support' : 'support-team.requests';
    $eventText = function ($event): string {
        return match ($event->event_type) {
            'assigned' => 'assigned this request.',
            'reassigned' => 'reassigned this request.',
            'unassigned' => 'unassigned this request.',
            'claimed' => 'claimed this request.',
            'priority_changed' => 'changed priority from '.str($event->previous_value)->headline().' to '.str($event->new_value)->headline().'.',
            'status_changed' => 'changed status from '.str($event->previous_value)->replace('_', ' ')->headline().' to '.str($event->new_value)->replace('_', ' ')->headline().'.',
            'public_reply_sent' => 'sent a public reply to the customer.',
            'internal_note_added' => 'added an internal note.',
            'escalated_to_admin' => 'escalated this request to Admin.',
            'escalation_cleared' => 'cleared the Admin escalation.',
            default => 'updated this request.',
        };
    };
@endphp

<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>
    @endif

    @if($supportRequest->escalated_at)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900"><span class="font-semibold">Escalated to Admin</span> · {{ str($supportRequest->escalation_category)->replace('_', ' ')->headline() }}@if($area === 'admin')<p class="mt-2">{{ $supportRequest->escalation_note }}</p><form method="POST" action="{{ route($prefix.'.clear-escalation', $supportRequest) }}" class="mt-3">@csrf<button class="rounded-lg border border-amber-300 px-3 py-2 text-xs font-semibold">Clear escalation</button></form>@endif</div>
    @endif

    @if($canMutate && $supportRequest->status !== 'closed')
        <div class="grid gap-4 lg:grid-cols-2">
            <form method="POST" action="{{ route($prefix.'.reply', $supportRequest) }}" class="rounded-xl border border-slate-200 bg-white p-5">@csrf<label class="text-sm font-semibold text-slate-900" for="reply_body">Reply to customer</label><textarea id="reply_body" name="body" rows="4" required class="mt-3 w-full rounded-lg border-slate-300 text-sm"></textarea><label class="mt-3 block text-xs font-medium text-slate-700" for="reply_status">Set status after reply</label><select id="reply_status" name="status" class="mt-1 w-full rounded-lg border-slate-300 text-sm"><option value="">Keep current status</option><option value="in_progress">In progress</option><option value="waiting_for_user">Waiting for user</option><option value="resolved">Resolved</option></select><button class="mt-3 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">Send reply</button></form>
            <form method="POST" action="{{ route($prefix.'.notes', $supportRequest) }}" class="rounded-xl border border-violet-200 bg-violet-50 p-5">@csrf<label class="text-sm font-semibold text-slate-900" for="note_body">Internal note</label><p class="mt-1 text-xs text-slate-600">Only visible to VerifyHomes team.</p><textarea id="note_body" name="body" rows="4" required class="mt-3 w-full rounded-lg border-slate-300 text-sm"></textarea><button class="mt-3 rounded-lg border border-violet-300 bg-white px-4 py-2 text-sm font-medium text-violet-900">Add internal note</button></form>
        </div>
    @endif

    @if($area === 'support-team' && $canMutate && $supportRequest->status !== 'closed' && ! $supportRequest->escalated_at)
        <form method="POST" action="{{ route($prefix.'.escalate', $supportRequest) }}" class="rounded-xl border border-amber-200 bg-amber-50 p-5">@csrf<label class="text-sm font-semibold text-amber-950" for="escalation_category">Escalate to Admin</label><select id="escalation_category" name="category" class="mt-3 w-full rounded-lg border-amber-200 text-sm">@foreach(\App\Models\SupportRequest::ESCALATION_CATEGORIES as $category)<option value="{{ $category }}">{{ str($category)->replace('_', ' ')->headline() }}</option>@endforeach</select><textarea name="note" rows="3" required placeholder="Reason for escalation" class="mt-3 w-full rounded-lg border-amber-200 text-sm"></textarea><button class="mt-3 rounded-lg bg-amber-800 px-4 py-2 text-sm font-medium text-white">Escalate to Admin</button></form>
    @endif

    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <p class="text-xs font-semibold tracking-[0.16em] text-teal-700">{{ $supportRequest->reference }}</p>
        <h2 class="mt-2 text-2xl font-semibold text-slate-950">{{ $supportRequest->subject }}</h2>
        <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <p><span class="font-medium">Requester:</span> {{ $supportRequest->user?->name }} ({{ $supportRequest->user?->email }})</p>
            <p><span class="font-medium">Role:</span> {{ str($supportRequest->role_snapshot)->headline() }}</p>
            <p><span class="font-medium">Category:</span> {{ $supportRequest->categoryLabel() }}</p>
            <p><span class="font-medium">Status:</span> {{ str($supportRequest->status)->replace('_', ' ')->headline() }}</p>
            <p><span class="font-medium">Assigned to:</span> {{ $supportRequest->assignedTo?->name ?? 'Unassigned' }}@if($supportRequest->assigned_at) <span class="text-slate-500">· {{ $supportRequest->assigned_at->format('M j, Y g:i A') }}</span>@endif</p>
            <p><span class="font-medium">Priority:</span> <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $priorityClasses[$supportRequest->priority] ?? $priorityClasses['normal'] }}">{{ str($supportRequest->priority)->headline() }}</span></p>
        </div>
    </div>

    @if($area === 'admin' || $canMutate || $canClaim)
        <div class="grid gap-4 lg:grid-cols-3">
            @if($area === 'admin')
                <form method="POST" action="{{ route($prefix.'.assignment', $supportRequest) }}" class="rounded-xl border border-slate-200 bg-white p-5">
                    @csrf
                    <label for="assigned_to" class="text-sm font-semibold text-slate-900">Assignment</label>
                    <select id="assigned_to" name="assigned_to" class="mt-3 w-full rounded-lg border-slate-300 text-sm">
                        <option value="">Unassigned</option>
                        @foreach($supportStaff as $staff)
                            <option value="{{ $staff->id }}" @selected($supportRequest->assigned_to === $staff->id)>{{ $staff->name }}</option>
                        @endforeach
                    </select>
                    <button class="mt-3 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">Save assignment</button>
                </form>
            @elseif($canClaim)
                <form method="POST" action="{{ route($prefix.'.claim', $supportRequest) }}" class="rounded-xl border border-slate-200 bg-white p-5">
                    @csrf
                    <p class="text-sm font-semibold text-slate-900">This request is unassigned</p>
                    <p class="mt-1 text-sm text-slate-600">Claim it before changing its priority or status.</p>
                    <button class="mt-3 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">Assign to me</button>
                </form>
            @endif

            @if($canMutate)
                <form method="POST" action="{{ route($prefix.'.priority', $supportRequest) }}" class="rounded-xl border border-slate-200 bg-white p-5">
                    @csrf
                    <label for="priority" class="text-sm font-semibold text-slate-900">Priority</label>
                    <select id="priority" name="priority" class="mt-3 w-full rounded-lg border-slate-300 text-sm">
                        @foreach(\App\Models\SupportRequest::PRIORITIES as $priority)
                            <option value="{{ $priority }}" @selected($supportRequest->priority === $priority)>{{ str($priority)->headline() }}</option>
                        @endforeach
                    </select>
                    <button class="mt-3 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">Update priority</button>
                </form>

                <form method="POST" action="{{ route($prefix.'.status', $supportRequest) }}" class="rounded-xl border border-slate-200 bg-white p-5">
                    @csrf
                    <label for="status" class="text-sm font-semibold text-slate-900">Status</label>
                    <select id="status" name="status" class="mt-3 w-full rounded-lg border-slate-300 text-sm">
                        <option value="{{ $supportRequest->status }}">{{ str($supportRequest->status)->replace('_', ' ')->headline() }}</option>
                        @foreach($availableStatuses as $status)
                            @if($status !== $supportRequest->status)<option value="{{ $status }}">{{ str($status)->replace('_', ' ')->headline() }}</option>@endif
                        @endforeach
                    </select>
                    <button class="mt-3 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">Update status</button>
                </form>
            @endif
        </div>
    @endif

    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <h3 class="font-semibold">Linked context</h3>
        <div class="mt-3 space-y-2 text-sm text-slate-700">
            @if($supportRequest->property)<p><span class="font-medium">Property:</span> {{ $supportRequest->property->title }} · {{ collect([$supportRequest->property->area, $supportRequest->property->city])->filter()->join(', ') }}</p>@endif
            @if($supportRequest->inspectionRequest)<p><span class="font-medium">Inspection:</span> #{{ $supportRequest->inspectionRequest->id }} · {{ str($supportRequest->inspectionRequest->status)->headline() }} · {{ $supportRequest->inspectionRequest->preferred_date?->format('M j, Y') }}</p>@endif
            @if($supportRequest->paymentTransaction)<p><span class="font-medium">Payment:</span> {{ $supportRequest->paymentTransaction->reference }} · {{ \App\Support\Currency::format($supportRequest->paymentTransaction->gross_amount, $supportRequest->paymentTransaction->currency) }} · {{ str($supportRequest->paymentTransaction->status)->headline() }}</p>@endif
            @if($supportRequest->maintenanceRequest)<p><span class="font-medium">Maintenance:</span> {{ $supportRequest->maintenanceRequest->title }} · {{ str($supportRequest->maintenanceRequest->status)->replace('_', ' ')->headline() }}</p>@endif
            @if($supportRequest->occupancyComplaint)<p><span class="font-medium">Complaint:</span> {{ str($supportRequest->occupancyComplaint->category)->headline() }} · {{ str($supportRequest->occupancyComplaint->status)->headline() }}</p>@endif
            @if(! $supportRequest->property && ! $supportRequest->inspectionRequest && ! $supportRequest->paymentTransaction && ! $supportRequest->maintenanceRequest && ! $supportRequest->occupancyComplaint)<p class="text-slate-500">No linked context.</p>@endif
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <h3 class="font-semibold">Conversation</h3>
        <div class="mt-4 space-y-4">
            @foreach($supportRequest->messages->where('is_internal', false) as $message)
                <article class="border-l-2 border-teal-500 pl-4"><p class="text-sm font-medium">{{ $message->user?->name ?? 'VerifyHomes' }}</p><p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $message->body }}</p><p class="mt-1 text-xs text-slate-500">{{ $message->created_at->format('M j, Y g:i A') }}</p></article>
            @endforeach
        </div>
    </div>

    @if($supportRequest->messages->where('is_internal', true)->isNotEmpty())
        <div class="rounded-xl border border-violet-200 bg-violet-50 p-5"><h3 class="font-semibold text-violet-950">Internal notes</h3><p class="mt-1 text-xs text-violet-800">Only visible to VerifyHomes team.</p><div class="mt-4 space-y-4">@foreach($supportRequest->messages->where('is_internal', true) as $message)<article class="border-l-2 border-violet-400 pl-4"><p class="text-sm font-medium">{{ $message->user?->name ?? 'VerifyHomes' }}</p><p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $message->body }}</p><p class="mt-1 text-xs text-slate-500">{{ $message->created_at->format('M j, Y g:i A') }}</p></article>@endforeach</div></div>
    @endif

    @if($supportRequest->attachments->isNotEmpty())
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <h3 class="font-semibold">Attachments</h3>
            <p class="mt-1 text-xs text-slate-500">Files stay private and are available only through these authorized controls.</p>
            <div class="mt-3 space-y-3">
                @foreach($supportRequest->attachments as $attachment)
                    <div class="flex flex-col gap-2 rounded-lg border border-slate-100 p-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0"><p class="break-all text-sm font-medium text-slate-800">{{ $attachment->original_name }}</p><p class="mt-1 text-xs text-slate-500">{{ $attachment->mime_type }} | {{ number_format($attachment->file_size / 1024, 1) }} KB | {{ $attachment->message?->is_internal ? 'Internal note' : 'Customer-visible' }}</p></div>
                        <div class="flex shrink-0 gap-3 text-sm"><a class="admin-inline-link" href="{{ route($prefix.'.attachments.view', [$supportRequest, $attachment]) }}" target="_blank" rel="noopener">View</a><a class="admin-inline-link" href="{{ route($prefix.'.attachments.download', [$supportRequest, $attachment]) }}">Download</a></div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if(false)
        <div class="rounded-xl border border-slate-200 bg-white p-5"><h3 class="font-semibold">Attachments</h3><div class="mt-3 space-y-2">@foreach($supportRequest->attachments as $attachment)<a class="block break-all text-sm font-medium text-teal-800 hover:underline" href="{{ route($prefix.'.attachments.view', [$supportRequest, $attachment]) }}" target="_blank" rel="noopener">{{ $attachment->original_name }} · {{ number_format($attachment->file_size / 1024, 1) }} KB</a>@endforeach</div></div>
    @endif

    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <h3 class="font-semibold">Operational activity</h3>
        <div class="mt-4 space-y-4">
            @forelse($supportRequest->events as $event)
                <article class="border-l-2 border-slate-200 pl-4"><p class="text-sm text-slate-700"><span class="font-medium text-slate-900">{{ $event->actor?->name ?? 'VerifyHomes' }}</span> {{ $eventText($event) }}</p><p class="mt-1 text-xs text-slate-500">{{ $event->created_at->format('M j, Y g:i A') }}</p></article>
            @empty
                <p class="text-sm text-slate-500">No operational activity yet.</p>
            @endforelse
        </div>
    </div>
</div>
